<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MediaDeliveryTest extends TestCase
{
    /**
     * Uploaded public media can be served through the media route.
     */
    public function test_media_route_serves_existing_file(): void
    {
        $filePath = storage_path('app/public/products/test/gallery/demo.jpg');
        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, 'demo-content');

        $response = $this->get(route('media.show', ['path' => 'products/test/gallery/demo.jpg']));

        $response->assertOk();

        File::delete($filePath);
    }

    /**
     * Missing media returns a 404.
     */
    public function test_media_route_returns_404_for_missing_file(): void
    {
        $response = $this->get(route('media.show', ['path' => 'products/test/gallery/missing.jpg']));

        $response->assertNotFound();
    }
}
