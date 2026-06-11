<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Tests\Integration;

use Glueful\Bootstrap\ApplicationContext;
use Glueful\Container\Container;
use Glueful\Container\Definition\AliasDefinition;
use Glueful\Container\Definition\ValueDefinition;
use Glueful\Container\Loader\DefaultServicesLoader;
use Glueful\Database\Connection;
use Glueful\Extensions\Flags\Contracts\FeatureFlagCheckerInterface;
use Glueful\Extensions\Flags\Contracts\FeatureFlagManagerInterface;
use Glueful\Extensions\Flags\FlagsServiceProvider;
use Glueful\Extensions\Flags\Http\RequireFlagsPermission;
use Glueful\Extensions\Flags\Services\DatabaseFeatureFlagChecker;
use Glueful\Extensions\Flags\Services\FeatureFlagManager;
use Glueful\Extensions\Flags\Tests\Support\FlagsTestCase;

final class ServiceProviderWiringTest extends FlagsTestCase
{
    public function testServiceAliasesLiveOnConcreteDefinitions(): void
    {
        $services = FlagsServiceProvider::services();

        self::assertContains(FeatureFlagCheckerInterface::class, $services[DatabaseFeatureFlagChecker::class]['alias']);
        self::assertContains(FeatureFlagManagerInterface::class, $services[FeatureFlagManager::class]['alias']);
        self::assertArrayNotHasKey(FeatureFlagCheckerInterface::class, $services);
        self::assertArrayNotHasKey(FeatureFlagManagerInterface::class, $services);
    }

    public function testServicesLoadThroughRealDefaultServicesLoaderInProductionMode(): void
    {
        $definitions = (new DefaultServicesLoader())->load(
            FlagsServiceProvider::services(),
            FlagsServiceProvider::class,
            prod: true
        );

        self::assertInstanceOf(AliasDefinition::class, $definitions[FeatureFlagCheckerInterface::class] ?? null);
        self::assertInstanceOf(AliasDefinition::class, $definitions[FeatureFlagManagerInterface::class] ?? null);
        self::assertArrayHasKey(RequireFlagsPermission::class, $definitions);
    }

    public function testProviderVersionMatchesComposerManifest(): void
    {
        $raw = (string) file_get_contents(__DIR__ . '/../../composer.json');
        $composer = json_decode($raw, true);
        self::assertIsArray($composer);

        $provider = new FlagsServiceProvider($this->appContext()->getContainer());

        self::assertSame($composer['extra']['glueful']['version'], $provider->getVersion());
        self::assertSame(FlagsServiceProvider::composerVersion(), $provider->getVersion());
    }

    public function testAliasesResolveThroughRealContainer(): void
    {
        $definitions = (new DefaultServicesLoader())->load(
            FlagsServiceProvider::services(),
            FlagsServiceProvider::class,
            prod: true
        );
        $definitions[ApplicationContext::class] = new ValueDefinition(ApplicationContext::class, $this->appContext());
        $definitions[Connection::class] = new ValueDefinition(Connection::class, $this->connection());
        $definitions['database'] = new ValueDefinition('database', $this->connection());
        $container = new Container($definitions);

        self::assertInstanceOf(DatabaseFeatureFlagChecker::class, $container->get(FeatureFlagCheckerInterface::class));
        self::assertInstanceOf(FeatureFlagManager::class, $container->get(FeatureFlagManagerInterface::class));
    }
}
