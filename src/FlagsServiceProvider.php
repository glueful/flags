<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags;

use Glueful\Bootstrap\ApplicationContext;
use Glueful\Database\Migrations\MigrationPriority;
use Glueful\Events\EventService;
use Glueful\Extensions\Flags\Console\FlagArchiveCommand;
use Glueful\Extensions\Flags\Console\FlagDisableCommand;
use Glueful\Extensions\Flags\Console\FlagEnableCommand;
use Glueful\Extensions\Flags\Console\FlagEvaluateCommand;
use Glueful\Extensions\Flags\Console\FlagGetCommand;
use Glueful\Extensions\Flags\Console\FlagListCommand;
use Glueful\Extensions\Flags\Contracts\FeatureFlagCheckerInterface;
use Glueful\Extensions\Flags\Contracts\FeatureFlagManagerInterface;
use Glueful\Extensions\Flags\Http\Controllers\FeatureFlagController;
use Glueful\Extensions\Flags\Http\Controllers\FeatureFlagEvaluateController;
use Glueful\Extensions\Flags\Http\Controllers\FeatureFlagRuleController;
use Glueful\Extensions\Flags\Http\RequireFlagsPermission;
use Glueful\Extensions\Flags\Repositories\FeatureFlagAuditRepository;
use Glueful\Extensions\Flags\Repositories\FeatureFlagRepository;
use Glueful\Extensions\Flags\Services\DatabaseFeatureFlagChecker;
use Glueful\Extensions\Flags\Services\FeatureFlagCache;
use Glueful\Extensions\Flags\Services\FeatureFlagEvaluator;
use Glueful\Extensions\Flags\Services\FeatureFlagManager;
use Glueful\Extensions\Flags\Services\FlagPayloadValidator;
use Glueful\Extensions\Flags\Support\FlagContextFactory;
use Glueful\Extensions\Flags\Support\RuleMatcher;
use Glueful\Extensions\ServiceProvider;
use Glueful\Permissions\Catalog\Permission;
use Psr\Container\ContainerInterface;

final class FlagsServiceProvider extends ServiceProvider
{
    private static ?string $cachedVersion = null;

    /**
     * Reads the extension version from composer.json's extra.glueful.version (cached).
     */
    public static function composerVersion(): string
    {
        if (self::$cachedVersion === null) {
            $raw = file_get_contents(__DIR__ . '/../composer.json');
            $composer = is_string($raw) ? json_decode($raw, true) : null;
            $version = is_array($composer) ? ($composer['extra']['glueful']['version'] ?? null) : null;
            self::$cachedVersion = is_string($version) ? $version : '0.0.0';
        }

        return self::$cachedVersion;
    }

    /** @return array<string,mixed> */
    public static function services(): array
    {
        return [
            FeatureFlagRepository::class => self::autowired(FeatureFlagRepository::class),
            FeatureFlagAuditRepository::class => self::autowired(FeatureFlagAuditRepository::class),
            FlagPayloadValidator::class => self::autowired(FlagPayloadValidator::class),
            RuleMatcher::class => self::autowired(RuleMatcher::class),
            FeatureFlagEvaluator::class => self::autowired(FeatureFlagEvaluator::class),
            FeatureFlagCache::class => self::autowired(FeatureFlagCache::class),
            FlagContextFactory::class => self::autowired(FlagContextFactory::class),
            DatabaseFeatureFlagChecker::class => self::autowired(
                DatabaseFeatureFlagChecker::class,
                aliases: [FeatureFlagCheckerInterface::class]
            ),
            FeatureFlagManager::class => [
                'factory' => [self::class, 'makeFeatureFlagManager'],
                'shared' => true,
                'alias' => [FeatureFlagManagerInterface::class],
            ],
            RequireFlagsPermission::class => [
                'class' => RequireFlagsPermission::class,
                'shared' => true,
                'autowire' => true,
                'alias' => ['flags_permission'],
            ],
            FeatureFlagController::class => self::autowired(FeatureFlagController::class),
            FeatureFlagRuleController::class => self::autowired(FeatureFlagRuleController::class),
            FeatureFlagEvaluateController::class => self::autowired(FeatureFlagEvaluateController::class),
            FlagListCommand::class => self::autowired(FlagListCommand::class),
            FlagGetCommand::class => self::autowired(FlagGetCommand::class),
            FlagEnableCommand::class => self::autowired(FlagEnableCommand::class),
            FlagDisableCommand::class => self::autowired(FlagDisableCommand::class),
            FlagArchiveCommand::class => self::autowired(FlagArchiveCommand::class),
            FlagEvaluateCommand::class => self::autowired(FlagEvaluateCommand::class),
        ];
    }

    public static function makeFeatureFlagManager(ContainerInterface $c): FeatureFlagManager
    {
        return new FeatureFlagManager(
            $c->get(FeatureFlagRepository::class),
            $c->get(FeatureFlagAuditRepository::class),
            $c->get(FeatureFlagEvaluator::class),
            $c->get(FeatureFlagCache::class),
            $c->get(ApplicationContext::class),
            $c->has(EventService::class) ? $c->get(EventService::class) : null,
            $c->has(FlagPayloadValidator::class) ? $c->get(FlagPayloadValidator::class) : null,
        );
    }

    /**
     * @param list<string> $aliases
     * @return array{class:class-string,shared:bool,autowire:bool,alias?:list<string>}
     */
    private static function autowired(string $class, bool $shared = true, array $aliases = []): array
    {
        $definition = ['class' => $class, 'shared' => $shared, 'autowire' => true];
        if ($aliases !== []) {
            $definition['alias'] = $aliases;
        }

        return $definition;
    }

    public function getName(): string
    {
        return 'Flags';
    }

    public function getVersion(): string
    {
        return self::composerVersion();
    }

    public function getDescription(): string
    {
        return 'Feature flags and rollout controls for Glueful apps.';
    }

    public function register(ApplicationContext $context): void
    {
        $this->mergeConfig('flags', require __DIR__ . '/../config/flags.php');
        $this->loadMigrationsFrom(__DIR__ . '/../migrations', MigrationPriority::DEFAULT, 'glueful/flags');
    }

    public function boot(ApplicationContext $context): void
    {
        $this->discoverCommands('Glueful\\Extensions\\Flags\\Console', __DIR__ . '/Console');
        if ((bool) \config($context, 'flags.routes_enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/routes.php');
        }
    }

    public function permissions(): array
    {
        return [
            Permission::define('flags.view')
                ->label('View feature flags')
                ->category('Flags')
                ->resource('flags')
                ->managedBy('glueful/flags'),
            Permission::define('flags.manage')
                ->label('Manage feature flags')
                ->category('Flags')
                ->resource('flags')
                ->managedBy('glueful/flags'),
            Permission::define('flags.evaluate')
                ->label('Evaluate feature flags')
                ->category('Flags')
                ->resource('flags')
                ->managedBy('glueful/flags'),
        ];
    }
}
