<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_product_feeds', function (Blueprint $table) {
            // Tipo de reemplazo para catálogos localizados.
            // null = feed normal (primario o suplementario).
            // 'language' | 'country' | 'language_and_country' = feed de localización.
            $table->enum('override_type', ['language', 'country', 'language_and_country'])
                  ->nullable()
                  ->after('ingestion_source_type');
        });
    }

    public function down(): void
    {
        Schema::table('meta_product_feeds', function (Blueprint $table) {
            $table->dropColumn('override_type');
        });
    }
};
