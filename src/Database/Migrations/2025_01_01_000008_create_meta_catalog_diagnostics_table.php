<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_catalog_diagnostics', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meta_catalog_id', 26);
            $table->string('error_type', 255);
            $table->enum('severity', ['warning', 'error'])->default('error');
            $table->unsignedInteger('count')->default(0);
            $table->text('description')->nullable();
            $table->unsignedInteger('affected_items_count')->default(0);
            $table->json('samples')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->foreign('meta_catalog_id')
                ->references('id')
                ->on('meta_catalogs')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_catalog_diagnostics');
    }
};
