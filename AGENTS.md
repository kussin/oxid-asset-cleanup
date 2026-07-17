# AGENTS.md

This file defines module-specific instructions for AI/code agents working on `kussin/oxid-asset-cleanup`.

## Scope

- Package root: `html/source/packages/kussin/oxid-asset-cleanup/`
- External repository path: `E:\GitHub\Kussin_OxidAssetCleanup`
- Target OXID module ID: `kussin_asset_cleanup`
- OXID Composer project root: `html/source/`
- OXID shop root: `html/source/source/`
- Active target platform: OXID eShop PE 6.5.5

## Language Rules

- All Markdown files in this module must be written in English.
- All source-code comments in this module must be written in English.
- Console output must be concise and operational.

## Design Intent

This module provides safe command-line maintenance tools for product and content assets below `source/out/pictures/`.

The first supported cleanup target is `source/out/pictures/master/`, where product master images can become orphaned after products are deleted or article image fields are replaced over multiple years of shop operation.

## Editing Rules

- Use the `kussin` vendor prefix for module IDs, namespaces, command names, log file names, settings, translation keys, and public identifiers.
- Keep cleanup behavior conservative. Deleting files must require an explicit force option.
- Keep deletion logs append-only and human-readable.
- Never delete files outside the resolved OXID picture directory or another explicitly whitelisted shop asset directory.
- Prefer targeted cleanup commands over broad filesystem deletion.
- Keep this package compatible with the local path repository `./packages/kussin/*`.
- Do not require `kussin/oxid-base` while this package targets the current OXID 6.5.5 project.

## Verification

For Composer or OXID console work, run commands from `html/source/`, not from the repository root.

When the module version changes, keep these aligned:

- `composer.json`
- `metadata.php`
- `version.txt`
- documentation references that mention the version
