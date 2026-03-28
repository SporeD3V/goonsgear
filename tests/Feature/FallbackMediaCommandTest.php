<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FallbackMediaCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fallback cleanup removes originals only when optimized variants exist.
     */
    public function test_cleanup_command_deletes_only_eligible_fallback_files(): void
    {
        $eligibleFallback = storage_path('app/public/products/cleanup-test/fallback/eligible.jpg');
        $eligibleOptimized = storage_path('app/public/products/cleanup-test/gallery/eligible.webp');

        File::ensureDirectoryExists(dirname($eligibleFallback));
        File::ensureDirectoryExists(dirname($eligibleOptimized));
        File::put($eligibleFallback, 'fallback-content');
        File::put($eligibleOptimized, 'optimized-content');

        $ineligibleFallback = storage_path('app/public/products/cleanup-test/fallback/ineligible.jpg');
        File::put($ineligibleFallback, 'fallback-content');

        $this->artisan('media:fallback clean')->assertSuccessful();

        $this->assertFileDoesNotExist($eligibleFallback);
        $this->assertFileExists($ineligibleFallback);

        File::delete([$eligibleOptimized, $ineligibleFallback]);
    }
}
