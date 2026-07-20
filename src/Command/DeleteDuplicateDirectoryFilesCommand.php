<?php

declare(strict_types=1);

namespace Kussin\OxidAssetCleanup\Command;

use Kussin\OxidAssetCleanup\Service\DuplicateDirectoryCleanupService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteDuplicateDirectoryFilesCommand extends Command
{
    /** @var DuplicateDirectoryCleanupService */
    private $cleanupService;

    public function __construct(DuplicateDirectoryCleanupService $cleanupService)
    {
        $this->cleanupService = $cleanupService;
        parent::__construct('kussin:asset-cleanup:delete-duplicate-directory-files');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Deletes files from a copied directory when the same relative file exists in the original directory.')
            ->addArgument('copy-directory', InputArgument::REQUIRED, 'Directory copy to clean, for example source/out/pictures/_master.')
            ->addOption('original-directory', null, InputOption::VALUE_REQUIRED, 'Original directory to compare against.', 'source/out/pictures/master')
            ->addOption('verify-hash', null, InputOption::VALUE_NONE, 'Require equal SHA-256 hashes in addition to equal relative path and file size.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Actually delete duplicate files from the copy directory.')
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

        $summary = $this->cleanupService->deleteDuplicates(
            (string) $input->getArgument('copy-directory'),
            (string) $input->getOption('original-directory'),
            $dryRun,
            (bool) $input->getOption('verify-hash')
        );

        $output->writeln(sprintf(
            '%s %d duplicate file(s), %d bytes total, %d skipped file(s), %d failure(s).',
            $dryRun ? 'Would delete' : 'Deleted',
            $summary['deleted'],
            $summary['bytes'],
            $summary['skipped'],
            $summary['failed']
        ));
        $output->writeln(sprintf('Log file: %s', $summary['logFile']));

        return $summary['failed'] > 0 ? 1 : 0;
    }
}
