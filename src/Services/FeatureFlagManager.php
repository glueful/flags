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
use Glueful\Extensions\Flags\Exceptions\FlagNotFoundException;
use Glueful\Extensions\Flags\Exceptions\RuleNotFoundException;
use Glueful\Extensions\Flags\Models\FeatureFlag;
use Glueful\Extensions\Flags\Models\FeatureFlagRule;
use Glueful\Extensions\Flags\Repositories\FeatureFlagAuditRepository;
use Glueful\Extensions\Flags\Repositories\FeatureFlagRepository;
use Glueful\Extensions\Flags\Support\FlagContext;

final class FeatureFlagManager implements FeatureFlagManagerInterface
{
    private FlagPayloadValidator $validator;

    public function __construct(
        private FeatureFlagRepository $flags,
        private FeatureFlagAuditRepository $audits,
        private FeatureFlagEvaluator $evaluator,
        private FeatureFlagCache $cache,
        private ApplicationContext $context,
        private ?EventService $events = null,
        ?FlagPayloadValidator $validator = null,
    ) {
        $this->validator = $validator ?? new FlagPayloadValidator();
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

    public function create(array $data, ?string $actorUuid = null): FeatureFlag
    {
        $data = $this->validator->validateCreate($data);
        $data['created_by'] = $actorUuid;
        if ($this->flags->find((string) $data['key']) !== null) {
            throw new \InvalidArgumentException(
                sprintf('Feature flag "%s" already exists.', (string) $data['key'])
            );
        }

        $flag = $this->flags->create($data);
        $this->audits->record($flag->uuid, 'created', null, $flag->toArray(), $actorUuid);
        $this->events?->dispatch(new FlagCreated($flag->uuid, $flag->key));

        return $flag;
    }

    public function update(string $flag, array $data, ?string $actorUuid = null): FeatureFlag
    {
        $before = $this->get($flag);
        if ($before === null) {
            throw FlagNotFoundException::forKey($flag);
        }

        $updated = $this->flags->update($flag, $this->validator->validatePatch($data));
        $this->cache->clear($flag);
        $this->audits->record($updated->uuid, 'updated', $before->toArray(), $updated->toArray(), $actorUuid);
        $this->events?->dispatch(new FlagUpdated($updated->uuid, $updated->key));
        if ($before->enabled !== $updated->enabled) {
            $this->events?->dispatch(
                $updated->enabled
                    ? new FlagEnabled($updated->uuid, $updated->key)
                    : new FlagDisabled($updated->uuid, $updated->key)
            );
        }

        return $updated;
    }

    public function addRule(string $flag, array $rule, ?string $actorUuid = null): FeatureFlagRule
    {
        $definition = $this->flags->find($flag);
        if ($definition === null) {
            throw FlagNotFoundException::forKey($flag);
        }

        $created = $this->flags->addRule($flag, $this->validator->validateRule($rule));
        $this->cache->clear($flag);
        $this->audits->record($created->flagUuid, 'rule_added', null, $created->toArray(), $actorUuid);
        $this->events?->dispatch(new FlagRuleAdded($created->flagUuid, $definition->key, $created->uuid));

        return $created;
    }

    public function removeRule(string $flag, string $ruleUuid, ?string $actorUuid = null): void
    {
        $definition = $this->flags->find($flag);
        if ($definition === null) {
            throw FlagNotFoundException::forKey($flag);
        }

        $before = $this->flags->findRule($definition->uuid, $ruleUuid);
        if ($before === null || !$before->enabled) {
            throw RuleNotFoundException::forUuid($definition->key, $ruleUuid);
        }

        $this->flags->removeRule($flag, $ruleUuid);
        $this->cache->clear($flag);
        $after = $this->flags->findRule($definition->uuid, $ruleUuid);
        $this->audits->record($definition->uuid, 'rule_removed', $before->toArray(), $after?->toArray(), $actorUuid);
        $this->events?->dispatch(new FlagRuleRemoved($definition->uuid, $definition->key, $ruleUuid));
    }
}
