# KUSSIN | Asset Cleanup for OXID eShop

This OXID 7 module provides console commands for finding and deleting orphaned product image assets.

## Scope

- Composer package: `kussin/oxid-asset-cleanup`
- OXID module ID: `kussin_asset_cleanup`
- Runtime target: OXID eShop PE/CE 7.4+
- Planned base integration: `kussin/oxid-base` should require this package in a future release.
- Package source path: `html/source/packages/kussin/oxid-asset-cleanup/`
- External repository path: `E:\GitHub\Kussin_OxidAssetCleanup`

## Background

Long-running OXID shops accumulate files below `source/out/pictures/` that are no longer referenced by `oxarticles`. This usually happens when article images are replaced or products are deleted while the old image files remain on disk.

The initial implementation focuses on product master images below `source/out/pictures/master/`.

## Commands

Run commands from the OXID Composer project root `html/source/`.

```bash
vendor/bin/oe-console kussin:asset-cleanup:scan-master
vendor/bin/oe-console kussin:asset-cleanup:delete-master --dry-run
vendor/bin/oe-console kussin:asset-cleanup:delete-master --force
```

`scan-master` lists orphaned master image files without deleting anything.

`delete-master` deletes orphaned master image files only when `--force` is provided. Use `--dry-run` to print and log the files that would be deleted.

## Detection Rules

- Referenced article images are read from `oxarticles.OXTHUMB`, `oxarticles.OXICON`, and `oxarticles.OXPIC1` through `oxarticles.OXPIC12`.
- Empty values and `nopic.jpg` are ignored.
- Matching is based on normalized relative paths below the OXID picture directory and the stored file basename.
- Only regular files below the resolved `master` picture directory are candidates.
- Files outside the resolved OXID picture directory are never deleted.

## Logging

Delete runs write an append-only log file:

```text
source/log/kussin_asset_cleanup_deleted_files.log
```

The log records the timestamp, action, file path, file size, and whether the command ran in dry-run mode.

## Installation

The module is intended to be required from the OXID Composer project root:

```bash
composer require kussin/oxid-asset-cleanup
```

In this repository, the OXID Composer project root is `html/source/` and Kussin packages are registered through the local path repository `./packages/kussin/*`.

This package intentionally does not require `kussin/oxid-base`, because the planned dependency direction is `kussin/oxid-base` requiring `kussin/oxid-asset-cleanup`.

## Development Notes

Technical documentation and source-code comments are written in English.

Keep cleanup operations small, explicit, and reversible from backups. The module documents deleted files, but it does not restore them.

---

&copy; 2006-2026 [Kussin | eCommerce und Online-Marketing GmbH](https://www.kussin.de/). All rights reserved.