<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_inventory_logs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meta_catalog_item_id', 26);
            $table->char('meta_catalog_id', 26);
            $table->bigInteger('previous_quantity')->nullable();
            $table->bigInteger('new_quantity')->nullable();
            $table->bigInteger('delta')->nullable();
            $table->string('source', 20)->default('manual');
            $table->char('meta_batch_request_id', 26)->nullable();
            $table->char('meta_product_feed_upload_id', 26)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->softDeletes();
            $table->foreign('meta_catalog_item_id')
                ->references('id')
                ->on('meta_catalog_items')
                ->cascadeOnDelete();

            $table->foreign('meta_catalog_id')
                ->references('id')
                ->on('meta_catalogs')
                ->cascadeOnDelete();

            $table->foreign('meta_batch_request_id')
                ->references('id')
                ->on('meta_batch_requests')
                ->nullOnDelete();

            $table->foreign('meta_product_feed_upload_id')
                ->references('id')
                ->on('meta_product_feed_uploads')
                ->nullOnDelete();

            $table->index(['meta_catalog_item_id', 'created_at']);
            $table->index(['meta_catalog_id', 'created_at']);
            $table->index('source');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE meta_inventory_logs ADD CONSTRAINT chk_source CHECK (source IN ('feed_upload', 'batch_api', 'manual', 'system'))");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE meta_inventory_logs ADD CONSTRAINT chk_source CHECK (source IN ('feed_upload', 'batch_api', 'manual', 'system'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_inventory_logs');
    }
};