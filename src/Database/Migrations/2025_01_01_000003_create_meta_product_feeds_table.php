<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_product_feeds', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meta_catalog_id', 26);
            $table->string('meta_feed_id', 50)->unique()->nullable(); // nullable para feeds aún no sincronizados con Meta
            $table->string('name', 255)->nullable();

            // Feed type & supplementary feed support
            $table->enum('ingestion_source_type', ['PRIMARY_FEED', 'SUPPLEMENTARY_FEED'])->default('PRIMARY_FEED');
            $table->json('primary_feed_ids')->nullable();   // solo para SUPPLEMENTARY_FEED: array de feed IDs primarios
            $table->boolean('update_only')->default(false); // true = no elimina items ausentes del feed

            // Replace schedule (full catalog refresh — sobrescribe TODO)
            $table->string('replace_schedule_url', 1000)->nullable();
            $table->enum('replace_schedule_interval', ['HOURLY', 'DAILY', 'WEEKLY'])->nullable();
            $table->tinyInteger('replace_schedule_hour')->unsigned()->nullable();   // 0-23
            $table->tinyInteger('replace_schedule_minute')->unsigned()->nullable(); // 0-59
            $table->string('replace_schedule_day_of_week', 20)->nullable();        // MONDAY...SUNDAY
            $table->timestamp('next_replace_upload_at')->nullable();
            $table->timestamp('last_replace_upload_at')->nullable();

            // Update schedule (incremental — solo crea/actualiza, no elimina)
            $table->string('update_schedule_url', 1000)->nullable();
            $table->enum('update_schedule_interval', ['HOURLY', 'DAILY', 'WEEKLY'])->nullable();
            $table->tinyInteger('update_schedule_hour')->unsigned()->nullable();
            $table->tinyInteger('update_schedule_minute')->unsigned()->nullable();
            $table->string('update_schedule_day_of_week', 20)->nullable();
            $table->timestamp('next_update_upload_at')->nullable();
            $table->timestamp('last_update_upload_at')->nullable();

            // Auth credentials (para feeds con basic HTTP/FTP auth) — ENCRIPTADOS a nivel modelo
            $table->text('feed_username')->nullable();
            $table->text('feed_password')->nullable();

            // Feed file info
            $table->string('file_name', 500)->nullable();
            $table->enum('format', ['csv', 'tsv', 'rss_xml', 'atom_xml', 'google_sheets'])->nullable();
            $table->string('encoding', 50)->nullable()->default('UTF-8');
            $table->string('delimiter', 10)->nullable();
            $table->enum('quoted_fields_mode', ['AUTO', 'ON', 'OFF'])->default('AUTO');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('meta_catalog_id')->references('id')->on('meta_catalogs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_product_feeds');
    }
};
