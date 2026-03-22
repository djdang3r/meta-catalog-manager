<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_product_feed_uploads', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meta_product_feed_id', 26);
            $table->string('meta_upload_session_id', 100)->nullable();
            $table->enum('status', ['in_progress', 'complete', 'failed', 'cancelled'])->default('in_progress');
            $table->unsignedInteger('num_detected_items')->default(0);
            $table->unsignedInteger('num_persisted_items')->default(0);
            $table->unsignedInteger('num_deleted_items')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->string('error_report_url', 1000)->nullable();
            $table->string('upload_url', 1000)->nullable();
            $table->boolean('update_only')->default(false);         // si fue upload con update_only=true
            $table->string('error_report_status', 50)->nullable();  // PENDING, WRITE_FINISHED, WRITE_FAILED
            $table->string('upload_type', 20)->default('url');      // 'url' o 'file'
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('meta_product_feed_id')
                ->references('id')
                ->on('meta_product_feeds')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_product_feed_uploads');
    }
};
