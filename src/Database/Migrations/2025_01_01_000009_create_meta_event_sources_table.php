<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_event_sources', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meta_catalog_id', 26);
            $table->string('source_id', 50);
            $table->string('source_type', 10)->default('PIXEL');
            $table->string('name', 255)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_check_at')->nullable();
            $table->json('last_check_results')->nullable();
            $table->timestamps();

            $table->foreign('meta_catalog_id')
                ->references('id')
                ->on('meta_catalogs')
                ->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE meta_event_sources ADD CONSTRAINT chk_source_type CHECK (source_type IN ('PIXEL', 'APP'))");
            DB::statement("ALTER TABLE meta_event_sources ADD CONSTRAINT chk_source_status CHECK (status IN ('active', 'inactive'))");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE meta_event_sources ADD CONSTRAINT chk_source_type CHECK (source_type IN ('PIXEL', 'APP'))");
            DB::statement("ALTER TABLE meta_event_sources ADD CONSTRAINT chk_source_status CHECK (status IN ('active', 'inactive'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_event_sources');
    }
};