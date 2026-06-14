<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Http\Controllers;

use Glueful\Bootstrap\ApplicationContext;
use Glueful\Extensions\Flags\Services\FeatureFlagManager;
use Glueful\Extensions\Flags\Support\FlagContextFactory;
use Glueful\Http\Response;
use Glueful\Routing\Attributes\ApiOperation;
use Glueful\Routing\Attributes\ApiResponse;
use Symfony\Component\HttpFoundation\Request;

final class FeatureFlagEvaluateController
{
    public function __construct(
        private FeatureFlagManager $manager,
        private FlagContextFactory $factory,
        private ApplicationContext $context,
    ) {
    }

    /**
     * Evaluate a feature flag against a context.
     */
    #[ApiOperation(
        summary: 'Evaluate Feature Flag',
        description: 'Evaluates a flag against a caller-supplied context and returns the boolean result. '
            . 'A missing flag returns the configured flags.default; environment defaults to the '
            . 'flags.environment config value when omitted. '
            . 'Body: `user` (user UUID to evaluate for), `tenant` (tenant UUID to evaluate for), '
            . '`environment` (environment name, defaults to flags.environment config), `roles` (role '
            . 'names for role rules), `scopes` (scope names for scope rules), `attributes` (free-form '
            . 'attributes for attribute rules and custom percentage subjects). '
            . 'Requires the `flags.evaluate` permission.',
        tags: ['Flags'],
    )]
    #[ApiResponse(200, description: 'Feature flag evaluated')]
    #[ApiResponse(401, description: 'Not authenticated')]
    #[ApiResponse(403, description: 'Missing flags.evaluate permission')]
    public function evaluate(Request $request, string $key): Response
    {
        $data = json_decode((string) $request->getContent(), true);
        $payload = is_array($data) ? $data : [];
        $payload['environment'] ??= \config($this->context, 'flags.environment', 'production');

        return Response::success([
            'enabled' => $this->manager->enabled($key, $this->factory->fromArray($payload)),
        ], 'Feature flag evaluated.');
    }
}
