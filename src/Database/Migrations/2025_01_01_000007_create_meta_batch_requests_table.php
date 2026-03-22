<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_batch_requests', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meta_catalog_id', 26);
            $table->string('handle', 500)->nullable();
            $table->enum('item_type', [
                'PRODUCT_ITEM',
                'VEHICLE',
                'HOTEL',
                'HOTEL_ROOM',
                'FLIGHT',
                'DESTINATION',
                'HOME_LISTING',
                'VEHICLE_OFFER',
            ])->default('PRODUCT_ITEM');
            $table->enum('operation', ['mixed', 'create', 'update', 'delete'])->default('mixed');
            $table->enum('status', ['pending', 'processing', 'complete', 'failed'])->default('pending');
            $table->unsignedInteger('items_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('errors')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('meta_catalog_id')
                ->references('id')
                ->on('meta_catalogs')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_batch_requests');
    }
};
