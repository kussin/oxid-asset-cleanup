<?php

declare(strict_types=1);

namespace Kussin\OxidAssetCleanup\Service;

use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Registry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

class ReferencedPictureCleanupService
{
    private const IMAGE_EXTENSIONS = [
        'gif' => true,
        'jpeg' => true,
        'jpg' => true,
        'png' => true,
    ];

    /** @var AssetCleanupSettingsService */
    private $settingsService;

    /** @var array<string, array{label: string, table: string, fields: array<int, string>, directory: string}> */
    private $targets = [
        'manufacturer' => [
            'label' => 'manufacturer picture',
            'table' => 'oxmanufacturers',
            'fields' => ['OXICON'],
            'directory' => 'master/manufacturer/icon',
        ],
        'vendor' => [
            'label' => 'vendor picture',
            'table' => 'oxvendor',
            'fields' => ['OXICON'],
            'directory' => 'master/vendor/icon',
        ],
        'wrapping' => [
            'label' => 'wrapping picture',
            'table' => 'oxwrapping',
            'fields' => ['OXPIC'],
            'directory' => 'master/wrapping',
        ],
    ];

    public function __construct(?AssetCleanupSettingsService $settingsService = null)
    {
        $this->settingsService = $settingsService ?: new AssetCleanupSettingsService();
    }

    /**
     * @return array<int, array{path: string, relativePath: string, size: int}>
     */
    public function findOrphanedPictures(string $target): array
    {
        $definition = $this->getTargetDefinition($target);
        $pictureDirectory = $this->getPictureDirectory();
        $targetDirectory = $this->getTargetDirectory($target);

        if (!is_dir($targetDirectory) || $this->settingsService->isProtectedPath($targetDirectory)) {
            return [];
        }

        $references = $this->getReferences($definition['table'], $definition['fields']);
        $orphans = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($targetDirectory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || !$this->isSupportedImageFile($file)) {
                continue;
            }

            $path = $file->getPathname();
            if ($this->settingsService->isProtectedPath($path)) {
                continue;
            }

            $relativePath = $this->normalizeRelativePath($path, $pictureDirectory);
            $basename = $this->normalizePath($file->getBasename());

            if (isset($references[$relativePath]) || isset($references[$basename])) {
                continue;
            }

            $orphans[] = [
                'path' => $path,
                'relativePath' => $relativePath,
                'size' => (int) $file->getSize(),
            ];
        }

        usort(
            $orphans,
            static fn (array $left, array $right): int => $left['relativePath'] <=> $right['relativePath']
        );

