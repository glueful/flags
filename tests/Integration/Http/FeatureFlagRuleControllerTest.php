<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Tests\Integration\Http;

use Glueful\Auth\UserIdentity;
use Glueful\Extensions\Flags\Http\Controllers\FeatureFlagRuleController;
use Glueful\Extensions\Flags\Repositories\FeatureFlagAuditRepository;
use Glueful\Extensions\Flags\Repositories\FeatureFlagRepository;
use Glueful\Extensions\Flags\Services\FeatureFlagCache;
use Glueful\Extensions\Flags\Services\FeatureFlagEvaluator;
use Glueful\Extensions\Flags\Services\FeatureFlagManager;
use Glueful\Extensions\Flags\Tests\Support\FlagsTestCase;
use Glueful\Http\Response;
use Symfony\Component\HttpFoundation\Request;

final class FeatureFlagRuleControllerTest extends FlagsTestCase
{
    private FeatureFlagManager $manager;
    private FeatureFlagRuleController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new FeatureFlagManager(
            new FeatureFlagRepository($this->connection()),
            new FeatureFlagAuditRepository($this->connection()),
            new FeatureFlagEvaluator(),
            new FeatureFlagCache(),
            $this->appContext()
        );
        $this->controller = new FeatureFlagRuleController($this->manager);
    }

    public function testStoreCreatesRule(): void
    {
        $flag = $this->manager->create(['key' => 'new_editor']);

        $response = $this->controller->store(
            $this->jsonRequest('POST', '/flags/new_editor/rules', ['type' => 'user', 'value' => ['user-1']], 'user-1'),
            'new_editor'
        );
        $data = $this->json($response);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('user', $data['data']['rule']['type']);
        self::assertSame('user-1', $this->latestAudit($flag->uuid)['actor_uuid']);
    }

    public function testStoreMissingTypeReturns422Envelope(): void
    {
        $this->manager->create(['key' => 'new_editor']);

        $response = $this->controller->store(
            $this->jsonRequest('POST', '/flags/new_editor/rules', ['value' => ['user-1']]),
            'new_editor'
        );
        $data = $this->json($response);

        self::assertSame(422, $response->getStatusCode());
        self::assertFalse($data['success']);
        self::assertArrayHasKey('rule', $data['error']['details']);
    }

    public function testStoreUnknownTypeReturns422(): void
    {
        $this->manager->create(['key' => 'new_editor']);

        $response = $this->controller->store(
            $this->jsonRequest('POST', '/flags/new_editor/rules', ['type' => 'geo', 'value' => ['gh']]),
            'new_editor'
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testStorePercentageOutOfRangeReturns422(): void
    {
        $this->manager->create(['key' => 'new_editor']);

        $response = $this->controller->store(
            $this->jsonRequest('POST', '/flags/new_editor/rules', ['type' => 'percentage', 'percentage' => 150]),
            'new_editor'
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testStoreUnknownFlagReturns404(): void
    {
        $response = $this->controller->store(
            $this->jsonRequest('POST', '/flags/missing/rules', ['type' => 'user', 'value' => ['user-1']]),
            'missing'
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testDeleteDisablesRule(): void
    {
        $this->manager->create(['key' => 'new_editor']);
        $rule = $this->manager->addRule('new_editor', ['type' => 'user', 'value' => ['user-1']]);

        $response = $this->controller->delete(
            Request::create('/flags/new_editor/rules/' . $rule->uuid, 'DELETE'),
            'new_editor',
            $rule->uuid
        );

        self::assertSame(200, $response->getStatusCode());
        $row = $this->connection()->table('feature_flag_rules')->where('uuid', '=', $rule->uuid)->first();
        self::assertNotNull($row);
        self::assertSame(0, (int) $row['enabled']);
    }

    public function testDeleteUnknownRuleReturns404(): void
    {
        $this->manager->create(['key' => 'new_editor']);

        $response = $this->controller->delete(
            Request::create('/flags/new_editor/rules/missing-rule', 'DELETE'),
            'new_editor',
            'missing-rule'
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testDeleteAlreadyRemovedRuleReturns404(): void
    {
        $this->manager->create(['key' => 'new_editor']);
        $rule = $this->manager->addRule('new_editor', ['type' => 'user', 'value' => ['user-1']]);
        $this->manager->removeRule('new_editor', $rule->uuid);

        $response = $this->controller->delete(
            Request::create('/flags/new_editor/rules/' . $rule->uuid, 'DELETE'),
            'new_editor',
            $rule->uuid
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testDeleteUnknownFlagReturns404(): void
    {
        $response = $this->controller->delete(
            Request::create('/flags/missing/rules/rule-1', 'DELETE'),
            'missing',
            'rule-1'
        );

        self::assertSame(404, $response->getStatusCode());
    }

    /** @param array<string,mixed> $payload */
    private function jsonRequest(string $method, string $uri, array $payload, ?string $actorUuid = null): Request
    {
        $request = Request::create(
            $uri,
            $method,
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        if ($actorUuid !== null) {
            $request->attributes->set('auth.user', new UserIdentity($actorUuid));
        }

        return $request;
    }

    /** @return array<string,mixed> */
    private function json(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string,mixed> */
    private function latestAudit(string $flagUuid): array
    {
        $rows = $this->connection()
            ->table('feature_flag_audits')
            ->where('flag_uuid', '=', $flagUuid)
            ->orderBy('id', 'DESC')
            ->get();

        self::assertNotEmpty($rows);

        return $rows[0];
    }
}
