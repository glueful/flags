<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Tests\Unit\Services;

use Glueful\Extensions\Flags\Services\FlagPayloadValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FlagPayloadValidatorTest extends TestCase
{
    private FlagPayloadValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new FlagPayloadValidator();
    }

    // -- create --------------------------------------------------------

    public function testCreateNormalizesValidPayload(): void
    {
        $validated = $this->validator->validateCreate([
            'key' => 'new_editor',
            'name' => 'New editor',
            'description' => 'Editor rollout',
            'enabled' => true,
            'default_value' => false,
            'status' => 'active',
            'created_by' => 'user-1',
        ]);

        self::assertSame('new_editor', $validated['key']);
        self::assertSame('New editor', $validated['name']);
        self::assertSame('Editor rollout', $validated['description']);
        self::assertTrue($validated['enabled']);
        self::assertFalse($validated['default_value']);
        self::assertSame('active', $validated['status']);
        self::assertSame('user-1', $validated['created_by']);
    }

    public function testCreateAppliesDefaults(): void
    {
        $validated = $this->validator->validateCreate(['key' => 'new_editor']);

        self::assertSame('new_editor', $validated['name']);
        self::assertNull($validated['description']);
        self::assertFalse($validated['enabled']);
        self::assertFalse($validated['default_value']);
        self::assertSame('active', $validated['status']);
        self::assertNull($validated['created_by']);
    }

    public function testCreateRejectsMissingKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateCreate(['name' => 'No key']);
    }

    public function testCreateRejectsBadKeyCharset(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateCreate(['key' => 'New Editor!']);
    }

    public function testCreateRejectsOverlongKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateCreate(['key' => str_repeat('a', 161)]);
    }

    public function testCreateRejectsInvalidStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateCreate(['key' => 'new_editor', 'status' => 'paused']);
    }

    public function testCreateRejectsNonBooleanEnabled(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateCreate(['key' => 'new_editor', 'enabled' => 'yes']);
    }

    public function testCreateRejectsNonStringName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateCreate(['key' => 'new_editor', 'name' => ['nope']]);
    }

    // -- patch ---------------------------------------------------------

    public function testPatchRejectsKeyChange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validatePatch(['key' => 'renamed']);
    }

    public function testPatchDropsUnknownFields(): void
    {
        $validated = $this->validator->validatePatch(['uuid' => 'hax', 'name' => 'Renamed']);

        self::assertSame(['name' => 'Renamed'], $validated);
    }

    public function testPatchValidatesStatusAndBooleans(): void
    {
        $validated = $this->validator->validatePatch([
            'status' => 'archived',
            'enabled' => false,
            'default_value' => true,
            'description' => null,
        ]);

        self::assertSame('archived', $validated['status']);
        self::assertFalse($validated['enabled']);
        self::assertTrue($validated['default_value']);
        self::assertNull($validated['description']);
    }

    public function testPatchRejectsInvalidStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validatePatch(['status' => 'paused']);
    }

    public function testPatchRejectsNonBooleanDefaultValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validatePatch(['default_value' => 'maybe']);
    }

    // -- rules ---------------------------------------------------------

    public function testRuleAppliesDefaults(): void
    {
        $validated = $this->validator->validateRule(['type' => 'user', 'value' => ['user-1']]);

        self::assertSame('user', $validated['type']);
        self::assertSame('in', $validated['operator']);
        self::assertSame(0, $validated['priority']);
        self::assertTrue($validated['enabled']);
        self::assertSame(['user-1'], $validated['value']);
    }

    public function testRuleRejectsMissingType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateRule(['value' => ['user-1']]);
    }

    public function testRuleRejectsUnknownType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateRule(['type' => 'geo', 'value' => ['gh']]);
    }

    public function testRuleRejectsUnknownOperator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateRule(['type' => 'user', 'operator' => 'matches', 'value' => ['user-1']]);
    }

    public function testRuleRejectsNonIntegerPriority(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateRule(['type' => 'user', 'priority' => 'first', 'value' => ['user-1']]);
    }

    public function testPercentageRuleRequiresPercentage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateRule(['type' => 'percentage']);
    }

    public function testRuleRejectsPercentageBelowZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateRule(['type' => 'percentage', 'percentage' => -1]);
    }

    public function testRuleRejectsPercentageAboveHundred(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateRule(['type' => 'percentage', 'percentage' => 101]);
    }

    public function testRuleRejectsUnknownSubject(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateRule(['type' => 'percentage', 'percentage' => 10, 'subject' => 'device']);
    }

    public function testCustomSubjectRequiresValueAttribute(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateRule(['type' => 'percentage', 'percentage' => 10, 'subject' => 'custom']);
    }

    public function testCustomSubjectAcceptsValueAttribute(): void
    {
        $validated = $this->validator->validateRule([
            'type' => 'percentage',
            'percentage' => 10,
            'subject' => 'custom',
            'value' => ['attribute' => 'device_id'],
        ]);

        self::assertSame('custom', $validated['subject']);
        self::assertSame(10, $validated['percentage']);
    }

    public function testAttributeRuleRequiresValueKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateRule(['type' => 'attribute', 'value' => ['value' => 'beta']]);
    }

    public function testAttributeRuleAcceptsKeyValueShape(): void
    {
        $validated = $this->validator->validateRule([
            'type' => 'attribute',
            'value' => ['key' => 'plan', 'value' => 'beta'],
        ]);

        self::assertSame(['key' => 'plan', 'value' => 'beta'], $validated['value']);
    }

    public function testRuleRejectsNonBooleanEnabled(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateRule(['type' => 'user', 'enabled' => 'yes', 'value' => ['user-1']]);
    }
}