        return $orphans;
    }

    /**
     * @return array{deleted: int, failed: int, missing: int, empty: int, bytes: int, logFile: string}
     */
    public function deleteOrphanedPictures(string $target, bool $dryRun): array
    {
        $summary = [
            'deleted' => 0,
            'failed' => 0,
            'missing' => 0,
            'empty' => 0,
            'bytes' => 0,
            'logFile' => $this->createLogFilePath($target),
        ];

        $this->writeLogHeader($summary['logFile']);
        $targetDirectory = $this->getTargetDirectory($target);

        if (!is_dir($targetDirectory)) {
            $summary['missing']++;
            $this->writeLogLine($summary['logFile'], 'missing_directory', $dryRun, null, $targetDirectory);
            $this->writeSummary($summary);
            return $summary;
        }

        $files = $this->findOrphanedPictures($target);

        if (!$files) {
            $summary['empty']++;
        }

        foreach ($files as $file) {
            $path = $file['path'];
            $size = $file['size'];

            if (!$this->isBelowPictureDirectory($path) || $this->settingsService->isProtectedPath($path)) {
                $summary['failed']++;
                $this->writeLogLine($summary['logFile'], 'skipped_outside_or_protected', $dryRun, $size, $path);
                continue;
            }

            if ($dryRun) {
                $summary['deleted']++;
                $summary['bytes'] += $size;
                $this->writeLogLine($summary['logFile'], 'dry_run_delete', true, $size, $path);
                continue;
            }

            if (@unlink($path)) {
                $summary['deleted']++;
                $summary['bytes'] += $size;
                $this->writeLogLine($summary['logFile'], 'deleted', false, $size, $path);
                continue;
            }

            $summary['failed']++;
            $this->writeLogLine($summary['logFile'], 'failed', false, $size, $path);
        }

        $this->writeSummary($summary);

        return $summary;
    }

    public function getTargetLabel(string $target): string
    {
        return $this->getTargetDefinition($target)['label'];
    }

    /**
     * @return array{label: string, table: string, fields: array<int, string>, directory: string}
     */
    private function getTargetDefinition(string $target): array
    {
        if (!isset($this->targets[$target])) {
            throw new RuntimeException(sprintf('Unknown cleanup target: %s', $target));
        }

        return $this->targets[$target];
    }

    /**
     * @param array<int, string> $fields
     * @return array<string, true>
     */
    private function getReferences(string $table, array $fields): array
    {
        $sql = 'SELECT ' . implode(', ', $fields) . ' FROM ' . $table;
        $rows = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC)->getAll($sql);
        $references = [];

        foreach ($rows as $row) {
            foreach ($fields as $field) {
                $value = $this->normalizeReferenceValue((string) ($row[$field] ?? ''));

                if ($value === '') {
                    continue;
                }

                $references[$value] = true;
                $references[basename($value)] = true;
            }
        }

        return $references;
    }

    private function normalizeReferenceValue(string $value): string
    {
        $value = trim($this->normalizePath($value));

        if ($value === '' || strtolower($value) === 'nopic.jpg') {
            return '';
        }

        return ltrim($value, '/');
    }

    private function getTargetDirectory(string $target): string
    {
        return $this->getPictureDirectory() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->getTargetDefinition($target)['directory']);
    }

    private function getPictureDirectory(): string
    {
        $directory = (string) Registry::getConfig()->getConfigParam('sShopDir') . DIRECTORY_SEPARATOR . 'out' . DIRECTORY_SEPARATOR . 'pictures';
        $realPath = realpath($directory);

        return rtrim($realPath !== false ? $realPath : $directory, DIRECTORY_SEPARATOR);
    }

    private function normalizeRelativePath(string $path, string $baseDirectory): string
    {
        $path = $this->normalizeFilesystemPath($path);
        $baseDirectory = rtrim($this->normalizeFilesystemPath($baseDirectory), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (stripos($path, $baseDirectory) === 0) {
            return $this->normalizePath(substr($path, strlen($baseDirectory)));
        }

        return $this->normalizePath($path);
    }

    private function normalizeFilesystemPath(string $path): string
    {
        $realPath = realpath($path);

        return $realPath !== false ? $realPath : $path;
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private function isSupportedImageFile(SplFileInfo $file): bool
    {
        return isset(self::IMAGE_EXTENSIONS[strtolower($file->getExtension())]);
    }

    private function isBelowPictureDirectory(string $path): bool
    {
        $filePath = $this->normalizeFilesystemPath($path);
        $pictureDirectory = rtrim($this->getPictureDirectory(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return stripos($filePath, $pictureDirectory) === 0;
    }

    private function createLogFilePath(string $target): string
    {
        $logDirectory = rtrim((string) Registry::getConfig()->getLogsDir(), DIRECTORY_SEPARATOR);

        if (!is_dir($logDirectory) && !mkdir($logDirectory, 0775, true) && !is_dir($logDirectory)) {
            throw new RuntimeException(sprintf('Could not create log directory: %s', $logDirectory));
        }

        return $logDirectory . DIRECTORY_SEPARATOR . 'KUSSIN_' . strtoupper($target) . '_PICTURE_CLEANUP_' . date('Ymd_His') . '.log';
    }

    private function writeLogHeader(string $logFile): void
    {
        if (file_put_contents($logFile, "deleted_at\tstatus\tdry_run\tfile_size_bytes\tpath\n") === false) {
            throw new RuntimeException(sprintf('Could not open log file: %s', $logFile));
        }
    }

    private function writeLogLine(string $logFile, string $status, bool $dryRun, ?int $fileSize, string $path): void
    {
        @file_put_contents(
            $logFile,
            sprintf(
                "%s\t%s\t%s\t%s\t%s\n",
                date('Y-m-d H:i:s'),
                $status,
                $dryRun ? '1' : '0',
                $fileSize === null ? '' : (string) $fileSize,
                $path
            ),
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * @param array{deleted: int, failed: int, missing: int, empty: int, bytes: int, logFile: string} $summary
     */
    private function writeSummary(array $summary): void
    {
        @file_put_contents(
            $summary['logFile'],
            sprintf(
                "# Summary: deleted=%d failed=%d missing_directories=%d empty_directories=%d bytes=%d\n",
                $summary['deleted'],
                $summary['failed'],
                $summary['missing'],
                $summary['empty'],
                $summary['bytes']
            ),
            FILE_APPEND | LOCK_EX
        );
    }
}
