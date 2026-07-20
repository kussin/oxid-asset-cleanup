<?php

declare(strict_types=1);

namespace Kussin\OxidAssetCleanup\Command;

use InvalidArgumentException;
use Kussin\OxidAssetCleanup\Service\AssetCleanupSettingsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AddProtectedDirectoryCommand extends Command
{
    /** @var AssetCleanupSettingsService */
    private $settingsService;

    public function __construct(AssetCleanupSettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
        parent::__construct('kussin:asset-cleanup:add-protected-directory');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Adds a shop directory to the protected directory setting so status does not report it as unusual.')
            ->addArgument('directory', InputArgument::REQUIRED, 'Shop directory, for example source/out/wh1-2023 or an absolute shop path.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $result = $this->settingsService->addProtectedDirectory((string) $input->getArgument('directory'));
        } catch (InvalidArgumentException $exception) {
            $output->writeln($exception->getMessage());

            return 1;
        }

        $output->writeln(sprintf(
            '%s protected directory: %s',
            $result['added'] ? 'Added' : 'Already configured',
            $result['value']
        ));

        return 0;
    }
}
