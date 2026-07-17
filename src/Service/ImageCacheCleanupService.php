<?php

declare(strict_types=1);

namespace Kussin\OxidAssetCleanup\Service;

use FilesystemIterator;
use OxidEsales\Eshop\Core\Registry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

class ImageCacheCleanupService
{
    private const ADDITIONAL_DIRECTORIES_SETTING = 'aKussinAssetCleanupAdditionalPictureCleanupDirectories';

    /** @var array<int, string> */
    private $defaultPictureDirectories = [
        'generated',
    ];

    /**
     * @return array{deleted: int, failed: int, missing: int, empty: int, bytes: int, logFile: string}
     */
    public function flushImageCache(bool $dryRun): array
    {
        $summary = [
            'deleted' => 0,
            'failed' => 0,
            'missing' => 0,
            'empty' => 0,
            'bytes' => 0,
            'logFile' => $this->createLogFilePath(),
        ];

        $this->writeLogHeader($summary['logFile']);

        foreach ($this->getTargetDirectories() as $targetDirectory) {
            $this->processDirectory($targetDirectory, $dryRun, $summary);
        }

        $this->writeSummary($summary);

        return $summary;
    }

    /**
     * @param array{deleted: int, failed: int, missing: int, empty: int, bytes: int, logFile: string} $summary
     */
    private function processDirectory(string $targetDirectory, bool $dryRun, array &$summary): void
    {
        $resolvedTargetDirectory = realpath($targetDirectory);

        if ($resolvedTargetDirectory === false || !is_dir($resolvedTargetDirectory)) {
            $summary['missing']++;
            $this->writeLogLine($summary['logFile'], 'missing_directory', null, null, $targetDirectory, $dryRun);
            return;
        }

        if (!$this->isBelowPictureDirectory($resolvedTargetDirectory)) {
            $summary['failed']++;
            $this->writeLogLine($summary['logFile'], 'skipped_outside_picture_dir', null, null, $resolvedTargetDirectory, $dryRun);
            return;
        }

        $foundFile = false;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($resolvedTargetDirectory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            $foundFile = true;
            $filePath = $fileInfo->getPathname();
            $fileSize = (int) $fileInfo->getSize();
            $creationTime = date('Y-m-d H:i:s', $fileInfo->getCTime());

            if (!$this->isBelowDirectory($filePath, $resolvedTargetDirectory)) {
                $summary['failed']++;
                $this->writeLogLine($summary['logFile'], 'skipped_outside_target', $fileSize, $creationTime, $filePath, $dryRun);
                continue;
            }

            if ($dryRun) {
                $summary['deleted']++;
                $summary['bytes'] += $fileSize;
                $this->writeLogLine($summary['logFile'], 'dry_run_delete', $fileSize, $creationTime, $filePath, true);
                continue;
            }

            if (@unlink($filePath)) {
                $summary['deleted']++;
                $summary['bytes'] += $fileSize;
                $this->writeLogLine($summary['logFile'], 'deleted', $fileSize, $creationTime, $filePath, false);
                continue;
            }

            $summary['failed']++;
            $this->writeLogLine($summary['logFile'], 'failed', $fileSize, $creationTime, $filePath, false);
        }

        if (!$foundFile) {
            $summary['empty']++;
            $this->writeLogLine($summary['logFile'], 'empty_directory_remove_manually', null, null, $resolvedTargetDirectory, $dryRun);
        }
    }

    /**
     * @return array<int, string>
     */
    private function getTargetDirectories(): array
    {
        $pictureDirectory = $this->getPictureDirectory();
        $directories = [];

        foreach (array_merge($this->defaultPictureDirectories, $this->getAdditionalPictureDirectories()) as $directory) {
            $targetDirectory = $this->resolvePictureDirectory($pictureDirectory, $directory);

            if ($targetDirectory !== null && !in_array($targetDirectory, $directories, true)) {
                $directories[] = $targetDirectory;
            }
        }

        return $directories;
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

        return $pictureDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory);
    }

    private function getPictureDirectory(): string
    {
        $directory = (string) Registry::getConfig()->getPictureDir(false);
        $realPath = realpath($directory);

        return rtrim($realPath !== false ? $realPath : $directory, DIRECTORY_SEPARATOR);
    }

    private function createLogFilePath(): string
    {
        $logDirectory = rtrim((string) Registry::getConfig()->getLogsDir(), DIRECTORY_SEPARATOR);

        if (!is_dir($logDirectory) && !mkdir($logDirectory, 0775, true) && !is_dir($logDirectory)) {
            throw new RuntimeException(sprintf('Could not create log directory: %s', $logDirectory));
        }

        return $logDirectory . DIRECTORY_SEPARATOR . 'KUSSIN_IMAGE_CACHE_FLUSH_' . date('Ymd_His') . '.log';
    }

    private function writeLogHeader(string $logFile): void
    {
        if (file_put_contents($logFile, "deleted_at\tstatus\tdry_run\tfile_size_bytes\tcreation_time\tpath\n") === false) {
            throw new RuntimeException(sprintf('Could not open log file: %s', $logFile));
        }
    }

    private function writeLogLine(
        string $logFile,
        string $status,
        ?int $fileSize,
        ?string $creationTime,
        string $path,
        bool $dryRun
    ): void {
        @file_put_contents(
            $logFile,
            sprintf(
                "%s\t%s\t%s\t%s\t%s\t%s\n",
                date('Y-m-d H:i:s'),
                $status,
                $dryRun ? '1' : '0',
                $fileSize === null ? '' : (string) $fileSize,
                $creationTime ?? '',
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

    private function isBelowPictureDirectory(string $path): bool
    {
        return $this->isBelowDirectory($path, $this->getPictureDirectory());
    }

    private function isBelowDirectory(string $filePath, string $baseDirectory): bool
    {
        $resolvedFilePath = realpath($filePath);
        $resolvedBaseDirectory = realpath($baseDirectory);

        if ($resolvedFilePath === false || $resolvedBaseDirectory === false) {
            return false;
        }

        $resolvedBaseDirectory = rtrim($resolvedBaseDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return stripos($resolvedFilePath, $resolvedBaseDirectory) === 0;
    }
}
