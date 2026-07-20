<?php

declare(strict_types=1);

namespace Kussin\OxidAssetCleanup\Command;

use InvalidArgumentException;
use Kussin\OxidAssetCleanup\Service\AssetCleanupSettingsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AddAdditionalPictureCleanupDirectoryCommand extends Command
{
    /** @var AssetCleanupSettingsService */
    private $settingsService;

    public function __construct(AssetCleanupSettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
        parent::__construct('kussin:asset-cleanup:add-picture-cleanup-directory');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Adds a directory below source/out/pictures to the additional master cleanup setting.')
            ->addArgument('directory', InputArgument::REQUIRED, 'Directory below source/out/pictures, for example _master, 0, or z1.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $result = $this->settingsService->addAdditionalPictureCleanupDirectory((string) $input->getArgument('directory'));
        } catch (InvalidArgumentException $exception) {
            $output->writeln($exception->getMessage());

            return 1;
        }

        $output->writeln(sprintf(
            '%s additional picture cleanup directory: %s',
            $result['added'] ? 'Added' : 'Already configured',
            $result['value']
        ));

        return 0;
    }
}
