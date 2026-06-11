<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Services;

use Glueful\Bootstrap\ApplicationContext;
use Glueful\Extensions\Flags\Contracts\FeatureFlagCheckerInterface;
use Glueful\Extensions\Flags\Repositories\FeatureFlagRepository;
use Glueful\Extensions\Flags\Support\FlagContext;

final class DatabaseFeatureFlagChecker implements FeatureFlagCheckerInterface
{
    public function __construct(
        private FeatureFlagRepository $flags,
        private FeatureFlagEvaluator $evaluator,
        private FeatureFlagCache $cache,
        private ApplicationContext $context,
    ) {
    }

    public function enabled(string $flag, FlagContext $context): bool
    {
        if (!$this->cache->has($flag, $context->environment)) {
            $definition = $this->flags->find($flag);
            $this->cache->put($flag, $context->environment, $definition);
        }

        $definition = $this->cache->get($flag, $context->environment);

        return $this->evaluator->evaluate(
            $definition,
            $context,
            (bool) \config($this->context, 'flags.default', false)
        );
    }
}
