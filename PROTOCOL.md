# PROTOCOL.md

This file is the long-term development memory for `kussin/oxid-asset-cleanup`.

## Current Decisions

- Composer package name: `kussin/oxid-asset-cleanup`.
- OXID module ID: `kussin_asset_cleanup`.
- The module does not require `kussin/oxid-base` to avoid a future Composer dependency cycle.
- Planned dependency direction: `kussin/oxid-base` should require `kussin/oxid-asset-cleanup` after validation.
- The package provides OXID console commands for product image asset maintenance.
- The first cleanup target is `source/out/pictures/master/`.
- Detection compares files below the master picture directory with article image references from `oxarticles.OXTHUMB`, `oxarticles.OXICON`, and `oxarticles.OXPIC1` through `oxarticles.OXPIC12`.
- Deletion requires the explicit `--force` option.
- Dry runs are supported through `--dry-run`.
- Deleted files are documented in `source/log/kussin_asset_cleanup_deleted_files.log`.
- Cleanup code must never delete files outside the resolved OXID picture directory.

## Future Work

- Add cleanup commands for generated product picture caches below `source/out/pictures/generated/`.
- Add optional age thresholds to avoid touching very recent files.
- Add batch limits for very large installations.
- Add CSV or JSON report output for audit workflows.

## Open Questions

No open questions are currently recorded.
