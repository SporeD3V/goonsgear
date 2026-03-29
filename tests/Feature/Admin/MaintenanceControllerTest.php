<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MaintenanceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    public function test_clear_caches_and_logs_work_without_token_when_not_configured(): void
    {
        config(['app.admin_maintenance_token' => null]);

        $logDirectory = storage_path('logs');
        File::ensureDirectoryExists($logDirectory);

        $customLogPath = $logDirectory.DIRECTORY_SEPARATOR.'custom-no-token.log';
        File::put($customLogPath, 'example-log-entry');

        $clearCachesResponse = $this->from(route('admin.products.index'))
            ->post(route('admin.maintenance.clear-caches'));

        $clearCachesResponse->assertRedirect(route('admin.products.index'));
        $clearCachesResponse->assertSessionHas('status');

        $clearLogsResponse = $this->from(route('admin.products.index'))
            ->post(route('admin.maintenance.clear-logs'));

        $clearLogsResponse->assertRedirect(route('admin.products.index'));
        $clearLogsResponse->assertSessionHas('status');
        $this->assertSame('', File::get($customLogPath));
    }

    public function test_clear_caches_requires_valid_token(): void
    {
        config(['app.admin_maintenance_token' => 'secret-token']);

        $response = $this->from(route('admin.products.index'))
            ->post(route('admin.maintenance.clear-caches'), [
                'maintenance_token' => 'wrong-token',
            ]);

        $response->assertRedirect(route('admin.products.index'));
        $response->assertSessionHasErrors(['maintenance']);
    }

    public function test_clear_logs_requires_valid_token(): void
    {
        config(['app.admin_maintenance_token' => 'secret-token']);

        $response = $this->from(route('admin.products.index'))
            ->post(route('admin.maintenance.clear-logs'), [
                'maintenance_token' => 'wrong-token',
            ]);

        $response->assertRedirect(route('admin.products.index'));
        $response->assertSessionHasErrors(['maintenance']);
    }

    public function test_clear_logs_truncates_log_files_with_valid_token(): void
    {
        config(['app.admin_maintenance_token' => 'secret-token']);

        $logDirectory = storage_path('logs');
        File::ensureDirectoryExists($logDirectory);

        $customLogPath = $logDirectory.DIRECTORY_SEPARATOR.'custom-test.log';
        File::put($customLogPath, 'example-log-entry');

        $response = $this->from(route('admin.products.index'))
            ->post(route('admin.maintenance.clear-logs'), [
                'maintenance_token' => 'secret-token',
            ]);

        $response->assertRedirect(route('admin.products.index'));
        $response->assertSessionHas('status');

        $this->assertSame('', File::get($customLogPath));
    }
}
