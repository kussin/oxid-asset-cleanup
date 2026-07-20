<?php

declare(strict_types=1);

namespace Kussin\OxidAssetCleanup\Service;

use FilesystemIterator;
use OxidEsales\Eshop\Core\Registry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

class FatchipWebpCleanupService
{
    /** @var AssetCleanupSettingsService */
    private $settingsService;

    /** @var array<int, string> */
    private $relativeTargetDirectories = [
        'out/dixeno_handar',
        'out/media',
        'out/pictures',
    ];

    public function __construct(?AssetCleanupSettingsService $settingsService = null)
    {
        $this->settingsService = $settingsService ?: new AssetCleanupSettingsService();
    }

    /**
     * @return array{deleted: int, failed: int, missing: int, bytes: int, logFile: string}
     */
    public function deleteGeneratedWebpFiles(bool $dryRun): array
    {
        $summary = [
            'deleted' => 0,
            'failed' => 0,
            'missing' => 0,
            'bytes' => 0,
            'logFile' => $this->createLogFilePath(),
        ];

        $this->writeLogHeader($summary['logFile']);

        foreach ($this->getTargetDirectories() as $targetDirectory) {
            $resolvedTargetDirectory = realpath($targetDirectory);

            if ($resolvedTargetDirectory === false || !is_dir($resolvedTargetDirectory)) {
                $summary['missing']++;
                $this->writeLogLine($summary['logFile'], 'missing_directory', null, null, $targetDirectory, $dryRun);
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($resolvedTargetDirectory, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
                    continue;
                }

                if (strtolower($fileInfo->getExtension()) !== 'webp') {
                    continue;
                }

                $filePath = $fileInfo->getPathname();
                $fileSize = (int) $fileInfo->getSize();
                $creationTime = date('Y-m-d H:i:s', $fileInfo->getCTime());

                if ($this->settingsService->isProtectedPath($filePath)) {
                    $this->writeLogLine($summary['logFile'], 'skipped_protected_directory', $fileSize, $creationTime, $filePath, $dryRun);
                    continue;
                }

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
        }

        $this->writeSummary($summary);

        return $summary;
    }

    /**
     * @return array<int, string>
     */
    private function getTargetDirectories(): array
    {
        $shopDirectory = rtrim((string) Registry::getConfig()->getConfigParam('sShopDir'), DIRECTORY_SEPARATOR);
        $directories = [];

        foreach ($this->relativeTargetDirectories as $relativeDirectory) {
            $directories[] = $shopDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        }

        return $directories;
    }

    private function createLogFilePath(): string
    {
        $logDirectory = rtrim((string) Registry::getConfig()->getLogsDir(), DIRECTORY_SEPARATOR);

        if (!is_dir($logDirectory) && !mkdir($logDirectory, 0775, true) && !is_dir($logDirectory)) {
            throw new RuntimeException(sprintf('Could not create log directory: %s', $logDirectory));
        }

        return $logDirectory . DIRECTORY_SEPARATOR . 'KUSSIN_FCWEBP_CLEAR_WEBP_' . date('Ymd_His') . '.log';
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
     * @param array{deleted: int, failed: int, missing: int, bytes: int, logFile: string} $summary
     */
    private function writeSummary(array $summary): void
    {
        @file_put_contents(
            $summary['logFile'],
            sprintf(
                "# Summary: deleted=%d failed=%d missing_directories=%d bytes=%d\n",
                $summary['deleted'],
                $summary['failed'],
                $summary['missing'],
                $summary['bytes']
            ),
            FILE_APPEND | LOCK_EX
        );
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
