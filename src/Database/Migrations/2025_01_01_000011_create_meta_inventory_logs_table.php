<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_inventory_logs', function (Blueprint $table) {
            $table->char('id', 26)->primary();

            // El producto cuyo inventario cambió
            $table->char('meta_catalog_item_id', 26);

            // Desnormalizado para queries rápidos por catálogo sin JOIN
            $table->char('meta_catalog_id', 26);

            // Valores antes y después del cambio
            $table->unsignedInteger('previous_quantity')->nullable();
            $table->unsignedInteger('new_quantity')->nullable();

            // Delta: positivo = reposición, negativo = bajó stock
            $table->integer('delta')->nullable();

            // Origen del cambio
            $table->enum('source', [
                'feed_upload',  // cambio vino de un feed upload
                'batch_api',    // cambio vino de la Batch API en tiempo real
                'manual',       // cambio hecho a mano via ProductService
                'system',       // cambio interno del paquete
            ])->default('manual');

            // Links opcionales al origen exacto del cambio
            $table->char('meta_batch_request_id', 26)->nullable();
            $table->char('meta_product_feed_upload_id', 26)->nullable();

            // Contexto adicional
            $table->text('notes')->nullable();

            // Sin softDeletes — el historial es inmutable
            $table->timestamps();

            // Foreign keys
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

            // Indexes para queries frecuentes
            $table->index(['meta_catalog_item_id', 'created_at']);
            $table->index(['meta_catalog_id', 'created_at']);
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_inventory_logs');
    }
};
