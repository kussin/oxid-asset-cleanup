<?php

declare(strict_types=1);

namespace Kussin\OxidAssetCleanup\Command;

use Kussin\OxidAssetCleanup\Service\MasterPictureCleanupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'kussin:asset-cleanup:delete-master',
    description: 'Deletes orphaned OXID product master pictures after an explicit force confirmation.'
)]
class DeleteMasterPicturesCommand extends Command
{
    public function __construct(private readonly MasterPictureCleanupService $cleanupService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Actually delete orphaned files.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Log and print what would be deleted.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $force = (bool) $input->getOption('force');

        if (!$dryRun && !$force) {
            $output->writeln('Refusing to delete files without --force. Use --dry-run for a simulated run.');

            return Command::FAILURE;
        }

        $summary = $this->cleanupService->deleteOrphanedMasterPictures($dryRun);
        $output->writeln(sprintf(
            '%s %d orphaned master picture file(s), %d bytes total, %d failure(s).',
            $dryRun ? 'Would delete' : 'Deleted',
            $summary['deleted'],
            $summary['bytes'],
            $summary['failed']
        ));
        $output->writeln(sprintf('Log file: %s', $this->cleanupService->getDeleteLogPath()));

        return $summary['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
