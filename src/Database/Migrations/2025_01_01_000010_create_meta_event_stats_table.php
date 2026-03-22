<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_event_stats', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meta_event_source_id', 26);
            $table->date('date_start');
            $table->date('date_stop');
            $table->string('event', 100);
            $table->string('device_type', 50)->nullable();
            $table->unsignedBigInteger('total_matched_content_ids')->default(0);
            $table->unsignedBigInteger('total_content_ids_matched_other_catalogs')->default(0);
            $table->unsignedBigInteger('total_unmatched_content_ids')->default(0);
            $table->unsignedBigInteger('unique_matched_content_ids')->default(0);
            $table->unsignedBigInteger('unique_content_ids_matched_other_catalogs')->default(0);
            $table->unsignedBigInteger('unique_unmatched_content_ids')->default(0);
            $table->timestamps();

            $table->foreign('meta_event_source_id')
                ->references('id')
                ->on('meta_event_sources')
                ->cascadeOnDelete();

            $table->index(['meta_event_source_id', 'date_start', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_event_stats');
    }
};
