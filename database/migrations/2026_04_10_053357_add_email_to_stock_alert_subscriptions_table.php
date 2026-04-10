<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_alert_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique('stock_alert_subscriptions_user_variant_unique');
        });

        Schema::table('stock_alert_subscriptions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('email')->nullable()->after('user_id');
            $table->unique(['user_id', 'product_variant_id', 'email'], 'stock_alert_sub_user_variant_email_unique');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_alert_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique('stock_alert_sub_user_variant_email_unique');
        });

        Schema::table('stock_alert_subscriptions', function (Blueprint $table) {
            $table->dropColumn('email');
            $table->foreignId('user_id')->nullable(false)->change();
            $table->unique(['user_id', 'product_variant_id'], 'stock_alert_subscriptions_user_variant_unique');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
