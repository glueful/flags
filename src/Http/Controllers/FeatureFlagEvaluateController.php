<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Http\Controllers;

use Glueful\Bootstrap\ApplicationContext;
use Glueful\Extensions\Flags\Services\FeatureFlagManager;
use Glueful\Extensions\Flags\Support\FlagContextFactory;
use Glueful\Http\Response;
use Symfony\Component\HttpFoundation\Request;

final class FeatureFlagEvaluateController
{
    public function __construct(
        private FeatureFlagManager $manager,
        private FlagContextFactory $factory,
        private ApplicationContext $context,
    ) {
    }

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
