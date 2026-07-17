<?php

declare(strict_types=1);

namespace Kussin\OxidAssetCleanup\Command;

use Kussin\OxidAssetCleanup\Service\FatchipWebpCleanupService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteFatchipWebpCommand extends Command
{
    /** @var FatchipWebpCleanupService */
    private $cleanupService;

    public function __construct(FatchipWebpCleanupService $cleanupService)
    {
        $this->cleanupService = $cleanupService;
        parent::__construct('kussin:asset-cleanup:delete-fcwebp');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Deletes FATCHIP-generated WebP files below the WH1 asset directories.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Actually delete generated WebP files.')
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

        $summary = $this->cleanupService->deleteGeneratedWebpFiles($dryRun);
        $output->writeln(sprintf(
            '%s %d FATCHIP WebP file(s), %d bytes total, %d failure(s), %d missing directories.',
            $dryRun ? 'Would delete' : 'Deleted',
            $summary['deleted'],
            $summary['bytes'],
            $summary['failed'],
            $summary['missing']
        ));
        $output->writeln(sprintf('Log file: %s', $summary['logFile']));

        return $summary['failed'] > 0 ? 1 : 0;
    }
}
