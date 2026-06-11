<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Services;

use Glueful\Bootstrap\ApplicationContext;
use Glueful\Events\EventService;
use Glueful\Extensions\Flags\Contracts\FeatureFlagManagerInterface;
use Glueful\Extensions\Flags\Events\FlagCreated;
use Glueful\Extensions\Flags\Events\FlagDisabled;
use Glueful\Extensions\Flags\Events\FlagEnabled;
use Glueful\Extensions\Flags\Events\FlagRuleAdded;
use Glueful\Extensions\Flags\Events\FlagRuleRemoved;
use Glueful\Extensions\Flags\Events\FlagUpdated;
use Glueful\Extensions\Flags\Models\FeatureFlag;
use Glueful\Extensions\Flags\Models\FeatureFlagRule;
use Glueful\Extensions\Flags\Repositories\FeatureFlagAuditRepository;
use Glueful\Extensions\Flags\Repositories\FeatureFlagRepository;
use Glueful\Extensions\Flags\Support\FlagContext;

final class FeatureFlagManager implements FeatureFlagManagerInterface
{
    public function __construct(
        private FeatureFlagRepository $flags,
        private FeatureFlagAuditRepository $audits,
        private FeatureFlagEvaluator $evaluator,
        private FeatureFlagCache $cache,
        private ApplicationContext $context,
        private ?EventService $events = null,
    ) {
    }

    public function enabled(string $flag, FlagContext $context): bool
    {
        if (!$this->cache->has($flag, $context->environment)) {
            $this->cache->put($flag, $context->environment, $this->flags->find($flag));
        }

        return $this->evaluator->evaluate(
            $this->cache->get($flag, $context->environment),
            $context,
            (bool) \config($this->context, 'flags.default', false)
        );
    }

    public function get(string $flag): ?FeatureFlag
    {
        return $this->flags->find($flag);
    }

    public function create(array $data): FeatureFlag
    {
        $flag = $this->flags->create($data);
        $this->audits->record($flag->uuid, 'created', null, ['key' => $flag->key]);
        $this->events?->dispatch(new FlagCreated($flag->uuid, $flag->key));

        return $flag;
    }

    public function update(string $flag, array $data): FeatureFlag
    {
        $before = $this->get($flag);
        $updated = $this->flags->update($flag, $data);
        $this->cache->clear($flag);
        $this->audits->record(
            $updated->uuid,
            'updated',
            $before !== null ? ['key' => $before->key] : null,
            ['key' => $updated->key]
        );
        $this->events?->dispatch(new FlagUpdated($updated->uuid, $updated->key));
        if ($before !== null && $before->enabled !== $updated->enabled) {
            $this->events?->dispatch(
                $updated->enabled
                    ? new FlagEnabled($updated->uuid, $updated->key)
                    : new FlagDisabled($updated->uuid, $updated->key)
            );
        }

        return $updated;
    }

    public function addRule(string $flag, array $rule): FeatureFlagRule
    {
        $created = $this->flags->addRule($flag, $rule);
        $this->cache->clear($flag);
        $definition = $this->flags->find($flag);
        $this->audits->record($created->flagUuid, 'rule_added', null, ['rule' => $created->uuid]);
        $this->events?->dispatch(new FlagRuleAdded($created->flagUuid, $definition?->key ?? $flag, $created->uuid));

        return $created;
    }

    public function removeRule(string $flag, string $ruleUuid): void
    {
        $definition = $this->flags->find($flag);
        $this->flags->removeRule($flag, $ruleUuid);
        $this->cache->clear($flag);
        if ($definition !== null) {
            $this->audits->record($definition->uuid, 'rule_removed', ['rule' => $ruleUuid], null);
            $this->events?->dispatch(new FlagRuleRemoved($definition->uuid, $definition->key, $ruleUuid));
        }
    }
}
