<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_generic_feeds', function (Blueprint $table) {
            $table->char('id', 26)->primary();

            // Relación con el catálogo
            $table->char('meta_catalog_id', 26);

            // IDs externos
            $table->string('meta_feed_id', 100)->unique()->nullable();                // ID del feed en Meta
            $table->string('commerce_partner_integration_id', 100)->nullable();       // para Generic Feed Files API

            // Tipo de feed genérico
            $table->enum('feed_type', [
                'PROMOTIONS',
                'PRODUCT_RATINGS_AND_REVIEWS',
                'SHIPPING_PROFILES',
                'NAVIGATION_MENU',
                'OFFER',
            ]);

            // Nombre descriptivo
            $table->string('name', 255)->nullable();

            // Último upload
            $table->timestamp('last_upload_at')->nullable();
            $table->string('last_upload_status', 50)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign key
            $table->foreign('meta_catalog_id')
                ->references('id')
                ->on('meta_catalogs')
                ->cascadeOnDelete();

            // Index compuesto para queries por catálogo + tipo
            $table->index(['meta_catalog_id', 'feed_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_generic_feeds');
    }
};
