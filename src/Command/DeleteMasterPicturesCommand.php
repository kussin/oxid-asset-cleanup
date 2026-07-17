<?php

declare(strict_types=1);

namespace Kussin\OxidAssetCleanup\Command;

use Kussin\OxidAssetCleanup\Service\MasterPictureCleanupService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteMasterPicturesCommand extends Command
{
    /** @var MasterPictureCleanupService */
    private $cleanupService;

    public function __construct(MasterPictureCleanupService $cleanupService)
    {
        $this->cleanupService = $cleanupService;
        parent::__construct('kussin:asset-cleanup:delete-master');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Deletes orphaned OXID product master pictures after an explicit force confirmation.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Actually delete orphaned files.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Log and print what would be deleted.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $force = (bool) $input->getOption('force');

        if (!$dryRun && !$force) {
            $output->writeln('Refusing to delete files without --force. Use --dry-run for a simulated run.');

            return 1;
        }

        $summary = $this->cleanupService->deleteOrphanedMasterPictures($dryRun);
        $output->writeln(sprintf(
            '%s %d orphaned master picture file(s), %d bytes total, %d failure(s), %d missing directories, %d empty directories.',
            $dryRun ? 'Would delete' : 'Deleted',
            $summary['deleted'],
            $summary['bytes'],
            $summary['failed'],
            $summary['missing'],
            $summary['empty']
        ));
        $output->writeln(sprintf('Log file: %s', $this->cleanupService->getDeleteLogPath()));

        return $summary['failed'] > 0 ? 1 : 0;
    }
}
