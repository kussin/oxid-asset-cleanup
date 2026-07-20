<?php

declare(strict_types=1);

namespace Kussin\OxidAssetCleanup\Command;

use Kussin\OxidAssetCleanup\Service\ReferencedPictureCleanupService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractReferencedPicturesCommand extends Command
{
    /** @var ReferencedPictureCleanupService */
    protected $cleanupService;

    /** @var string */
    protected $target;

    public function __construct(ReferencedPictureCleanupService $cleanupService, string $name, string $target)
    {
        $this->cleanupService = $cleanupService;
        $this->target = $target;
        parent::__construct($name);
    }

    protected function configureDeleteOptions(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Actually delete orphaned files.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Log and print what would be deleted.');
    }

    protected function executeScan(OutputInterface $output)
    {
        $files = $this->cleanupService->findOrphanedPictures($this->target);
        $bytes = 0;

        foreach ($files as $file) {
            $bytes += $file['size'];
            $output->writeln(sprintf('%s (%d bytes)', $file['relativePath'], $file['size']));
        }

        $output->writeln(sprintf(
            'Found %d orphaned %s file(s), %d bytes total.',
            count($files),
            $this->cleanupService->getTargetLabel($this->target),
            $bytes
        ));

        return 0;
    }

    protected function executeDelete(InputInterface $input, OutputInterface $output)
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $force = (bool) $input->getOption('force');

        if (!$dryRun && !$force) {
            $output->writeln('Refusing to delete files without --force. Use --dry-run for a simulated run.');

            return 1;
        }

        $summary = $this->cleanupService->deleteOrphanedPictures($this->target, $dryRun);

        $output->writeln(sprintf(
            '%s %d orphaned %s file(s), %d bytes total, %d failure(s), %d missing directories, %d empty directories.',
            $dryRun ? 'Would delete' : 'Deleted',
            $summary['deleted'],
            $this->cleanupService->getTargetLabel($this->target),
            $summary['bytes'],
            $summary['failed'],
            $summary['missing'],
            $summary['empty']
        ));
        $output->writeln(sprintf('Log file: %s', $summary['logFile']));

        return $summary['failed'] > 0 ? 1 : 0;
    }
}
