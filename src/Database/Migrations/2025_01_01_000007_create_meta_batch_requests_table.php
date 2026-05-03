<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_batch_requests', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meta_catalog_id', 26);
            $table->string('handle', 500)->nullable();
            $table->string('item_type', 20)->default('PRODUCT_ITEM');
            $table->string('operation', 20)->default('mixed');
            $table->string('status', 20)->default('pending');
            $table->bigInteger('items_count')->default(0);
            $table->bigInteger('success_count')->default(0);
            $table->bigInteger('error_count')->default(0);
            $table->json('errors')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->softDeletes();
            $table->foreign('meta_catalog_id')
                ->references('id')
                ->on('meta_catalogs')
                ->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE meta_batch_requests ADD CONSTRAINT chk_batch_item_type CHECK (item_type IN ('PRODUCT_ITEM', 'VEHICLE', 'HOTEL', 'HOTEL_ROOM', 'FLIGHT', 'DESTINATION', 'HOME_LISTING', 'VEHICLE_OFFER'))");
            DB::statement("ALTER TABLE meta_batch_requests ADD CONSTRAINT chk_batch_operation CHECK (operation IN ('mixed', 'create', 'update', 'delete'))");
            DB::statement("ALTER TABLE meta_batch_requests ADD CONSTRAINT chk_batch_status CHECK (status IN ('pending', 'processing', 'complete', 'failed'))");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE meta_batch_requests ADD CONSTRAINT chk_batch_item_type CHECK (item_type IN ('PRODUCT_ITEM', 'VEHICLE', 'HOTEL', 'HOTEL_ROOM', 'FLIGHT', 'DESTINATION', 'HOME_LISTING', 'VEHICLE_OFFER'))");
            DB::statement("ALTER TABLE meta_batch_requests ADD CONSTRAINT chk_batch_operation CHECK (operation IN ('mixed', 'create', 'update', 'delete'))");
            DB::statement("ALTER TABLE meta_batch_requests ADD CONSTRAINT chk_batch_status CHECK (status IN ('pending', 'processing', 'complete', 'failed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_batch_requests');
    }
};