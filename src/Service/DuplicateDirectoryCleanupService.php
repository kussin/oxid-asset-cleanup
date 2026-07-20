<?php

declare(strict_types=1);

namespace Kussin\OxidAssetCleanup\Service;

use FilesystemIterator;
use OxidEsales\Eshop\Core\Registry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

class DuplicateDirectoryCleanupService
{
    /**
     * @return array{deleted: int, skipped: int, failed: int, bytes: int, logFile: string}
     */
    public function deleteDuplicates(string $copyDirectory, string $originalDirectory, bool $dryRun, bool $verifyHash): array
    {
        $summary = [
            'deleted' => 0,
            'skipped' => 0,
            'failed' => 0,
            'bytes' => 0,
            'logFile' => $this->createLogFilePath(),
        ];

        $this->writeLogHeader($summary['logFile']);

        $copyDirectory = $this->resolveShopPath($copyDirectory);
        $originalDirectory = $this->resolveShopPath($originalDirectory);

        if (!$this->isValidDirectoryPair($copyDirectory, $originalDirectory)) {
            $summary['failed']++;
            $this->writeLogLine($summary['logFile'], 'invalid_directory_pair', null, '', $copyDirectory . ' => ' . $originalDirectory, $dryRun);
            $this->writeSummary($summary);
            return $summary;
        }

        $copyDirectory = rtrim(realpath($copyDirectory) ?: $copyDirectory, DIRECTORY_SEPARATOR);
        $originalDirectory = rtrim(realpath($originalDirectory) ?: $originalDirectory, DIRECTORY_SEPARATOR);
        $originalFiles = $this->indexOriginalFiles($originalDirectory);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($copyDirectory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            $copyPath = $fileInfo->getPathname();
            $relativePath = $this->normalizePath(substr($copyPath, strlen($copyDirectory) + 1));

            if (!isset($originalFiles[$relativePath])) {
                $summary['skipped']++;
                continue;
            }

            $original = $originalFiles[$relativePath];
            $copySize = (int) $fileInfo->getSize();

            if ($copySize !== $original['bytes']) {
                $summary['skipped']++;
                $this->writeLogLine($summary['logFile'], 'skipped_size_mismatch', $copySize, $relativePath, $copyPath, $dryRun);
                continue;
            }

            if ($verifyHash && hash_file('sha256', $copyPath) !== hash_file('sha256', $original['path'])) {
                $summary['skipped']++;
                $this->writeLogLine($summary['logFile'], 'skipped_hash_mismatch', $copySize, $relativePath, $copyPath, $dryRun);
                continue;
            }

            if ($dryRun) {
                $summary['deleted']++;
                $summary['bytes'] += $copySize;
                $this->writeLogLine($summary['logFile'], 'dry_run_delete', $copySize, $relativePath, $copyPath, true);
                continue;
            }

            if (@unlink($copyPath)) {
                $summary['deleted']++;
                $summary['bytes'] += $copySize;
                $this->writeLogLine($summary['logFile'], 'deleted', $copySize, $relativePath, $copyPath, false);
                continue;
            }

            $summary['failed']++;
            $this->writeLogLine($summary['logFile'], 'failed', $copySize, $relativePath, $copyPath, false);
        }

        $this->writeSummary($summary);

        return $summary;
    }

    /**
     * @return array<string, array{path: string, bytes: int}>
     */
    private function indexOriginalFiles(string $originalDirectory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($originalDirectory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            $relativePath = $this->normalizePath(substr($fileInfo->getPathname(), strlen($originalDirectory) + 1));
            $files[$relativePath] = [
                'path' => $fileInfo->getPathname(),
                'bytes' => (int) $fileInfo->getSize(),
            ];
        }

        return $files;
    }

    private function isValidDirectoryPair(string $copyDirectory, string $originalDirectory): bool
    {
        if (!is_dir($copyDirectory) || !is_dir($originalDirectory)) {
            return false;
        }

        $copyRealPath = realpath($copyDirectory);
        $originalRealPath = realpath($originalDirectory);

        if ($copyRealPath === false || $originalRealPath === false || $copyRealPath === $originalRealPath) {
            return false;
        }

        return $this->isBelowPictureDirectory($copyRealPath) && $this->isBelowPictureDirectory($originalRealPath);
    }

    private function createLogFilePath(): string
    {
        $logDirectory = rtrim((string) Registry::getConfig()->getLogsDir(), DIRECTORY_SEPARATOR);

        if (!is_dir($logDirectory) && !mkdir($logDirectory, 0775, true) && !is_dir($logDirectory)) {
            throw new RuntimeException(sprintf('Could not create log directory: %s', $logDirectory));
        }

        return $logDirectory . DIRECTORY_SEPARATOR . 'KUSSIN_DUPLICATE_DIRECTORY_CLEANUP_' . date('Ymd_His') . '.log';
    }

    private function writeLogHeader(string $logFile): void
    {
        if (file_put_contents($logFile, "deleted_at\tstatus\tdry_run\tfile_size_bytes\trelative_path\tpath\n") === false) {
            throw new RuntimeException(sprintf('Could not open log file: %s', $logFile));
        }
    }

    private function writeLogLine(string $logFile, string $status, ?int $fileSize, string $relativePath, string $path, bool $dryRun): void
    {
        @file_put_contents(
            $logFile,
            sprintf(
                "%s\t%s\t%s\t%s\t%s\t%s\n",
                date('Y-m-d H:i:s'),
                $status,
                $dryRun ? '1' : '0',
                $fileSize === null ? '' : (string) $fileSize,
                $relativePath,
                $path
            ),
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * @param array{deleted: int, skipped: int, failed: int, bytes: int, logFile: string} $summary
     */
    private function writeSummary(array $summary): void
    {
        @file_put_contents(
            $summary['logFile'],
            sprintf(
                "# Summary: deleted=%d skipped=%d failed=%d bytes=%d\n",
                $summary['deleted'],
                $summary['skipped'],
                $summary['failed'],
                $summary['bytes']
            ),
            FILE_APPEND | LOCK_EX
        );
    }

    private function resolveShopPath(string $path): string
    {
        $path = trim($path);
        $shopDirectory = $this->getShopDirectory();

        if (preg_match('#^[a-zA-Z]:[\\\\/]#', $path) || strpos($path, '/') === 0) {
            return $path;
        }

        $path = preg_replace('#^source/#', '', str_replace('\\', '/', $path));

        return $shopDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, trim((string) $path, '/'));
    }

    private function isBelowPictureDirectory(string $path): bool
    {
        $pictureDirectory = rtrim($this->getPictureDirectory(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $path = rtrim(realpath($path) ?: $path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return stripos($path, $pictureDirectory) === 0;
    }

    private function getPictureDirectory(): string
    {
        $directory = (string) Registry::getConfig()->getPictureDir(false);
        $realPath = realpath($directory);

        return rtrim($realPath !== false ? $realPath : $directory, DIRECTORY_SEPARATOR);
    }

    private function getShopDirectory(): string
    {
        $directory = (string) Registry::getConfig()->getConfigParam('sShopDir');
        $realPath = realpath($directory);

        return rtrim($realPath !== false ? $realPath : $directory, DIRECTORY_SEPARATOR);
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
