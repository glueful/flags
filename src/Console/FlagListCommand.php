<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Console;

use Glueful\Console\BaseCommand;
use Glueful\Extensions\Flags\Repositories\FeatureFlagRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'flags:list', description: 'List feature flags')]
final class FlagListCommand extends BaseCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->table(['Key', 'Enabled', 'Default', 'Status'], array_map(
            static fn($flag): array => [
                $flag->key,
                $flag->enabled ? 'yes' : 'no',
                $flag->defaultValue ? 'true' : 'false',
                $flag->status,
            ],
            $this->getService(FeatureFlagRepository::class)->all()
        ));

        return self::SUCCESS;
    }
}
