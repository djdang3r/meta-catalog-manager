<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('meta_catalog_items', 'image_cdn_url')) {
            Schema::table('meta_catalog_items', function (Blueprint $table): void {
                // Copia de la imagen en el CDN de Meta (`image_cdn_urls`). Se usa
                // para descargar cuando la URL del merchant (`image_url`) está
                // caída (p. ej. un storage propio ya limpiado). Meta la refresca
                // en cada lectura, así que es válida en el momento del sync.
                $table->string('image_cdn_url', 2000)->nullable()->after('image_url');
            });
        }
    }

    public function down(): void
    {
        Schema::table('meta_catalog_items', function (Blueprint $table): void {
            $table->dropColumn('image_cdn_url');
        });
    }
};
