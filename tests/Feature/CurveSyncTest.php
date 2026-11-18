<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Mockery\MockInterface;
use App\Services\CurveSyncService;

class CurveSyncTest extends TestCase
{
    protected string $syncKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncKey = 'test-secret-sync-key';
        config(['services.curve.sync_key' => $this->syncKey]);
    }

    public function test_rejects_requests_without_key(): void
    {
        $response = $this->postJson('/curve/sync/clinics');
        $response->assertStatus(403);
    }

    public function test_rejects_requests_with_invalid_key(): void
    {
        $response = $this->postJson('/curve/sync/clinics', [], [
            'X-Sync-Key' => 'invalid-key-12345',
        ]);
        $response->assertStatus(403);
    }

    public function test_accepts_valid_header_key(): void
    {
        $this->mock(CurveSyncService::class, function (MockInterface $mock) {
            $mock->shouldReceive('syncClinics')->once()->andReturn([
                'entity' => 'clinics',
                'fetched' => 3,
                'created' => 3,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [],
            ]);
        });

        $response = $this->postJson('/curve/sync/clinics', [], [
            'X-Sync-Key' => $this->syncKey,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'entity' => 'clinics',
            'fetched' => 3,
            'created' => 3,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ]);
    }

    public function test_accepts_valid_query_param_key(): void
    {
        $this->mock(CurveSyncService::class, function (MockInterface $mock) {
            $mock->shouldReceive('syncClinics')->once()->andReturn([
                'entity' => 'clinics',
                'fetched' => 3,
                'created' => 0,
                'updated' => 0,
                'skipped' => 3,
                'errors' => [],
            ]);
        });

        $response = $this->postJson('/curve/sync/clinics?key='.$this->syncKey);
        $response->assertStatus(200);
        $response->assertJson([
            'entity' => 'clinics',
            'skipped' => 3,
        ]);
    }

    public function test_all_endpoint(): void
    {
        $this->mock(CurveSyncService::class, function (MockInterface $mock) {
            $mock->shouldReceive('syncAll')->once()->andReturn([
                'clinics' => [
                    'entity' => 'clinics',
                    'fetched' => 3,
                    'created' => 3,
                    'updated' => 0,
                    'skipped' => 0,
                    'errors' => [],
                ],
                'operatories' => [
                    'entity' => 'operatories',
                    'fetched' => 5,
                    'created' => 5,
                    'updated' => 0,
                    'skipped' => 0,
                    'errors' => [],
                ],
                'providers' => [
                    'entity' => 'providers',
                    'fetched' => 10,
                    'created' => 10,
                    'updated' => 0,
                    'skipped' => 0,
                    'errors' => [],
                ],
                'appointment_tags' => [
                    'entity' => 'appointment_tags',
                    'fetched' => 4,
                    'created' => 4,
                    'updated' => 0,
                    'skipped' => 0,
                    'errors' => [],
                ],
            ]);
        });

        $response = $this->postJson('/curve/sync/all', [], [
            'X-Sync-Key' => $this->syncKey,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'clinics' => ['entity', 'fetched', 'created', 'updated', 'skipped', 'errors'],
            'operatories' => ['entity', 'fetched', 'created', 'updated', 'skipped', 'errors'],
            'providers' => ['entity', 'fetched', 'created', 'updated', 'skipped', 'errors'],
            'appointment_tags' => ['entity', 'fetched', 'created', 'updated', 'skipped', 'errors'],
        ]);
    }

    public function test_csrf_is_exempt(): void
    {
        $this->mock(CurveSyncService::class, function (MockInterface $mock) {
            $mock->shouldReceive('syncProviders')->once()->andReturn([
                'entity' => 'providers',
                'fetched' => 60,
                'created' => 60,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [],
            ]);
        });

        // Calling POST via standard web request without CSRF token
        $response = $this->call('POST', '/curve/sync/providers', [], [], [], [
            'HTTP_X-Sync-Key' => $this->syncKey,
        ]);

        $response->assertStatus(200);
    }

    public function test_operatories_endpoint(): void
    {
        $this->mock(CurveSyncService::class, function (MockInterface $mock) {
            $mock->shouldReceive('syncOperatories')->once()->andReturn([
                'entity' => 'operatories',
                'fetched' => 10,
                'created' => 10,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [],
            ]);
        });

        $response = $this->postJson('/curve/sync/operatories', [], [
            'X-Sync-Key' => $this->syncKey,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'entity' => 'operatories',
        ]);
    }

    public function test_appointment_tags_endpoint(): void
    {
        $this->mock(CurveSyncService::class, function (MockInterface $mock) {
            $mock->shouldReceive('syncAppointmentTags')->once()->andReturn([
                'entity' => 'appointment_tags',
                'fetched' => 64,
                'created' => 64,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [],
            ]);
        });

        $response = $this->postJson('/curve/sync/appointment-tags', [], [
            'X-Sync-Key' => $this->syncKey,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'entity' => 'appointment_tags',
        ]);
    }
}
