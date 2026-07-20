<?php

declare(strict_types=1);

namespace Kussin\OxidAssetCleanup\Service;

use OxidEsales\Eshop\Core\Registry;
use InvalidArgumentException;

class AssetCleanupSettingsService
{
    public const ADDITIONAL_PICTURE_CLEANUP_DIRECTORIES = 'aKussinAssetCleanupAdditionalPictureCleanupDirectories';
    public const PROTECTED_DIRECTORIES = 'aKussinAssetCleanupProtectedDirectories';
    private const MODULE_SCOPE = 'module:kussin_asset_cleanup';

    /**
     * @return array<int, string>
     */
    public function getAdditionalPictureCleanupDirectories(): array
    {
        return $this->getArraySetting(self::ADDITIONAL_PICTURE_CLEANUP_DIRECTORIES);
    }

    /**
     * @return array<int, string>
     */
    public function getProtectedDirectories(): array
    {
        return $this->getArraySetting(self::PROTECTED_DIRECTORIES);
    }

    /**
     * @return array<int, string>
     */
    public function getProtectedDirectoryPaths(): array
    {
        $paths = [];

        foreach ($this->getProtectedDirectories() as $directory) {
            $paths[] = $this->resolveShopPath($directory);
        }

        return array_values(array_filter($paths));
    }

    public function isProtectedPath(string $path): bool
    {
        $resolvedPath = $this->normalizeRealPath($path);

        foreach ($this->getProtectedDirectoryPaths() as $protectedDirectory) {
            $protectedDirectory = rtrim($this->normalizeRealPath($protectedDirectory), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

            if (stripos($resolvedPath . (is_dir($resolvedPath) ? DIRECTORY_SEPARATOR : ''), $protectedDirectory) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{added: bool, value: string, values: array<int, string>}
     */
    public function addAdditionalPictureCleanupDirectory(string $directory): array
    {
        return $this->addArraySettingValue(
            self::ADDITIONAL_PICTURE_CLEANUP_DIRECTORIES,
            $this->normalizePictureDirectory($directory)
        );
    }

    /**
     * @return array{added: bool, value: string, values: array<int, string>}
     */
    public function addProtectedDirectory(string $directory): array
    {
        return $this->addArraySettingValue(
            self::PROTECTED_DIRECTORIES,
            $this->normalizeShopDirectory($directory)
        );
    }

    /**
     * @return array<int, string>
     */
    private function getArraySetting(string $settingName): array
    {
        $value = Registry::getConfig()->getConfigParam($settingName);

        if (!is_array($value)) {
            return [];
        }

        $values = array_values(array_filter(array_map('trim', $value), static function (string $directory): bool {
            return $directory !== '';
        }));

        return array_values(array_unique($values));
    }

    /**
     * @return array{added: bool, value: string, values: array<int, string>}
     */
    private function addArraySettingValue(string $settingName, string $value): array
    {
        $values = $this->getArraySetting($settingName);

        if (!in_array($value, $values, true)) {
            $values[] = $value;
            sort($values);
            $this->saveArraySetting($settingName, $values);

            return ['added' => true, 'value' => $value, 'values' => $values];
        }

        return ['added' => false, 'value' => $value, 'values' => $values];
    }

    /**
     * @param array<int, string> $values
     */
    private function saveArraySetting(string $settingName, array $values): void
    {
        $config = Registry::getConfig();

        $config->saveShopConfVar('arr', $settingName, $values, $config->getShopId(), self::MODULE_SCOPE);
        $config->setConfigParam($settingName, $values);
    }

    private function normalizePictureDirectory(string $directory): string
    {
        $directory = trim(str_replace('\\', '/', $directory));
        $directory = preg_replace('#^source/out/pictures/#', '', $directory);
        $directory = preg_replace('#^out/pictures/#', '', $directory);
        $directory = trim((string) $directory, '/');

        if ($directory === '' || strpos($directory, '..') !== false) {
            throw new InvalidArgumentException('Invalid picture cleanup directory.');
        }

        return $directory . '/';
    }

    private function normalizeShopDirectory(string $directory): string
    {
        $directory = trim(str_replace('\\', '/', $directory));
        $shopDirectory = str_replace('\\', '/', rtrim((string) Registry::getConfig()->getConfigParam('sShopDir'), '/\\'));

        if ($shopDirectory !== '' && stripos($directory, $shopDirectory . '/') === 0) {
            $directory = substr($directory, strlen($shopDirectory) + 1);
        }

        $directory = preg_replace('#^source/#', '', $directory);

        $directory = trim((string) $directory, '/');

        if ($directory === '' || strpos($directory, '..') !== false) {
            throw new InvalidArgumentException('Invalid protected directory.');
        }

        return $directory . '/';
    }

    private function resolveShopPath(string $directory): string
    {
        $directory = $this->normalizeShopDirectory($directory);
        $shopDirectory = rtrim((string) Registry::getConfig()->getConfigParam('sShopDir'), DIRECTORY_SEPARATOR);

        return $shopDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, trim($directory, '/'));
    }

    private function normalizeRealPath(string $path): string
    {
        $realPath = realpath($path);

        return $realPath !== false ? $realPath : rtrim($path, DIRECTORY_SEPARATOR);
    }
}
