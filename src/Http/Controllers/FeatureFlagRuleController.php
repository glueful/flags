<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Http\Controllers;

use Glueful\Extensions\Flags\Exceptions\FlagNotFoundException;
use Glueful\Extensions\Flags\Exceptions\RuleNotFoundException;
use Glueful\Extensions\Flags\Services\FeatureFlagManager;
use Glueful\Http\Response;
use Symfony\Component\HttpFoundation\Request;

final class FeatureFlagRuleController
{
    public function __construct(private FeatureFlagManager $manager)
    {
    }

    public function store(Request $request, string $key): Response
    {
        try {
            return Response::created(
                ['rule' => $this->manager->addRule($key, $this->body($request))],
                'Flag rule created.'
            );
        } catch (FlagNotFoundException) {
            return Response::notFound('Feature flag not found.');
        } catch (\InvalidArgumentException $e) {
            return Response::validation(['rule' => $e->getMessage()]);
        }
    }

    public function delete(Request $request, string $key, string $uuid): Response
    {
        try {
            $this->manager->removeRule($key, $uuid);
        } catch (FlagNotFoundException) {
            return Response::notFound('Feature flag not found.');
        } catch (RuleNotFoundException) {
            return Response::notFound('Flag rule not found.');
        }

        return Response::success([], 'Flag rule removed.');
    }

    /** @return array<string,mixed> */
    private function body(Request $request): array
    {
        $data = json_decode((string) $request->getContent(), true);

        return array_merge($request->request->all(), is_array($data) ? $data : []);
    }
}
