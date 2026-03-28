<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MaintenanceControllerTest extends TestCase
{
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
