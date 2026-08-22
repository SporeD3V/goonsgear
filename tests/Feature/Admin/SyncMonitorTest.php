<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\WcSyncPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SyncMonitorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A payload the processor can actually complete, so tests can tell
     * "was skipped" apart from "ran and failed".
     */
    private function processablePayload(array $attributes = []): WcSyncPayload
    {
        return WcSyncPayload::factory()
            ->forEvent('product.created', [
                'wc_product_id' => 4100,
                'name' => 'Synced Tee',
                'slug' => 'synced-tee',
                'status' => 'publish',
            ])
            ->create($attributes + ['wc_entity_id' => 4100]);
    }

    public function test_retry_clears_the_error_and_attempt_count(): void
    {
        $this->actingAsAdmin();

        $payload = WcSyncPayload::factory()->failed('SQLSTATE[23000]: Duplicate entry')->create();

        Livewire::test('admin.sync-monitor')
            ->call('retryPayload', $payload->id);

        $payload->refresh();
        $this->assertSame(0, $payload->attempts);
        $this->assertNull($payload->processing_error);
        $this->assertNull($payload->processed_at);
    }

    public function test_dismiss_retires_the_payload_but_keeps_the_reason(): void
    {
        $this->actingAsAdmin();

        $payload = WcSyncPayload::factory()->failed('SQLSTATE[23000]: Duplicate entry')->create();

        Livewire::test('admin.sync-monitor')
            ->call('dismissPayload', $payload->id);

        $payload->refresh();
        $this->assertNotNull($payload->processed_at);
        $this->assertSame('SQLSTATE[23000]: Duplicate entry', $payload->processing_error);
        $this->assertTrue($payload->isDismissed());
    }

    public function test_dismissing_an_untouched_payload_records_why(): void
    {
        $this->actingAsAdmin();

        $payload = WcSyncPayload::factory()->create();

        Livewire::test('admin.sync-monitor')
            ->call('dismissPayload', $payload->id);

        $payload->refresh();
        $this->assertTrue($payload->isDismissed());
        $this->assertSame('Dismissed manually without processing.', $payload->processing_error);
    }

    public function test_dismissed_payloads_are_not_counted_as_processed(): void
    {
        $this->actingAsAdmin();

        $dismissed = WcSyncPayload::factory()->failed()->create();
        Livewire::test('admin.sync-monitor')->call('dismissPayload', $dismissed->id);

        Livewire::test('admin.sync-monitor')
            ->set('statusFilter', 'processed')
            ->assertDontSee('#'.$dismissed->id)
            ->set('statusFilter', 'dismissed')
            ->assertSee('#'.$dismissed->id);
    }

    public function test_delete_removes_the_payload(): void
    {
        $this->actingAsAdmin();

        $payload = WcSyncPayload::factory()->failed()->create();

        Livewire::test('admin.sync-monitor')
            ->call('deletePayload', $payload->id);

        $this->assertDatabaseMissing('wc_sync_payloads', ['id' => $payload->id]);
    }

    public function test_bulk_retry_resets_every_failed_payload(): void
    {
        $this->actingAsAdmin();

        WcSyncPayload::factory()->failed()->count(3)->create();
        $processed = WcSyncPayload::factory()->processed()->create();

        Livewire::test('admin.sync-monitor')
            ->call('retryAllFailed');

        $this->assertSame(0, WcSyncPayload::failed()->count());
        $this->assertSame(3, WcSyncPayload::pending()->count());
        $this->assertNotNull($processed->refresh()->processed_at);
    }

    public function test_bulk_dismiss_retires_every_failed_payload(): void
    {
        $this->actingAsAdmin();

        WcSyncPayload::factory()->failed()->count(2)->create();

        Livewire::test('admin.sync-monitor')
            ->call('dismissAllFailed');

        $this->assertSame(0, WcSyncPayload::pending()->count());
    }

    public function test_processor_stops_retrying_after_the_attempt_limit(): void
    {
        $payload = $this->processablePayload(['attempts' => WcSyncPayload::MAX_ATTEMPTS]);

        $this->artisan('sync:process')->assertSuccessful();

        // Still unprocessed: the processor never picked it up.
        $this->assertNull($payload->refresh()->processed_at);
        $this->assertSame(WcSyncPayload::MAX_ATTEMPTS, $payload->attempts);
    }

    public function test_retrying_an_exhausted_payload_makes_it_run_again(): void
    {
        $this->actingAsAdmin();

        $payload = $this->processablePayload([
            'attempts' => WcSyncPayload::MAX_ATTEMPTS,
            'processing_error' => 'SQLSTATE[23000]: Duplicate entry',
        ]);

        $this->assertTrue($payload->isExhausted());

        Livewire::test('admin.sync-monitor')
            ->call('retryPayload', $payload->id);

        $this->artisan('sync:process')->assertSuccessful();

        $this->assertNotNull($payload->refresh()->processed_at);
        $this->assertNull($payload->processing_error);
    }

    public function test_payloads_below_the_limit_are_still_retried(): void
    {
        $payload = $this->processablePayload(['attempts' => WcSyncPayload::MAX_ATTEMPTS - 1]);

        $this->artisan('sync:process')->assertSuccessful();

        $this->assertNotNull($payload->refresh()->processed_at);
    }

    public function test_failed_rows_offer_retry_dismiss_and_delete(): void
    {
        $this->actingAsAdmin();

        $payload = WcSyncPayload::factory()->failed()->create();

        Livewire::test('admin.sync-monitor')
            ->set('statusFilter', 'failed')
            ->assertSee("retryPayload({$payload->id})", false)
            ->assertSee("dismissPayload({$payload->id})", false)
            ->assertSee("deletePayload({$payload->id})", false);
    }

    public function test_successfully_processed_rows_cannot_be_retried_or_dismissed(): void
    {
        $this->actingAsAdmin();

        $payload = WcSyncPayload::factory()->processed()->create();

        Livewire::test('admin.sync-monitor')
            ->set('statusFilter', 'processed')
            ->assertDontSee("retryPayload({$payload->id})", false)
            ->assertDontSee("dismissPayload({$payload->id})", false)
            ->assertSee("deletePayload({$payload->id})", false);
    }

    public function test_non_admin_cannot_reach_the_sync_monitor(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.sync-monitor.index'))
            ->assertForbidden();
    }
}
