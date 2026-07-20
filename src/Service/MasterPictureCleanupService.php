<?php

declare(strict_types=1);

namespace Kussin\OxidAssetCleanup\Service;

use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Registry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class MasterPictureCleanupService
{
    private const ADDITIONAL_DIRECTORIES_SETTING = 'aKussinAssetCleanupAdditionalPictureCleanupDirectories';
    private const MAX_ARTICLE_PICTURES = 12;
    private const DELETE_LOG_FILE = 'kussin_asset_cleanup_deleted_files.log';
    private const IMAGE_EXTENSIONS = [
        'gif' => true,
        'jpeg' => true,
        'jpg' => true,
        'png' => true,
    ];

    /** @var array<int, string> */
    private $emptyDirectories = [];

    /** @var array<int, string> */
    private $missingDirectories = [];

    /** @var AssetCleanupSettingsService */
    private $settingsService;

    public function __construct(AssetCleanupSettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * @return array<int, array{path: string, relativePath: string, size: int}>
     */
    public function findOrphanedMasterPictures(): array
    {
        $pictureDirectory = $this->getPictureDirectory();
        $referencedPictures = $this->getReferencedArticlePictures();
        $orphans = [];

        $this->emptyDirectories = [];
        $this->missingDirectories = [];

        foreach ($this->getMasterPictureDirectories() as $masterDirectory) {
            if ($this->settingsService->isProtectedPath($masterDirectory)) {
                continue;
            }

            if (!is_dir($masterDirectory)) {
                $this->missingDirectories[] = $masterDirectory;
                continue;
            }

            $foundFile = false;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($masterDirectory, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file instanceof SplFileInfo || !$file->isFile()) {
                    continue;
                }

                $foundFile = true;

                if (!$this->isSupportedImageFile($file)) {
                    continue;
                }

                $path = $file->getPathname();
                $relativePath = $this->normalizeRelativePath($path, $pictureDirectory);
                $basename = $this->normalizePath($file->getBasename());

                if ($this->settingsService->isProtectedPath($path)) {
                    continue;
                }

                if (isset($referencedPictures[$relativePath]) || isset($referencedPictures[$basename])) {
                    continue;
                }

                $orphans[] = [
                    'path' => $path,
                    'relativePath' => $relativePath,
                    'size' => (int) $file->getSize(),
                ];
            }

            if (!$foundFile) {
                $this->emptyDirectories[] = $masterDirectory;
            }
        }

        usort(
            $orphans,
            static fn (array $left, array $right): int => $left['relativePath'] <=> $right['relativePath']
        );

        return $orphans;
    }

    /**
     * @return array{deleted: int, failed: int, missing: int, empty: int, bytes: int}
     */
    public function deleteOrphanedMasterPictures(bool $dryRun): array
    {
        $summary = [
            'deleted' => 0,
            'failed' => 0,
            'missing' => 0,
            'empty' => 0,
            'bytes' => 0,
        ];

        $files = $this->findOrphanedMasterPictures();

        foreach ($this->missingDirectories as $directory) {
            $summary['missing']++;
            $this->writeDeletionLog('MISSING_DIRECTORY', $directory, 0, $dryRun);
        }

        foreach ($this->emptyDirectories as $directory) {
            $summary['empty']++;
            $this->writeDeletionLog('EMPTY_DIRECTORY_REMOVE_MANUALLY', $directory, 0, $dryRun);
        }

        foreach ($files as $file) {
            $path = $file['path'];
            $size = $file['size'];

            if (!$this->isBelowPictureDirectory($path)) {
                $summary['failed']++;
                $this->writeDeletionLog('SKIPPED_OUTSIDE_PICTURE_DIR', $path, $size, $dryRun);
                continue;
            }

            if ($dryRun) {
                $summary['deleted']++;
                $summary['bytes'] += $size;
                $this->writeDeletionLog('DRY_RUN_DELETE', $path, $size, true);
                continue;
            }

            if (@unlink($path)) {
                $summary['deleted']++;
                $summary['bytes'] += $size;
                $this->writeDeletionLog('DELETED', $path, $size, false);
                continue;
            }

            $summary['failed']++;
            $this->writeDeletionLog('FAILED', $path, $size, false);
        }

        return $summary;
    }

    public function getDeleteLogPath(): string
    {
        $logDirectory = rtrim((string) Registry::getConfig()->getLogsDir(), DIRECTORY_SEPARATOR);

        return $logDirectory . DIRECTORY_SEPARATOR . self::DELETE_LOG_FILE;
    }

    /**
     * @return array<int, string>
     */
    private function getMasterPictureDirectories(): array
    {
        $pictureDirectory = $this->getPictureDirectory();
        $directories = [
            $pictureDirectory . DIRECTORY_SEPARATOR . 'master' . DIRECTORY_SEPARATOR . 'product',
        ];

        foreach ($this->getAdditionalPictureDirectories() as $directory) {
            $targetDirectory = $this->resolvePictureDirectory($pictureDirectory, $directory);

            if ($targetDirectory !== null && !in_array($targetDirectory, $directories, true)) {
                $directories[] = $targetDirectory;
            }
        }

        return $directories;
    }

    private function getPictureDirectory(): string
    {
        $config = Registry::getConfig();
        $pictureDirectory = (string) $config->getConfigParam('sShopDir') . DIRECTORY_SEPARATOR . 'out' . DIRECTORY_SEPARATOR . 'pictures';

        return $this->normalizeFilesystemPath($pictureDirectory);
    }

    /**
     * @return array<int, string>
     */
    private function getAdditionalPictureDirectories(): array
    {
        $value = Registry::getConfig()->getConfigParam(self::ADDITIONAL_DIRECTORIES_SETTING);

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $value), static function (string $directory): bool {
            return $directory !== '';
        }));
    }

    private function resolvePictureDirectory(string $pictureDirectory, string $directory): ?string
    {
        $directory = trim(str_replace('\\', '/', $directory));
        $directory = preg_replace('#^source/out/pictures/#', '', $directory);
        $directory = preg_replace('#^out/pictures/#', '', $directory);
        $directory = trim((string) $directory, '/');

        if ($directory === '' || strpos($directory, '..') !== false) {
            return null;
        }

        return rtrim($pictureDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory);
    }

    /**
     * @return array<string, true>
     */
    private function getReferencedArticlePictures(): array
    {
        $fields = [
            'OXTHUMB',
            'OXICON',
        ];

        for ($index = 1; $index <= self::MAX_ARTICLE_PICTURES; $index++) {
            $fields[] = 'OXPIC' . $index;
        }

        $sql = 'SELECT ' . implode(', ', $fields) . ' FROM oxarticles';
        $rows = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC)->getAll($sql);
        $references = [];

        foreach ($rows as $row) {
            foreach ($fields as $field) {
                $value = $this->normalizeArticlePictureValue((string) ($row[$field] ?? ''));

                if ($value === '') {
                    continue;
                }

                $references[$value] = true;
                $references[basename($value)] = true;
            }
        }

        return $references;
    }

    private function normalizeArticlePictureValue(string $value): string
    {
        $value = trim($this->normalizePath($value));

        if ($value === '' || strtolower($value) === 'nopic.jpg') {
            return '';
        }

        return ltrim($value, '/');
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

    private function writeDeletionLog(string $action, string $path, int $size, bool $dryRun): void
    {
        $line = sprintf(
            "[%s] action=%s dryRun=%s size=%d path=%s\n",
            date('c'),
            $action,
            $dryRun ? '1' : '0',
            $size,
            $path
        );

        @file_put_contents($this->getDeleteLogPath(), $line, FILE_APPEND | LOCK_EX);
    }
}
