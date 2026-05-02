<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_product_feeds', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meta_catalog_id', 26);
            $table->string('meta_feed_id', 50)->unique()->nullable();
            $table->string('name', 255)->nullable();

            $table->string('ingestion_source_type', 30)->default('PRIMARY_FEED');
            $table->json('primary_feed_ids')->nullable();
            $table->boolean('update_only')->default(false);

            $table->string('replace_schedule_url', 1000)->nullable();
            $table->string('replace_schedule_interval', 20)->nullable();
            $table->smallInteger('replace_schedule_hour')->nullable();
            $table->smallInteger('replace_schedule_minute')->nullable();
            $table->string('replace_schedule_day_of_week', 20)->nullable();
            $table->timestamp('next_replace_upload_at')->nullable();
            $table->timestamp('last_replace_upload_at')->nullable();

            $table->string('update_schedule_url', 1000)->nullable();
            $table->string('update_schedule_interval', 20)->nullable();
            $table->smallInteger('update_schedule_hour')->nullable();
            $table->smallInteger('update_schedule_minute')->nullable();
            $table->string('update_schedule_day_of_week', 20)->nullable();
            $table->timestamp('next_update_upload_at')->nullable();
            $table->timestamp('last_update_upload_at')->nullable();

            // Auth credentials for HTTP/FTP feeds
            $table->text('feed_username')->nullable();
            $table->text('feed_password')->nullable();

            $table->string('file_name', 500)->nullable();
            $table->string('format', 20)->nullable();
            $table->string('encoding', 50)->nullable()->default('UTF-8');
            $table->string('delimiter', 10)->nullable();
            $table->string('quoted_fields_mode', 10)->default('AUTO');

            $table->string('override_type', 30)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('meta_catalog_id')->references('id')->on('meta_catalogs')->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE meta_product_feeds ADD CONSTRAINT chk_ingestion_source_type CHECK (ingestion_source_type IN ('PRIMARY_FEED', 'SUPPLEMENTARY_FEED'))");
            DB::statement("ALTER TABLE meta_product_feeds ADD CONSTRAINT chk_replace_interval CHECK (replace_schedule_interval IN ('HOURLY', 'DAILY', 'WEEKLY') OR replace_schedule_interval IS NULL)");
            DB::statement("ALTER TABLE meta_product_feeds ADD CONSTRAINT chk_update_interval CHECK (update_schedule_interval IN ('HOURLY', 'DAILY', 'WEEKLY') OR update_schedule_interval IS NULL)");
            DB::statement("ALTER TABLE meta_product_feeds ADD CONSTRAINT chk_format CHECK (format IN ('csv', 'tsv', 'rss_xml', 'atom_xml', 'google_sheets') OR format IS NULL)");
            DB::statement("ALTER TABLE meta_product_feeds ADD CONSTRAINT chk_quoted_fields_mode CHECK (quoted_fields_mode IN ('AUTO', 'ON', 'OFF'))");
            DB::statement("ALTER TABLE meta_product_feeds ADD CONSTRAINT chk_override_type CHECK (override_type IN ('language', 'country', 'language_and_country') OR override_type IS NULL)");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE meta_product_feeds ADD CONSTRAINT chk_ingestion_source_type CHECK (ingestion_source_type IN ('PRIMARY_FEED', 'SUPPLEMENTARY_FEED'))");
            DB::statement("ALTER TABLE meta_product_feeds ADD CONSTRAINT chk_replace_interval CHECK (replace_schedule_interval IN ('HOURLY', 'DAILY', 'WEEKLY') OR replace_schedule_interval IS NULL)");
            DB::statement("ALTER TABLE meta_product_feeds ADD CONSTRAINT chk_update_interval CHECK (update_schedule_interval IN ('HOURLY', 'DAILY', 'WEEKLY') OR update_schedule_interval IS NULL)");
            DB::statement("ALTER TABLE meta_product_feeds ADD CONSTRAINT chk_format CHECK (format IN ('csv', 'tsv', 'rss_xml', 'atom_xml', 'google_sheets') OR format IS NULL)");
            DB::statement("ALTER TABLE meta_product_feeds ADD CONSTRAINT chk_quoted_fields_mode CHECK (quoted_fields_mode IN ('AUTO', 'ON', 'OFF'))");
            DB::statement("ALTER TABLE meta_product_feeds ADD CONSTRAINT chk_override_type CHECK (override_type IN ('language', 'country', 'language_and_country') OR override_type IS NULL)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_product_feeds');
    }
};