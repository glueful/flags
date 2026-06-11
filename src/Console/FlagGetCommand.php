<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Console;

use Glueful\Console\BaseCommand;
use Glueful\Extensions\Flags\Services\FeatureFlagManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'flags:get', description: 'Show one feature flag')]
final class FlagGetCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('key', InputArgument::REQUIRED, 'Flag key');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $flag = $this->getService(FeatureFlagManager::class)->get((string) $input->getArgument('key'));
        if ($flag === null) {
            $this->error('Feature flag not found.');
            return self::FAILURE;
        }

        $output->writeln(json_encode($flag, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
