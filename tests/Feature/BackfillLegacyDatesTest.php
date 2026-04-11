<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BackfillLegacyDatesTest extends TestCase
{
    use RefreshDatabase;

    private string $legacyDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->legacyDatabasePath = database_path('testing-legacy-backfill.sqlite');

        if (file_exists($this->legacyDatabasePath)) {
            unlink($this->legacyDatabasePath);
        }

        touch($this->legacyDatabasePath);

        Config::set('database.connections.legacy', [
            'driver' => 'sqlite',
            'database' => $this->legacyDatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('legacy');

        $this->createLegacySchema();
    }

    protected function tearDown(): void
    {
        DB::disconnect('legacy');

        if (file_exists($this->legacyDatabasePath)) {
            unlink($this->legacyDatabasePath);
        }

        parent::tearDown();
    }

    public function test_backfills_customer_created_at_from_legacy_user_registered(): void
    {
        $user = User::factory()->create(['email' => 'legacy@example.com']);

        $originalRegistered = '2021-06-15 10:30:00';

        DB::connection('legacy')->table('wp_users')->insert([
            'ID' => 42,
            'user_email' => 'legacy@example.com',
            'user_login' => 'legacyuser',
            'user_registered' => $originalRegistered,
        ]);

        DB::table('import_legacy_customers')->insert([
            'legacy_wp_user_id' => 42,
            'user_id' => $user->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('app:backfill-legacy-dates')
            ->assertSuccessful()
            ->expectsOutputToContain('Updated 1 customer dates');

        $user->refresh();
        $this->assertSame('2021-06-15 10:30:00', $user->created_at->format('Y-m-d H:i:s'));
    }

    public function test_backfills_order_shipped_at_from_legacy_date_completed(): void
    {
        $completedTimestamp = Carbon::parse('2022-03-10 14:00:00')->timestamp;

        $order = Order::factory()->create([
            'order_number' => 'WC-500',
            'status' => 'completed',
            'payment_status' => 'completed',
            'shipped_at' => null,
            'placed_at' => '2022-03-08 09:00:00',
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            'post_id' => 500,
            'meta_key' => '_date_completed',
            'meta_value' => (string) $completedTimestamp,
        ]);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 500,
            'order_id' => $order->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('app:backfill-legacy-dates')
            ->assertSuccessful()
            ->expectsOutputToContain('Updated 1 order shipped_at dates');

        $order->refresh();
        $this->assertNotNull($order->shipped_at);
        $this->assertSame('2022-03-10 14:00:00', $order->shipped_at->format('Y-m-d H:i:s'));
    }

    public function test_skips_non_completed_orders(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'WC-600',
            'status' => 'processing',
            'payment_status' => 'completed',
            'shipped_at' => null,
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            'post_id' => 600,
            'meta_key' => '_date_completed',
            'meta_value' => (string) now()->timestamp,
        ]);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 600,
            'order_id' => $order->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('app:backfill-legacy-dates')
            ->assertSuccessful()
            ->expectsOutputToContain('Updated 0 order shipped_at dates');

        $order->refresh();
        $this->assertNull($order->shipped_at);
    }

    public function test_skips_orders_that_already_have_shipped_at(): void
    {
        $existingShipped = Carbon::parse('2022-01-01 12:00:00');

        $order = Order::factory()->create([
            'order_number' => 'WC-700',
            'status' => 'completed',
            'payment_status' => 'completed',
            'shipped_at' => $existingShipped,
        ]);

        DB::connection('legacy')->table('wp_postmeta')->insert([
            'post_id' => 700,
            'meta_key' => '_date_completed',
            'meta_value' => (string) now()->timestamp,
        ]);

        DB::table('import_legacy_orders')->insert([
            'legacy_wc_order_id' => 700,
            'order_id' => $order->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('app:backfill-legacy-dates')
            ->assertSuccessful()
            ->expectsOutputToContain('Updated 0 order shipped_at dates');

        $order->refresh();
        $this->assertSame('2022-01-01 12:00:00', $order->shipped_at->format('Y-m-d H:i:s'));
    }

    public function test_dry_run_does_not_modify_data(): void
    {
        $user = User::factory()->create(['email' => 'dryrun@example.com']);
        $originalCreatedAt = $user->created_at->format('Y-m-d H:i:s');

        DB::connection('legacy')->table('wp_users')->insert([
            'ID' => 99,
            'user_email' => 'dryrun@example.com',
            'user_login' => 'dryrunuser',
            'user_registered' => '2019-01-01 00:00:00',
        ]);

        DB::table('import_legacy_customers')->insert([
            'legacy_wp_user_id' => 99,
            'user_id' => $user->id,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('app:backfill-legacy-dates --dry-run')
            ->assertSuccessful()
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('Would update 1 customer dates');

        $user->refresh();
        $this->assertSame($originalCreatedAt, $user->created_at->format('Y-m-d H:i:s'));
    }

    private function createLegacySchema(): void
    {
        $schema = Schema::connection('legacy');

        $schema->create('wp_users', function ($table) {
            $table->unsignedBigInteger('ID')->primary();
            $table->string('user_email');
            $table->string('user_login');
            $table->dateTime('user_registered')->nullable();
        });

        $schema->create('wp_postmeta', function ($table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->string('meta_key');
            $table->text('meta_value')->nullable();
        });
    }
}
