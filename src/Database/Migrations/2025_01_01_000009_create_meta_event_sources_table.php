<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_event_sources', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meta_catalog_id', 26);
            $table->string('source_id', 50);
            $table->enum('source_type', ['PIXEL', 'APP'])->default('PIXEL');
            $table->string('name', 255)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('last_check_at')->nullable();
            $table->json('last_check_results')->nullable();
            $table->timestamps();

            $table->foreign('meta_catalog_id')
                ->references('id')
                ->on('meta_catalogs')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_event_sources');
    }
};
