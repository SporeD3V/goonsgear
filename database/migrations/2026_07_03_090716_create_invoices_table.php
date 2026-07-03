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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('sequence');
            $table->timestamp('issued_at');
            // Immutable rendering source (GoBD): the PDF is always produced
            // from this snapshot, never from live order/product data.
            $table->json('snapshot');
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->unique(['year', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
