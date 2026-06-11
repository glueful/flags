<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Console;

use Glueful\Console\BaseCommand;
use Glueful\Extensions\Flags\Services\FeatureFlagManager;
use Glueful\Extensions\Flags\Support\FlagContextFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'flags:evaluate', description: 'Evaluate a feature flag')]
final class FlagEvaluateCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('key', InputArgument::REQUIRED, 'Flag key');
        $this->addOption('user', null, InputOption::VALUE_REQUIRED, 'User UUID');
        $this->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'Tenant UUID');
        $this->addOption('env', null, InputOption::VALUE_REQUIRED, 'Environment');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $enabled = $this->getService(FeatureFlagManager::class)->enabled(
            (string) $input->getArgument('key'),
            (new FlagContextFactory())->fromArray([
                'user' => $input->getOption('user'),
                'tenant' => $input->getOption('tenant'),
                'environment' => $input->getOption('env'),
            ])
        );
        $output->writeln($enabled ? 'true' : 'false');

        return self::SUCCESS;
    }
}
