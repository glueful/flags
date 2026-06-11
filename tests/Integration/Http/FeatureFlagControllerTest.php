<?php

declare(strict_types=1);

namespace Glueful\Extensions\Flags\Tests\Integration\Http;

use Glueful\Extensions\Flags\Http\Controllers\FeatureFlagController;
use Glueful\Extensions\Flags\Repositories\FeatureFlagAuditRepository;
use Glueful\Extensions\Flags\Repositories\FeatureFlagRepository;
use Glueful\Extensions\Flags\Services\FeatureFlagCache;
use Glueful\Extensions\Flags\Services\FeatureFlagEvaluator;
use Glueful\Extensions\Flags\Services\FeatureFlagManager;
use Glueful\Extensions\Flags\Tests\Support\FlagsTestCase;
use Glueful\Http\Response;
use Symfony\Component\HttpFoundation\Request;

final class FeatureFlagControllerTest extends FlagsTestCase
{
    private FeatureFlagManager $manager;
    private FeatureFlagController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $flags = new FeatureFlagRepository($this->connection());
        $this->manager = new FeatureFlagManager(
            $flags,
            new FeatureFlagAuditRepository($this->connection()),
            new FeatureFlagEvaluator(),
            new FeatureFlagCache(),
            $this->appContext()
        );
        $this->controller = new FeatureFlagController($this->manager, $flags);
    }

    // -- happy paths ----------------------------------------------------

    public function testStoreCreatesFlag(): void
    {
        $response = $this->controller->store($this->jsonRequest('POST', '/flags', [
            'key' => 'new_editor',
            'name' => 'New editor',
        ]));
        $data = $this->json($response);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('new_editor', $data['data']['flag']['key']);
    }

    public function testUpdateChangesFlag(): void
    {
        $this->manager->create(['key' => 'new_editor']);

        $response = $this->controller->update(
            $this->jsonRequest('PATCH', '/flags/new_editor', ['enabled' => true]),
            'new_editor'
        );
        $data = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($data['data']['flag']['enabled']);
    }

    public function testArchiveSoftDeletesFlag(): void
    {
        $this->manager->create(['key' => 'new_editor', 'enabled' => true]);

        $response = $this->controller->archive(
            Request::create('/flags/new_editor', 'DELETE'),
            'new_editor'
        );
        $data = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('archived', $data['data']['flag']['status']);
        self::assertFalse($data['data']['flag']['enabled']);
    }

    public function testShowReturnsFlag(): void
    {
        $this->manager->create(['key' => 'new_editor']);

        $response = $this->controller->show(Request::create('/flags/new_editor'), 'new_editor');

        self::assertSame(200, $response->getStatusCode());
    }

    // -- 422 matrix -----------------------------------------------------

    public function testStoreMissingKeyReturns422Envelope(): void
    {
        $response = $this->controller->store($this->jsonRequest('POST', '/flags', ['name' => 'No key']));
        $data = $this->json($response);

        self::assertSame(422, $response->getStatusCode());
        self::assertFalse($data['success']);
        self::assertSame(422, $data['error']['code']);
        self::assertArrayHasKey('flag', $data['error']['details']);
    }

    public function testStoreBadKeyCharsetReturns422(): void
    {
        $response = $this->controller->store($this->jsonRequest('POST', '/flags', ['key' => 'New Editor!']));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testStoreInvalidStatusReturns422(): void
    {
        $response = $this->controller->store($this->jsonRequest('POST', '/flags', [
            'key' => 'new_editor',
            'status' => 'paused',
        ]));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testStoreDuplicateKeyReturns422(): void
    {
        $this->manager->create(['key' => 'new_editor']);

        $response = $this->controller->store($this->jsonRequest('POST', '/flags', ['key' => 'new_editor']));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testStoreNonBooleanEnabledReturns422(): void
    {
        $response = $this->controller->store($this->jsonRequest('POST', '/flags', [
            'key' => 'new_editor',
            'enabled' => 'yes',
        ]));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateInvalidStatusReturns422(): void
    {
        $this->manager->create(['key' => 'new_editor']);

        $response = $this->controller->update(
            $this->jsonRequest('PATCH', '/flags/new_editor', ['status' => 'paused']),
            'new_editor'
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateKeyChangeReturns422(): void
    {
        $this->manager->create(['key' => 'new_editor']);

        $response = $this->controller->update(
            $this->jsonRequest('PATCH', '/flags/new_editor', ['key' => 'renamed']),
            'new_editor'
        );

        self::assertSame(422, $response->getStatusCode());
    }

    // -- 404s -----------------------------------------------------------

    public function testUpdateUnknownFlagReturns404(): void
    {
        $response = $this->controller->update(
            $this->jsonRequest('PATCH', '/flags/missing', ['enabled' => true]),
            'missing'
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testArchiveUnknownFlagReturns404(): void
    {
        $response = $this->controller->archive(Request::create('/flags/missing', 'DELETE'), 'missing');

        self::assertSame(404, $response->getStatusCode());
    }

    public function testShowUnknownFlagReturns404(): void
    {
        $response = $this->controller->show(Request::create('/flags/missing'), 'missing');

        self::assertSame(404, $response->getStatusCode());
    }

    /** @param array<string,mixed> $payload */
    private function jsonRequest(string $method, string $uri, array $payload): Request
    {
        return Request::create(
            $uri,
            $method,
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }

    /** @return array<string,mixed> */
    private function json(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }
}
