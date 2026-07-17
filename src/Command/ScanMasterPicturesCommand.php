<?php

declare(strict_types=1);

namespace Kussin\OxidAssetCleanup\Command;

use Kussin\OxidAssetCleanup\Service\MasterPictureCleanupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'kussin:asset-cleanup:scan-master',
    description: 'Lists orphaned OXID product master pictures without deleting files.'
)]
class ScanMasterPicturesCommand extends Command
{
    public function __construct(private readonly MasterPictureCleanupService $cleanupService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $orphans = $this->cleanupService->findOrphanedMasterPictures();
        $bytes = 0;

        foreach ($orphans as $file) {
            $bytes += $file['size'];
            $output->writeln(sprintf('%s (%d bytes)', $file['relativePath'], $file['size']));
        }

        $output->writeln(sprintf('Found %d orphaned master picture file(s), %d bytes total.', count($orphans), $bytes));

        return Command::SUCCESS;
    }
}
