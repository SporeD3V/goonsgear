<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate existing product edit history into the generic edit_histories table
        DB::table('product_edit_histories')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                $inserts = [];

                foreach ($rows as $row) {
                    $inserts[] = [
                        'user_id' => $row->user_id,
                        'editable_type' => 'App\\Models\\Product',
                        'editable_id' => $row->product_id,
                        'field' => $row->field,
                        'old_value' => $row->old_value,
                        'new_value' => $row->new_value,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ];
                }

                DB::table('edit_histories')->insert($inserts);
            });

        Schema::dropIfExists('product_edit_histories');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('product_edit_histories', function ($table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('field');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'field', 'created_at']);
        });
    }
};
