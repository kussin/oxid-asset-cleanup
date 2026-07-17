<?php

declare(strict_types=1);

namespace Kussin\OxidAssetCleanup\Command;

use Kussin\OxidAssetCleanup\Service\ImageCacheCleanupService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FlushImageCacheCommand extends Command
{
    /** @var ImageCacheCleanupService */
    private $cleanupService;

    public function __construct(ImageCacheCleanupService $cleanupService)
    {
        $this->cleanupService = $cleanupService;
        parent::__construct('kussin:asset-cleanup:flush-image-cache');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Deletes generated OXID image cache files.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Actually delete image cache files.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Log and print what would be deleted.')
            ->addOption('delete-empty-directories', null, InputOption::VALUE_NONE, 'Delete empty directories after deleting image cache files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $force = (bool) $input->getOption('force');
        $deleteEmptyDirectories = (bool) $input->getOption('delete-empty-directories');

        if (!$dryRun && !$force) {
            $output->writeln('Refusing to delete files without --force. Use --dry-run for a simulated run.');

            return 1;
        }

        $summary = $this->cleanupService->flushImageCache($dryRun, $deleteEmptyDirectories);
        $output->writeln(sprintf(
            '%s %d image cache file(s), %d bytes total, %d failure(s), %d missing directories, %d empty directories.',
            $dryRun ? 'Would delete' : 'Deleted',
            $summary['deleted'],
            $summary['bytes'],
            $summary['failed'],
            $summary['missing'],
            $summary['empty']
        ));
        $output->writeln(sprintf('Log file: %s', $summary['logFile']));

        return $summary['failed'] > 0 ? 1 : 0;
    }
}
