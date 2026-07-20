<?php

$sMetadataVersion = '2.0';

$aModule = [
    'id' => 'kussin_asset_cleanup',
    'title' => 'KUSSIN | Asset Cleanup for OXID eShop',
    'description' => 'Provides safe console commands for detecting and deleting orphaned product image assets.',
    'thumbnail' => 'module.png',
    'version' => '0.0.1',
    'author' => 'Kussin | eCommerce und Online-Marketing GmbH',
    'url' => 'https://www.kussin.de/',
    'email' => 'info@kussin.eu',
    'extend' => [],
    'controllers' => [],
    'events' => [],
    'templates' => [],
    'blocks' => [],
    'settings' => [
        [
            'group' => 'kussin_asset_cleanup_master_cleanup',
            'name' => 'aKussinAssetCleanupAdditionalPictureCleanupDirectories',
            'type' => 'arr',
            'value' => [],
        ],
        [
            'group' => 'kussin_asset_cleanup_status',
            'name' => 'aKussinAssetCleanupProtectedDirectories',
            'type' => 'arr',
            'value' => [],
        ],
    ],
];
