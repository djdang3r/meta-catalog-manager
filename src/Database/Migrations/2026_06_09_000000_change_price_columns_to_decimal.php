<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_catalog_items', function (Blueprint $table) {
            $table->bigInteger('price_new')->nullable()->after('item_group_id');
            $table->bigInteger('sale_price_new')->nullable()->after('price_new');
        });

        DB::statement("UPDATE meta_catalog_items SET price_new = CAST(NULLIF(price, '') AS BIGINT) WHERE price IS NOT NULL AND price != ''");
        DB::statement("UPDATE meta_catalog_items SET sale_price_new = CAST(NULLIF(sale_price, '') AS BIGINT) WHERE sale_price IS NOT NULL AND sale_price != ''");

        Schema::table('meta_catalog_items', function (Blueprint $table) {
            $table->dropColumn(['price', 'sale_price']);
            $table->renameColumn('price_new', 'price');
            $table->renameColumn('sale_price_new', 'sale_price');
        });
    }

    public function down(): void
    {
        Schema::table('meta_catalog_items', function (Blueprint $table) {
            $table->string('price_old')->nullable()->after('item_group_id');
            $table->string('sale_price_old')->nullable()->after('price_old');
        });

        DB::statement('UPDATE meta_catalog_items SET price_old = CAST(price AS CHAR) WHERE price IS NOT NULL');
        DB::statement('UPDATE meta_catalog_items SET sale_price_old = CAST(sale_price AS CHAR) WHERE sale_price IS NOT NULL');

        Schema::table('meta_catalog_items', function (Blueprint $table) {
            $table->dropColumn(['price', 'sale_price']);
            $table->renameColumn('price_old', 'price');
            $table->renameColumn('sale_price_old', 'sale_price');
        });
    }
};
