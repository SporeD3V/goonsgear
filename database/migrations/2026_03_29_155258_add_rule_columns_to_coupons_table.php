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
        Schema::table('coupons', function (Blueprint $table) {
            $table->boolean('is_stackable')->default(false)->after('is_active');
            $table->string('stack_group', 50)->nullable()->after('is_stackable');
            $table->string('scope_type', 20)->nullable()->after('stack_group');
            $table->unsignedBigInteger('scope_id')->nullable()->after('scope_type');
            $table->boolean('is_personal')->default(false)->after('scope_id');

            $table->index('stack_group');
            $table->index(['scope_type', 'scope_id']);
            $table->index('is_personal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropIndex(['is_personal']);
            $table->dropIndex(['scope_type', 'scope_id']);
            $table->dropIndex(['stack_group']);
            $table->dropColumn([
                'is_stackable',
                'stack_group',
                'scope_type',
                'scope_id',
                'is_personal',
            ]);
        });
    }
};
