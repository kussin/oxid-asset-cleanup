<?php

declare(strict_types=1);

namespace Kussin\OxidAssetCleanup\Service;

use FilesystemIterator;
use OxidEsales\Eshop\Core\Registry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class StatusReportService
{
    private const ADDITIONAL_DIRECTORIES_SETTING = 'aKussinAssetCleanupAdditionalPictureCleanupDirectories';
    private const PROTECTED_DIRECTORIES_SETTING = 'aKussinAssetCleanupProtectedDirectories';

    /** @var AssetCleanupSettingsService */
    private $settingsService;

    /** @var array<int, string> */
    private $standardOutDirectories = [
        'admin',
        'azure',
        'basic',
        'flow',
        'media',
        'modules',
        'pictures',
        'pictos',
        'src',
    ];

    /** @var array<int, string> */
    private $standardPictureDirectories = [
        'generated',
        'master',
    ];

    public function __construct(?AssetCleanupSettingsService $settingsService = null)
    {
        $this->settingsService = $settingsService ?: new AssetCleanupSettingsService();
    }

    /**
     * @param array<int, string> $largeFileDirectories
     * @return array{
     *   disk: array{path: string, total: int, free: int, used: int, usedPercent: string},
     *   cleanableDirectories: array<int, array{label: string, path: string, exists: bool, bytes: int, files: int}>,
     *   largeFiles: array<int, array{path: string, bytes: int}>,
     *   unusualDirectories: array<int, array{area: string, path: string}>
     * }
     */
    public function createReport(int $minimumLargeFileSize, array $largeFileDirectories): array
    {
        $shopDirectory = $this->getShopDirectory();

        return [
            'disk' => $this->getDiskUsage($shopDirectory),
            'cleanableDirectories' => $this->getCleanableDirectories(),
            'largeFiles' => $this->findLargeFiles($largeFileDirectories ?: $this->getDefaultLargeFileDirectories(), $minimumLargeFileSize),
            'unusualDirectories' => $this->findUnusualDirectories(),
        ];
    }

    public function parseSize(string $value): int
    {
        $value = trim($value);

        if (!preg_match('/^(\d+(?:\.\d+)?)\s*(B|K|KB|M|MB|G|GB)?$/i', $value, $matches)) {
            return 5 * 1024 * 1024;
        }

        $number = (float) $matches[1];
        $unit = strtoupper($matches[2] ?? 'B');
        $factor = 1;

        if ($unit === 'K' || $unit === 'KB') {
            $factor = 1024;
        } elseif ($unit === 'M' || $unit === 'MB') {
            $factor = 1024 * 1024;
        } elseif ($unit === 'G' || $unit === 'GB') {
            $factor = 1024 * 1024 * 1024;
        }

        return (int) round($number * $factor);
    }

    public function formatBytes(int $bytes): string
    {
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        $value = (float) $bytes;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return sprintf('%.2f %s', $value, $units[$unitIndex]);
    }

    /**
     * @return array{path: string, total: int, free: int, used: int, usedPercent: string}
     */
    private function getDiskUsage(string $path): array
    {
        $total = (int) @disk_total_space($path);
        $free = (int) @disk_free_space($path);
        $used = max(0, $total - $free);
        $usedPercent = $total > 0 ? sprintf('%.1f%%', ($used / $total) * 100) : 'n/a';

        return [
            'path' => $path,
            'total' => $total,
            'free' => $free,
            'used' => $used,
            'usedPercent' => $usedPercent,
        ];
    }

    /**
     * @return array<int, array{label: string, path: string, exists: bool, bytes: int, files: int}>
     */
    private function getCleanableDirectories(): array
    {
        $shopDirectory = $this->getShopDirectory();
        $pictureDirectory = $this->getPictureDirectory();
        $directories = [
            'article master cleanup' => $pictureDirectory . DIRECTORY_SEPARATOR . 'master' . DIRECTORY_SEPARATOR . 'product',
            'generated image cache' => $pictureDirectory . DIRECTORY_SEPARATOR . 'generated',
            'FATCHIP out/media WebP cleanup' => $shopDirectory . DIRECTORY_SEPARATOR . 'out' . DIRECTORY_SEPARATOR . 'media',
            'FATCHIP out/pictures WebP cleanup' => $pictureDirectory,
            'FATCHIP out/dixeno_handar WebP cleanup' => $shopDirectory . DIRECTORY_SEPARATOR . 'out' . DIRECTORY_SEPARATOR . 'dixeno_handar',
        ];

        foreach ($this->getAdditionalMasterDirectories() as $directory) {
            $directories['additional master cleanup: ' . $directory] = $this->resolvePictureDirectory($directory);
        }

        $result = [];
        foreach ($directories as $label => $directory) {
            if ($this->settingsService->isProtectedPath($directory)) {
                continue;
            }

            $size = $this->getDirectorySize($directory);
            $result[] = [
                'label' => (string) $label,
                'path' => $directory,
                'exists' => is_dir($directory),
                'bytes' => $size['bytes'],
                'files' => $size['files'],
            ];
        }

        return $result;
    }

    /**
     * @return array{bytes: int, files: int}
     */
    private function getDirectorySize(string $directory): array
    {
        $summary = ['bytes' => 0, 'files' => 0];

        if (!is_dir($directory)) {
            return $summary;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo instanceof SplFileInfo && $fileInfo->isFile()) {
                $summary['files']++;
                $summary['bytes'] += (int) $fileInfo->getSize();
            }
        }

        return $summary;
    }

    /**
     * @param array<int, string> $directories
     * @return array<int, array{path: string, bytes: int}>
     */
    private function findLargeFiles(array $directories, int $minimumSize): array
    {
        $files = [];

        foreach ($directories as $directory) {
            $resolvedDirectory = $this->resolveShopPath($directory);

            if ($resolvedDirectory === null || !is_dir($resolvedDirectory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($resolvedDirectory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
                    continue;
                }

                $size = (int) $fileInfo->getSize();
                if ($size >= $minimumSize) {
                    $files[] = [
                        'path' => $fileInfo->getPathname(),
                        'bytes' => $size,
                    ];
                }
            }
        }

        usort($files, static function (array $left, array $right): int {
            return $right['bytes'] <=> $left['bytes'];
        });

        return array_slice($files, 0, 100);
    }

    /**
     * @return array<int, string>
     */
    private function getDefaultLargeFileDirectories(): array
    {
        $shopDirectory = $this->getShopDirectory();
        $directories = [
            $shopDirectory . DIRECTORY_SEPARATOR . 'out',
        ];

        $exportDirectory = $shopDirectory . DIRECTORY_SEPARATOR . 'export';
        if (is_dir($exportDirectory)) {
            $directories[] = $exportDirectory;
        }

        return $directories;
    }

    /**
     * @return array<int, array{area: string, path: string}>
     */
    private function findUnusualDirectories(): array
    {
        $result = [];
        $outDirectory = $this->getShopDirectory() . DIRECTORY_SEPARATOR . 'out';
        $pictureDirectory = $this->getPictureDirectory();
        $protectedOutDirectories = $this->getProtectedChildDirectoryNames('out');
        $protectedPictureDirectories = $this->getProtectedChildDirectoryNames('out/pictures');
        $knownPictureDirectories = array_merge(
            $this->standardPictureDirectories,
            $this->getAdditionalPictureDirectoryNames(),
            $protectedPictureDirectories
        );

        foreach ($this->findUnexpectedChildDirectories($outDirectory, array_merge($this->standardOutDirectories, $protectedOutDirectories)) as $directory) {
            $result[] = ['area' => 'source/out', 'path' => $directory];
        }

        foreach ($this->findUnexpectedChildDirectories($pictureDirectory, $knownPictureDirectories) as $directory) {
            $result[] = ['area' => 'source/out/pictures', 'path' => $directory];
        }

        return $result;
    }

    /**
     * @param array<int, string> $allowedNames
     * @return array<int, string>
     */
    private function findUnexpectedChildDirectories(string $parentDirectory, array $allowedNames): array
    {
        $directories = [];

        if (!is_dir($parentDirectory)) {
            return $directories;
        }

        $allowed = array_flip(array_map('strtolower', $allowedNames));
        $iterator = new FilesystemIterator($parentDirectory, FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isDir()) {
                continue;
            }

            if (!isset($allowed[strtolower($fileInfo->getBasename())])) {
                $directories[] = $fileInfo->getPathname();
            }
        }

        sort($directories);

        return $directories;
    }

    /**
     * @return array<int, string>
     */
    private function getAdditionalMasterDirectories(): array
    {
        $value = Registry::getConfig()->getConfigParam(self::ADDITIONAL_DIRECTORIES_SETTING);

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $value), static function (string $directory): bool {
            return $directory !== '';
        }));
    }

    /**
     * @return array<int, string>
     */
    private function getAdditionalPictureDirectoryNames(): array
    {
        $names = [];

        foreach ($this->getAdditionalMasterDirectories() as $directory) {
            $name = $this->getFirstPathSegment($directory);

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array<int, string>
     */
    private function getProtectedChildDirectoryNames(string $parentRelativePath): array
    {
        $value = Registry::getConfig()->getConfigParam(self::PROTECTED_DIRECTORIES_SETTING);

        if (!is_array($value)) {
            return [];
        }

        $names = [];
        $parentRelativePath = trim(str_replace('\\', '/', $parentRelativePath), '/') . '/';

        foreach ($value as $directory) {
            $directory = $this->normalizeProtectedDirectory((string) $directory);

            if (stripos($directory, $parentRelativePath) !== 0) {
                continue;
            }

            $remainingPath = substr($directory, strlen($parentRelativePath));
            $name = $this->getFirstPathSegment($remainingPath);

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    private function normalizeProtectedDirectory(string $directory): string
    {
        $directory = trim(str_replace('\\', '/', $directory));
        $shopDirectory = str_replace('\\', '/', rtrim($this->getShopDirectory(), '/\\'));

        if ($shopDirectory !== '' && stripos($directory, $shopDirectory . '/') === 0) {
            $directory = substr($directory, strlen($shopDirectory) + 1);
        }

        $directory = preg_replace('#^source/#', '', $directory);

        return trim((string) $directory, '/') . '/';
    }

    private function getFirstPathSegment(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        if ($path === '') {
            return '';
        }

        $parts = explode('/', $path);

        return (string) $parts[0];
    }

    private function resolvePictureDirectory(string $directory): string
    {
        $directory = trim(str_replace('\\', '/', $directory));
        $directory = preg_replace('#^source/out/pictures/#', '', $directory);
        $directory = preg_replace('#^out/pictures/#', '', $directory);
        $directory = trim((string) $directory, '/');

        return $this->getPictureDirectory() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory);
    }

    private function resolveShopPath(string $path): ?string
    {
        $path = trim($path);
        $shopDirectory = $this->getShopDirectory();

        if ($path === '') {
            return null;
        }

        if (preg_match('#^[a-zA-Z]:[\\\\/]#', $path) || strpos($path, '/') === 0) {
            return $path;
        }

        $path = preg_replace('#^source/#', '', str_replace('\\', '/', $path));

        return $shopDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, trim((string) $path, '/'));
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
}
