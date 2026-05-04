<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('meta_catalog_items', 'currency')) {
            Schema::table('meta_catalog_items', function (Blueprint $table): void {
                $table->string('currency', 10)->nullable()->after('sale_price');
            });
        }
    }

    public function down(): void
    {
        Schema::table('meta_catalog_items', function (Blueprint $table): void {
            $table->dropColumn('currency');
        });
    }
};
