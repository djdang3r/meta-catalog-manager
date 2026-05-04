<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('meta_catalogs', 'auto_sync_enabled')) {
            Schema::table('meta_catalogs', function (Blueprint $table): void {
                $table->boolean('auto_sync_enabled')->default(false)->after('status');
                $table->timestamp('last_synced_at')->nullable()->after('auto_sync_enabled');
            });
        }
    }

    public function down(): void
    {
        Schema::table('meta_catalogs', function (Blueprint $table): void {
            $table->dropColumn(['auto_sync_enabled', 'last_synced_at']);
        });
    }
};
