<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class MaintenanceController extends Controller
{
    public function clearCaches(Request $request): RedirectResponse
    {
        if (! $this->hasValidMaintenanceToken($request)) {
            return redirect()
                ->back()
                ->withErrors(['maintenance' => $this->maintenanceTokenErrorMessage()]);
        }

        Artisan::call('optimize:clear');

        Log::warning('Admin maintenance cleared caches.', [
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->back()
            ->with('status', 'Application caches cleared successfully.');
    }

    public function clearLogs(Request $request): RedirectResponse
    {
        if (! $this->hasValidMaintenanceToken($request)) {
            return redirect()
                ->back()
                ->withErrors(['maintenance' => $this->maintenanceTokenErrorMessage()]);
        }

        $logDirectory = storage_path('logs');

        File::ensureDirectoryExists($logDirectory);

        $logFiles = glob($logDirectory.DIRECTORY_SEPARATOR.'*.log') ?: [];

        foreach ($logFiles as $logFile) {
            if (is_file($logFile)) {
                File::put($logFile, '');
            }
        }

        if ($logFiles === []) {
            File::put($logDirectory.DIRECTORY_SEPARATOR.'laravel.log', '');
        }

        Log::warning('Admin maintenance cleared logs.', [
            'ip' => $request->ip(),
            'cleared_files' => count($logFiles),
        ]);

        return redirect()
            ->back()
            ->with('status', 'Application logs cleared successfully.');
    }

    private function hasValidMaintenanceToken(Request $request): bool
    {
        $expectedToken = (string) config('app.admin_maintenance_token', '');
        $providedToken = $request->string('maintenance_token')->trim()->toString();

        if ($expectedToken === '' || $providedToken === '') {
            return false;
        }

        return hash_equals($expectedToken, $providedToken);
    }

    private function maintenanceTokenErrorMessage(): string
    {
        $expectedToken = (string) config('app.admin_maintenance_token', '');

        if ($expectedToken === '') {
            return 'Maintenance token is not configured. Set ADMIN_MAINTENANCE_TOKEN in the environment.';
        }

        return 'Invalid maintenance token.';
    }
}
