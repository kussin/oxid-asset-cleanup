<?php

declare(strict_types=1);

namespace Kussin\OxidAssetCleanup\Command;

use Kussin\OxidAssetCleanup\Service\MasterPictureCleanupService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ScanMasterPicturesCommand extends Command
{
    /** @var MasterPictureCleanupService */
    private $cleanupService;

    public function __construct(MasterPictureCleanupService $cleanupService)
    {
        $this->cleanupService = $cleanupService;
        parent::__construct('kussin:asset-cleanup:scan-master');
    }

    protected function configure(): void
    {
        $this->setDescription('Lists orphaned OXID product master pictures without deleting files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $orphans = $this->cleanupService->findOrphanedMasterPictures();
        $bytes = 0;

        foreach ($orphans as $file) {
            $bytes += $file['size'];
            $output->writeln(sprintf('%s (%d bytes)', $file['relativePath'], $file['size']));
        }

        $output->writeln(sprintf('Found %d orphaned master picture file(s), %d bytes total.', count($orphans), $bytes));

        return 0;
    }
}
