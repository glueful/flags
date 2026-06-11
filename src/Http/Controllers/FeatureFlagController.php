<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Http\Controllers;

use Glueful\Extensions\Flags\Exceptions\FlagNotFoundException;
use Glueful\Extensions\Flags\Repositories\FeatureFlagRepository;
use Glueful\Extensions\Flags\Services\FeatureFlagManager;
use Glueful\Http\Response;
use Symfony\Component\HttpFoundation\Request;

final class FeatureFlagController
{
    public function __construct(private FeatureFlagManager $manager, private FeatureFlagRepository $flags)
    {
    }

    public function index(Request $request): Response
    {
        return Response::success(['flags' => $this->flags->all()], 'Feature flags retrieved.');
    }

    public function store(Request $request): Response
    {
        try {
            return Response::created(
                ['flag' => $this->manager->create($this->body($request))],
                'Feature flag created.'
            );
        } catch (\InvalidArgumentException $e) {
            return Response::validation(['flag' => $e->getMessage()]);
        }
    }

    public function show(Request $request, string $key): Response
    {
        $flag = $this->manager->get($key);
        if ($flag === null) {
            return Response::notFound('Feature flag not found.');
        }

        return Response::success(['flag' => $flag], 'Feature flag retrieved.');
    }

    public function update(Request $request, string $key): Response
    {
        try {
            return Response::success(
                ['flag' => $this->manager->update($key, $this->body($request))],
                'Feature flag updated.'
            );
        } catch (FlagNotFoundException) {
            return Response::notFound('Feature flag not found.');
        } catch (\InvalidArgumentException $e) {
            return Response::validation(['flag' => $e->getMessage()]);
        }
    }

    public function archive(Request $request, string $key): Response
    {
        try {
            return Response::success(
                ['flag' => $this->manager->update($key, ['status' => 'archived', 'enabled' => false])],
                'Feature flag archived.'
            );
        } catch (FlagNotFoundException) {
            return Response::notFound('Feature flag not found.');
        }
    }

    /** @return array<string,mixed> */
    private function body(Request $request): array
    {
        $data = json_decode((string) $request->getContent(), true);

        return array_merge($request->request->all(), is_array($data) ? $data : []);
    }
}
