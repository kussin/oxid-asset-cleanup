# TODO

This file tracks planned cleanup commands for `kussin/oxid-asset-cleanup`.

## Next Cleanup Targets

- Add commands for assets linked from `oxcontents` and Visual CMS content.
- Audit legacy picture subdirectories such as `0`, `1`, `z1`, `__master`, `_master`, and `_generated` before adding them to the configurable master cleanup directories.

## Command Behavior

- Keep scan and delete commands separate or provide a safe scan-first workflow.
- Require `--force` for every destructive command.
- Support `--dry-run` for every destructive command.
- The status command must keep its configurable minimum size parameter and one or more configurable target directories, for example `source/export/`.
- Directories configured in `aKussinAssetCleanupProtectedDirectories` must remain reporting exclusions and deletion guards, not cleanup targets.
- Write deleted files to `source/log/kussin_asset_cleanup_deleted_files.log`.
- Never delete files outside the resolved OXID picture directory or another explicitly whitelisted shop asset directory.

## Open Questions

- Identify all Visual CMS asset storage fields and embedded markup patterns before implementing content cleanup.
- Decide which legacy `source/out/pictures/` subdirectories correspond to old master-picture structures and are safe to configure for `kussin:asset-cleanup:delete-master`.
