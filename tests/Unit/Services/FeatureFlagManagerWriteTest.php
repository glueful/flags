<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Tests\Unit\Services;

use Glueful\Events\EventDispatcher;
use Glueful\Events\EventService;
use Glueful\Events\ListenerProvider;
use Glueful\Extensions\Flags\Events\FlagRuleRemoved;
use Glueful\Extensions\Flags\Exceptions\FlagNotFoundException;
use Glueful\Extensions\Flags\Exceptions\RuleNotFoundException;
use Glueful\Extensions\Flags\Repositories\FeatureFlagAuditRepository;
use Glueful\Extensions\Flags\Repositories\FeatureFlagRepository;
use Glueful\Extensions\Flags\Services\FeatureFlagCache;
use Glueful\Extensions\Flags\Services\FeatureFlagEvaluator;
use Glueful\Extensions\Flags\Services\FeatureFlagManager;
use Glueful\Extensions\Flags\Tests\Support\FlagsTestCase;
use InvalidArgumentException;

final class FeatureFlagManagerWriteTest extends FlagsTestCase
{
    private FeatureFlagManager $manager;

    /** @var list<object> */
    private array $events = [];

    protected function setUp(): void
    {
        parent::setUp();

        $provider = new ListenerProvider();
        $eventService = new EventService(new EventDispatcher($provider), $provider);
        $eventService->addListener(FlagRuleRemoved::class, function (object $event): void {
            $this->events[] = $event;
        });

        $this->manager = new FeatureFlagManager(
            new FeatureFlagRepository($this->connection()),
            new FeatureFlagAuditRepository($this->connection()),
            new FeatureFlagEvaluator(),
            new FeatureFlagCache(),
            $this->appContext(),
            $eventService
        );
    }

    public function testCreateRecordsFullFlagSnapshot(): void
    {
        $flag = $this->manager->create(
            ['key' => 'new_editor', 'name' => 'New editor', 'enabled' => true],
            'user-1'
        );

        $audit = $this->latestAudit($flag->uuid);
        self::assertSame('created', $audit['action']);
        self::assertSame('user-1', $audit['actor_uuid']);
        self::assertNull($audit['before']);

        $after = $this->decode($audit['after']);
        self::assertSame('new_editor', $after['key']);
        self::assertSame('New editor', $after['name']);
        self::assertTrue($after['enabled']);
        self::assertFalse($after['default_value']);
        self::assertSame('active', $after['status']);
        self::assertSame('user-1', $after['created_by']);
        self::assertSame([], $after['rules']);
    }

    public function testCreateIgnoresClientSuppliedCreatedBy(): void
    {
        $flag = $this->manager->create(['key' => 'new_editor', 'created_by' => 'spoofed-user'], 'real-user');

        self::assertSame('real-user', $flag->createdBy);
    }

    public function testCreateRejectsDuplicateKey(): void
    {
        $this->manager->create(['key' => 'new_editor']);

        $this->expectException(InvalidArgumentException::class);
        $this->manager->create(['key' => 'new_editor']);
    }

    public function testUpdateRecordsFullBeforeAndAfterSnapshots(): void
    {
        $flag = $this->manager->create(['key' => 'new_editor', 'name' => 'New editor']);
        $this->manager->update('new_editor', ['name' => 'Renamed', 'enabled' => true], 'user-2');

        $audit = $this->latestAudit($flag->uuid);
        self::assertSame('updated', $audit['action']);
        self::assertSame('user-2', $audit['actor_uuid']);

        $before = $this->decode($audit['before']);
        $after = $this->decode($audit['after']);
        self::assertSame('New editor', $before['name']);
        self::assertFalse($before['enabled']);
        self::assertSame('Renamed', $after['name']);
        self::assertTrue($after['enabled']);
        self::assertSame('new_editor', $after['key']);
    }

    public function testUpdateUnknownFlagThrowsNotFound(): void
    {
        $this->expectException(FlagNotFoundException::class);
        $this->manager->update('missing_flag', ['enabled' => true]);
    }

