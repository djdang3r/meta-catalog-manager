<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_catalog_images', function (Blueprint $table) {
            $table->string('id', 26)->primary();

            $table->string('meta_catalog_item_id', 26);
            $table->foreign('meta_catalog_item_id')
                ->references('id')
                ->on('meta_catalog_items')
                ->onDelete('cascade');

            $table->enum('type', ['product_main', 'product_additional']);
            $table->unsignedTinyInteger('position')->default(0)
                ->comment('Orden dentro de additional_image_urls (0 para product_main)');

            // Origen
            $table->text('original_url')->comment('URL original del CDN de Meta');

            // Almacenamiento local
            $table->string('local_path')->nullable()->comment('Ruta relativa en storage (ej: meta-catalog/products/main/xxx.jpg)');
            $table->string('local_url')->nullable()->comment('URL pública via Storage::url()');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable()->comment('Tamaño en bytes');
            $table->timestamp('downloaded_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_catalog_images');
    }
};
