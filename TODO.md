# TODO

This file tracks planned cleanup commands for `kussin/oxid-asset-cleanup`.

## Next Cleanup Targets

- Add commands for assets linked from `oxcontents` and Visual CMS content.
- Add commands for images linked from `oxmanufacturers`.
- Add commands for images linked from `oxvendor`.
- Add commands for wrapping and gift-card images below `source/out/pictures/master/wrapping/`.
- Audit legacy picture subdirectories such as `0`, `1`, `z1`, `__master`, `_master`, and `_generated` before adding them to the configurable image cache flush directories.
- Add a large-file report command that lists files at or above a configurable size threshold. Default threshold: `2 MB`.

## Command Behavior

- Keep scan and delete commands separate or provide a safe scan-first workflow.
- Require `--force` for every destructive command.
- Support `--dry-run` for every destructive command.
- The future large-file report command must accept a configurable minimum size parameter and one or more configurable target directories, for example `source/export/`.
- Write deleted files to `source/log/kussin_asset_cleanup_deleted_files.log`.
- Never delete files outside the resolved OXID picture directory or another explicitly whitelisted shop asset directory.

## Open Questions

- Identify all Visual CMS asset storage fields and embedded markup patterns before implementing content cleanup.
- Decide whether manufacturer and vendor cleanup should scan only standard OXID image fields or also rich text and module-owned extension fields.
- Decide which legacy `source/out/pictures/` subdirectories are cache-only and safe to configure for `kussin:asset-cleanup:flush-image-cache`.
