<?php

declare(strict_types=1);

namespace Kussin\OxidAssetCleanup\Command;

use Kussin\OxidAssetCleanup\Service\ReferencedPictureCleanupService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteVendorPicturesCommand extends AbstractReferencedPicturesCommand
{
    public function __construct(ReferencedPictureCleanupService $cleanupService)
    {
        parent::__construct($cleanupService, 'kussin:asset-cleanup:delete-vendor', 'vendor');
    }

    protected function configure(): void
    {
        $this->setDescription('Deletes orphaned OXID vendor icon pictures after an explicit force confirmation.');
        $this->configureDeleteOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->executeDelete($input, $output);
    }
}
