<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Tests\Support;

use Glueful\Bootstrap\ApplicationContext;
use Glueful\Database\Connection;
use Glueful\Extensions\Flags\Database\Migrations\CreateFeatureFlagsTables;
use Glueful\Helpers\Utils;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

abstract class FlagsTestCase extends TestCase
{
    protected ApplicationContext $context;
    protected Connection $connection;

    /** @var array<string,mixed> */
    protected array $bindings = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = new Connection([
            'engine' => 'sqlite',
            'sqlite' => ['primary' => ':memory:'],
            'pooling' => ['enabled' => false],
        ]);
        (new CreateFeatureFlagsTables())->up($this->connection->getSchemaBuilder());

        $connection = $this->connection;
        $bindings = &$this->bindings;
        $container = new class ($connection, $bindings) implements ContainerInterface {
            /** @param array<string,mixed> $bindings */
            public function __construct(private Connection $connection, private array &$bindings)
            {
            }

            public function get(string $id): mixed
            {
                if ($id === 'database' || $id === Connection::class) {
                    return $this->connection;
                }
                if (array_key_exists($id, $this->bindings)) {
                    return $this->bindings[$id];
                }
                throw new \RuntimeException("Unknown service: {$id}");
            }

            public function has(string $id): bool
            {
                return $id === 'database' || $id === Connection::class || array_key_exists($id, $this->bindings);
            }
        };

        $this->context = new ApplicationContext(basePath: sys_get_temp_dir(), environment: 'testing');
        $this->context->setContainer($container);
        $this->context->mergeConfigDefaults('flags', require __DIR__ . '/../../config/flags.php');
    }

    protected function appContext(): ApplicationContext
    {
        return $this->context;
    }

    protected function connection(): Connection
    {
        return $this->connection;
    }

    protected function bind(string $id, mixed $service): void
    {
        $this->bindings[$id] = $service;
    }

    /** @param array<string,mixed> $overrides */
    protected function seedFlag(array $overrides = []): array
    {
        $row = array_merge([
            'uuid' => Utils::generateNanoID(12),
            'key' => 'new_editor',
            'name' => 'New editor',
            'enabled' => true,
            'default_value' => false,
            'status' => 'active',
        ], $overrides);

        $this->connection->table('feature_flags')->insert($row);

        return $row;
    }
}
