<?php

declare(strict_types=1);

namespace Kussin\OxidAssetCleanup\Command;

use Kussin\OxidAssetCleanup\Service\StatusReportService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends Command
{
    /** @var StatusReportService */
    private $statusReportService;

    public function __construct(StatusReportService $statusReportService)
    {
        $this->statusReportService = $statusReportService;
        parent::__construct('kussin:asset-cleanup:status');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Shows disk usage, cleanable directory sizes, unusual directories, and large files.')
            ->addOption('min-size', null, InputOption::VALUE_REQUIRED, 'Minimum large-file size. Supports B, KB, MB, GB.', '5MB')
            ->addOption('path', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Directory to scan for large files. Can be used multiple times.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $minimumSize = $this->statusReportService->parseSize((string) $input->getOption('min-size'));
        $paths = $input->getOption('path');
        $report = $this->statusReportService->createReport($minimumSize, is_array($paths) ? $paths : []);

        $disk = $report['disk'];
        $output->writeln('Disk usage');
        $output->writeln(sprintf(
            '- %s: total=%s used=%s free=%s usedPercent=%s',
            $disk['path'],
            $this->statusReportService->formatBytes($disk['total']),
            $this->statusReportService->formatBytes($disk['used']),
            $this->statusReportService->formatBytes($disk['free']),
            $disk['usedPercent']
        ));

        $output->writeln('');
        $output->writeln('Cleanable directories');
        foreach ($report['cleanableDirectories'] as $directory) {
            $output->writeln(sprintf(
                '- %s: %s, files=%d, exists=%s',
                $directory['label'],
                $this->statusReportService->formatBytes($directory['bytes']),
                $directory['files'],
                $directory['exists'] ? 'yes' : 'no'
            ));
            $output->writeln(sprintf('  %s', $directory['path']));
        }

        $output->writeln('');
        $output->writeln(sprintf('Large files >= %s', $this->statusReportService->formatBytes($minimumSize)));
        foreach ($report['largeFiles'] as $file) {
            $output->writeln(sprintf('- %s %s', $this->statusReportService->formatBytes($file['bytes']), $file['path']));
        }

        if (!$report['largeFiles']) {
            $output->writeln('- none found');
        }

        $output->writeln('');
        $output->writeln('Unusual directories');
        foreach ($report['unusualDirectories'] as $directory) {
            $output->writeln(sprintf('- %s: %s', $directory['area'], $directory['path']));
        }

        if (!$report['unusualDirectories']) {
            $output->writeln('- none found');
        }

        return 0;
    }
}
