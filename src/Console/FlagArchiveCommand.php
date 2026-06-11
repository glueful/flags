<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Console;

use Glueful\Console\BaseCommand;
use Glueful\Extensions\Flags\Services\FeatureFlagManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'flags:archive', description: 'Archive a feature flag')]
final class FlagArchiveCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('key', InputArgument::REQUIRED, 'Flag key');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->getService(FeatureFlagManager::class)->update(
            (string) $input->getArgument('key'),
            ['status' => 'archived', 'enabled' => false]
        );
        $this->success('Feature flag archived.');

        return self::SUCCESS;
    }
}
