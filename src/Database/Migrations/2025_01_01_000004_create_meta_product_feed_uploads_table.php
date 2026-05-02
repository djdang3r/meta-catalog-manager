<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_product_feed_uploads', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meta_product_feed_id', 26);
            $table->string('meta_upload_session_id', 100)->nullable();
            $table->string('status', 20)->default('in_progress');
            $table->bigInteger('num_detected_items')->default(0);
            $table->bigInteger('num_persisted_items')->default(0);
            $table->bigInteger('num_deleted_items')->default(0);
            $table->bigInteger('error_count')->default(0);
            $table->bigInteger('warning_count')->default(0);
            $table->string('error_report_url', 1000)->nullable();
            $table->string('upload_url', 1000)->nullable();
            $table->boolean('update_only')->default(false);
            $table->string('error_report_status', 50)->nullable();
            $table->string('upload_type', 20)->default('url');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('meta_product_feed_id')
                ->references('id')
                ->on('meta_product_feeds')
                ->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE meta_product_feed_uploads ADD CONSTRAINT chk_upload_status CHECK (status IN ('in_progress', 'complete', 'failed', 'cancelled'))");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE meta_product_feed_uploads ADD CONSTRAINT chk_upload_status CHECK (status IN ('in_progress', 'complete', 'failed', 'cancelled'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_product_feed_uploads');
    }
};