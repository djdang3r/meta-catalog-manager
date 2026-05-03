<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'meta_catalog_items',
        'meta_product_feed_uploads',
        'meta_batch_requests',
        'meta_catalog_diagnostics',
        'meta_event_sources',
        'meta_event_stats',
        'meta_inventory_logs',
        'meta_catalog_images',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $table): void {
                    $table->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
    }
};