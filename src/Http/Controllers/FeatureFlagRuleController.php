<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Http\Controllers;

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
        return Response::created(
            ['rule' => $this->manager->addRule($key, $this->body($request))],
            'Flag rule created.'
        );
    }

    public function delete(Request $request, string $key, string $uuid): Response
    {
        $this->manager->removeRule($key, $uuid);

        return Response::success([], 'Flag rule removed.');
    }

    /** @return array<string,mixed> */
    private function body(Request $request): array
    {
        $data = json_decode((string) $request->getContent(), true);

        return array_merge($request->request->all(), is_array($data) ? $data : []);
    }
}
