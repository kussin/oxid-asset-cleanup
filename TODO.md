# TODO

This file tracks planned cleanup commands for `kussin/oxid-asset-cleanup`.

## Next Cleanup Targets

- Add commands for FATCHIP-generated WebP images.
- Add commands for assets linked from `oxcontents` and Visual CMS content.
- Add commands for images linked from `oxmanufacturers`.
- Add commands for images linked from `oxvendor`.
- Add commands for generated product picture caches below `source/out/pictures/generated/`.

## Command Behavior

- Keep scan and delete commands separate or provide a safe scan-first workflow.
- Require `--force` for every destructive command.
- Support `--dry-run` for every destructive command.
- Write deleted files to `source/log/kussin_asset_cleanup_deleted_files.log`.
- Never delete files outside the resolved OXID picture directory or another explicitly whitelisted shop asset directory.

## Open Questions

- Identify the exact FATCHIP WebP output paths and naming conventions in this installation.
- Identify all Visual CMS asset storage fields and embedded markup patterns before implementing content cleanup.
- Decide whether manufacturer and vendor cleanup should scan only standard OXID image fields or also rich text and module-owned extension fields.
