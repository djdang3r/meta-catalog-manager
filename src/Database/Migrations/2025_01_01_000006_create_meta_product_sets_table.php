<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_product_sets', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meta_catalog_id', 26);
            $table->string('meta_product_set_id', 50)->unique();
            $table->string('name', 255)->nullable();
            $table->json('filter')->nullable();
            $table->unsignedInteger('product_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('meta_catalog_id')
                ->references('id')
                ->on('meta_catalogs')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_product_sets');
    }
};