    public function testAddRuleRecordsFullRuleSnapshot(): void
    {
        $flag = $this->manager->create(['key' => 'new_editor']);
        $rule = $this->manager->addRule('new_editor', ['type' => 'user', 'value' => ['user-1']], 'user-3');

        $audit = $this->latestAudit($flag->uuid);
        self::assertSame('rule_added', $audit['action']);
        self::assertSame('user-3', $audit['actor_uuid']);
        self::assertNull($audit['before']);

        $after = $this->decode($audit['after']);
        self::assertSame($rule->uuid, $after['uuid']);
        self::assertSame($flag->uuid, $after['flag_uuid']);
        self::assertSame('user', $after['type']);
        self::assertSame('in', $after['operator']);
        self::assertSame(['user-1'], $after['value']);
        self::assertTrue($after['enabled']);
    }

    public function testAddRuleUnknownFlagThrowsNotFound(): void
    {
        $this->expectException(FlagNotFoundException::class);
        $this->manager->addRule('missing_flag', ['type' => 'user', 'value' => ['user-1']]);
    }

    public function testRemoveRuleRecordsSnapshotsAndDispatchesEvent(): void
    {
        $flag = $this->manager->create(['key' => 'new_editor']);
        $rule = $this->manager->addRule('new_editor', ['type' => 'user', 'value' => ['user-1']]);

        $this->manager->removeRule('new_editor', $rule->uuid, 'user-4');

        $audit = $this->latestAudit($flag->uuid);
        self::assertSame('rule_removed', $audit['action']);
        self::assertSame('user-4', $audit['actor_uuid']);

        $before = $this->decode($audit['before']);
        $after = $this->decode($audit['after']);
        self::assertSame($rule->uuid, $before['uuid']);
        self::assertSame('user', $before['type']);
        self::assertTrue($before['enabled']);
        self::assertSame($rule->uuid, $after['uuid']);
        self::assertFalse($after['enabled']);

        self::assertCount(1, $this->events);
        $event = $this->events[0];
        self::assertInstanceOf(FlagRuleRemoved::class, $event);
        self::assertSame($rule->uuid, $event->ruleUuid);
        self::assertSame('new_editor', $event->key);
    }

    public function testRemoveRuleUnknownUuidThrowsAndStaysSilent(): void
    {
        $flag = $this->manager->create(['key' => 'new_editor']);
        $auditCountBefore = count($this->auditsFor($flag->uuid));

        try {
            $this->manager->removeRule('new_editor', 'missing-rule');
            self::fail('Expected RuleNotFoundException');
        } catch (RuleNotFoundException) {
            // expected
        }

        self::assertCount($auditCountBefore, $this->auditsFor($flag->uuid));
        self::assertSame([], $this->events);
    }

    public function testRemoveRuleTwiceThrowsNotFound(): void
    {
        $this->manager->create(['key' => 'new_editor']);
        $rule = $this->manager->addRule('new_editor', ['type' => 'user', 'value' => ['user-1']]);
        $this->manager->removeRule('new_editor', $rule->uuid);

        $this->expectException(RuleNotFoundException::class);
        $this->manager->removeRule('new_editor', $rule->uuid);
    }

    public function testRemoveRuleUnknownFlagThrowsNotFound(): void
    {
        $this->expectException(FlagNotFoundException::class);
        $this->manager->removeRule('missing_flag', 'rule-1');
    }

    /** @return array<string,mixed> */
    private function latestAudit(string $flagUuid): array
    {
        $rows = $this->auditsFor($flagUuid);
        self::assertNotEmpty($rows);

        return $rows[0];
    }

    /** @return list<array<string,mixed>> */
    private function auditsFor(string $flagUuid): array
    {
        return $this->connection()
            ->table('feature_flag_audits')
            ->where('flag_uuid', '=', $flagUuid)
            ->orderBy('id', 'DESC')
            ->get();
    }

    /** @return array<string,mixed> */
    private function decode(mixed $json): array
    {
        self::assertIsString($json);
        $decoded = json_decode($json, true);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
