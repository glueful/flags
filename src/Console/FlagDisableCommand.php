<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Console;

use Glueful\Console\BaseCommand;
use Glueful\Extensions\Flags\Services\FeatureFlagManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'flags:disable', description: 'Disable a feature flag')]
final class FlagDisableCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('key', InputArgument::REQUIRED, 'Flag key');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->getService(FeatureFlagManager::class)->update((string) $input->getArgument('key'), ['enabled' => false]);
        $this->success('Feature flag disabled.');

        return self::SUCCESS;
    }
}
