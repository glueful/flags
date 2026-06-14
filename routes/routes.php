<?php

declare(strict_types=1);

use Glueful\Extensions\Flags\Http\Controllers\FeatureFlagController;
use Glueful\Extensions\Flags\Http\Controllers\FeatureFlagEvaluateController;
use Glueful\Extensions\Flags\Http\Controllers\FeatureFlagRuleController;
use Glueful\Routing\Router;

/** @var Router $router Router instance injected by RouteManifest::load() */

$router->group(['prefix' => '/flags', 'middleware' => ['auth']], function (Router $router): void {
    // Flag management
    $router->get('', [FeatureFlagController::class, 'index'])->middleware('flags_permission:flags.view');
    $router->post('', [FeatureFlagController::class, 'store'])->middleware('flags_permission:flags.manage');
    $router->get('/{key}', [FeatureFlagController::class, 'show'])->middleware('flags_permission:flags.view');
    $router->patch('/{key}', [FeatureFlagController::class, 'update'])->middleware('flags_permission:flags.manage');
    $router->delete('/{key}', [FeatureFlagController::class, 'archive'])->middleware('flags_permission:flags.manage');

    // Flag rules
    $router->post('/{key}/rules', [FeatureFlagRuleController::class, 'store'])->middleware('flags_permission:flags.manage');
    $router->delete('/{key}/rules/{uuid}', [FeatureFlagRuleController::class, 'delete'])
        ->middleware('flags_permission:flags.manage');

    // Evaluation
    $router->post('/{key}/evaluate', [FeatureFlagEvaluateController::class, 'evaluate'])
        ->middleware('flags_permission:flags.evaluate');
});
