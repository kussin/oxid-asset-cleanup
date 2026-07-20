<?php

declare(strict_types=1);

namespace Kussin\OxidAssetCleanup\Command;

use Kussin\OxidAssetCleanup\Service\ReferencedPictureCleanupService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ScanWrappingPicturesCommand extends AbstractReferencedPicturesCommand
{
    public function __construct(ReferencedPictureCleanupService $cleanupService)
    {
        parent::__construct($cleanupService, 'kussin:asset-cleanup:scan-wrapping', 'wrapping');
    }

    protected function configure(): void
    {
        $this->setDescription('Lists orphaned OXID wrapping pictures without deleting files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->executeScan($output);
    }
}
