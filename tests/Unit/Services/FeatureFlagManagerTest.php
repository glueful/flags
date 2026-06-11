<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Tests\Unit\Services;

use Glueful\Extensions\Flags\Repositories\FeatureFlagAuditRepository;
use Glueful\Extensions\Flags\Repositories\FeatureFlagRepository;
use Glueful\Extensions\Flags\Services\DatabaseFeatureFlagChecker;
use Glueful\Extensions\Flags\Services\FeatureFlagCache;
use Glueful\Extensions\Flags\Services\FeatureFlagEvaluator;
use Glueful\Extensions\Flags\Services\FeatureFlagManager;
use Glueful\Extensions\Flags\Support\FlagContext;
use Glueful\Extensions\Flags\Tests\Support\FlagsTestCase;

final class FeatureFlagManagerTest extends FlagsTestCase
{
    public function testManagerAndBoundCheckerAgreeOnMissingDefault(): void
    {
        $this->setConfig('flags.default', true);
        $flags = new FeatureFlagRepository($this->connection());
        $audits = new FeatureFlagAuditRepository($this->connection());
        $evaluator = new FeatureFlagEvaluator();
        $managerCache = new FeatureFlagCache();
        $checkerCache = new FeatureFlagCache();
        $context = new FlagContext(userUuid: 'user-1');

        $manager = new FeatureFlagManager($flags, $audits, $evaluator, $managerCache, $this->appContext());
        $checker = new DatabaseFeatureFlagChecker($flags, $evaluator, $checkerCache, $this->appContext());

        self::assertTrue($manager->enabled('missing_flag', $context));
        self::assertSame($checker->enabled('missing_flag', $context), $manager->enabled('missing_flag', $context));
    }

    public function testCacheCanStoreNegativeLookup(): void
    {
        $cache = new FeatureFlagCache();
        $cache->put('missing', 'production', null);

        self::assertTrue($cache->has('missing', 'production'));
        self::assertNull($cache->get('missing', 'production'));
    }
}
