<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_catalog_diagnostics', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meta_catalog_id', 26);
            $table->string('error_type', 255);
            $table->string('severity', 20)->default('error');
            $table->bigInteger('count')->default(0);
            $table->text('description')->nullable();
            $table->bigInteger('affected_items_count')->default(0);
            $table->json('samples')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->softDeletes();
            $table->foreign('meta_catalog_id')
                ->references('id')
                ->on('meta_catalogs')
                ->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE meta_catalog_diagnostics ADD CONSTRAINT chk_diagnostic_severity CHECK (severity IN ('warning', 'error'))");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE meta_catalog_diagnostics ADD CONSTRAINT chk_diagnostic_severity CHECK (severity IN ('warning', 'error'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_catalog_diagnostics');
    }
};