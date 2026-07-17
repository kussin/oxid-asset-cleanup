# PROTOCOL.md

This file is the long-term development memory for `kussin/oxid-asset-cleanup`.

## Current Decisions

- Composer package name: `kussin/oxid-asset-cleanup`.
- OXID module ID: `kussin_asset_cleanup`.
- Current package version: `0.0.1`.
- Active target platform: OXID eShop PE 6.5.5.
- The module does not require `kussin/oxid-base` because the current OXID 6 project has no base module dependency.
- The package provides OXID console commands for product image asset maintenance.
- The first cleanup target is article master images below `source/out/pictures/master/product/`.
- Detection compares image files below the article master picture directory with article image references from `oxarticles.OXTHUMB`, `oxarticles.OXICON`, and `oxarticles.OXPIC1` through `oxarticles.OXPIC12`.
- Article master cleanup includes `gif`, `jpeg`, `jpg`, and `png` files only.
- Article master cleanup excludes `.webp` files so generated FATCHIP assets are handled by `kussin:asset-cleanup:delete-fcwebp`.
- Non-article master areas such as `master/vendor`, `master/manufacturer`, and `master/wrapping` are excluded from article master cleanup.
- FATCHIP WebP cleanup removes generated `.webp` files below `source/out/dixeno_handar`, `source/out/media`, and `source/out/pictures`.
- FATCHIP WebP cleanup writes timestamped logs named `KUSSIN_FCWEBP_CLEAR_WEBP_<timestamp>.log`.
- Image cache flush removes generated OXID image cache files below `source/out/pictures/generated/`.
- OXID regenerates generated image cache files on demand through the standard `out/pictures/generated/...` rewrite to `getimg.php`.
- Additional legacy picture cache directories can be configured through `aKussinAssetCleanupAdditionalPictureCleanupDirectories`.
- Additional configured directories are resolved below `source/out/pictures/`; paths outside the picture directory are rejected.
- Empty configured directories are logged with `empty_directory_remove_manually` and are not removed automatically.
- Deletion requires the explicit `--force` option.
- Dry runs are supported through `--dry-run`.
- Deleted files are documented in `source/log/kussin_asset_cleanup_deleted_files.log`.
- Cleanup code must never delete files outside the resolved OXID picture directory or another explicitly whitelisted shop asset directory.

## Future Work

- Add cleanup commands for assets linked from `oxcontents` and Visual CMS content.
- Add cleanup commands for images linked from `oxmanufacturers`.
- Add cleanup commands for images linked from `oxvendor`.
- Add cleanup commands for wrapping and gift-card images below `source/out/pictures/master/wrapping/`.
- Audit legacy picture subdirectories such as `0`, `1`, `z1`, `__master`, `_master`, and `_generated` before adding them to the configurable image cache flush directories.
- Add a large-file report command with a default threshold of `2 MB`, a configurable minimum-size parameter, and configurable target directories such as `source/export/`.
- Add optional age thresholds to avoid touching very recent files.
- Add batch limits for very large installations.
- Add CSV or JSON report output for audit workflows.

## Open Questions

No open questions are currently recorded.
