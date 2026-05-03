<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_catalog_images', function (Blueprint $table) {
            $table->char('id', 26)->primary();

            $table->char('meta_catalog_item_id', 26);
            $table->foreign('meta_catalog_item_id')
                ->references('id')
                ->on('meta_catalog_items')
                ->onDelete('cascade');

            $table->string('type', 30);
            $table->smallInteger('position')->default(0);

            // Origen
            $table->text('original_url');

            $table->string('local_path', 500)->nullable();
            $table->string('local_url', 500)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->timestamp('downloaded_at')->nullable();

            $table->timestamps();

            $table->softDeletes();        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE meta_catalog_images ADD CONSTRAINT chk_image_type CHECK (type IN ('product_main', 'product_additional'))");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE meta_catalog_images ADD CONSTRAINT chk_image_type CHECK (type IN ('product_main', 'product_additional'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_catalog_images');
    }
};