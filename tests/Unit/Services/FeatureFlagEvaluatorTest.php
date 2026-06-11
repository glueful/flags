<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Tests\Unit\Services;

use Glueful\Extensions\Flags\Models\FeatureFlag;
use Glueful\Extensions\Flags\Models\FeatureFlagRule;
use Glueful\Extensions\Flags\Services\FeatureFlagEvaluator;
use Glueful\Extensions\Flags\Support\FlagContext;
use PHPUnit\Framework\TestCase;

final class FeatureFlagEvaluatorTest extends TestCase
{
    public function testDisabledFlagsFailClosedAndRulesCanEnable(): void
    {
        $evaluator = new FeatureFlagEvaluator();
        $context = new FlagContext(userUuid: 'user-1', roles: ['admin']);

        self::assertFalse($evaluator->evaluate(null, $context));
        self::assertFalse($evaluator->evaluate(new FeatureFlag('f1', 'flag', 'Flag', null, false, true, 'active'), $context));

        $flag = new FeatureFlag('f1', 'flag', 'Flag', null, true, false, 'active', rules: [
            new FeatureFlagRule('r1', 'f1', 1, 'role', 'in', ['admin']),
        ]);
        self::assertTrue($evaluator->evaluate($flag, $context));
    }

    public function testPercentageRolloutIsStable(): void
    {
        $flag = new FeatureFlag('f1', 'flag', 'Flag', null, true, false, 'active', rules: [
            new FeatureFlagRule('r1', 'f1', 1, 'percentage', 'in', null, 50, 'user'),
        ]);
        $context = new FlagContext(userUuid: 'user-1');
        $evaluator = new FeatureFlagEvaluator();

        self::assertSame($evaluator->evaluate($flag, $context), $evaluator->evaluate($flag, $context));
    }

    public function testArchivedFlagsFailClosedEvenWhenMissingDefaultAllows(): void
    {
        $flag = new FeatureFlag('f1', 'flag', 'Flag', null, true, true, 'archived');

        self::assertFalse((new FeatureFlagEvaluator())->evaluate($flag, new FlagContext(userUuid: 'user-1'), true));
    }
}
