<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_catalog_offers', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meta_catalog_id', 26);
            $table->string('meta_offer_id', 100)->unique()->nullable();
            $table->string('offer_id', 255)->notNull();
            $table->string('title', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('application_type', 30);
            $table->string('value_type', 20);
            $table->string('fixed_amount_off', 50)->nullable();
            $table->smallInteger('percent_off')->nullable();
            $table->string('target_type', 20)->default('LINE_ITEM');
            $table->string('target_granularity', 20)->default('ITEM_LEVEL');
            $table->string('target_selection', 30)->default('ALL_CATALOG_PRODUCTS');
            $table->json('target_filter')->nullable();
            $table->json('target_product_retailer_ids')->nullable();
            $table->json('target_product_group_retailer_ids')->nullable();
            $table->json('target_product_set_retailer_ids')->nullable();
            $table->json('target_shipping_option_types')->nullable();
            $table->json('prerequisite_filter')->nullable();
            $table->json('prerequisite_product_retailer_ids')->nullable();
            $table->json('prerequisite_product_group_retailer_ids')->nullable();
            $table->json('prerequisite_product_set_retailer_ids')->nullable();
            $table->bigInteger('min_quantity')->default(0);
            $table->string('min_subtotal', 50)->nullable();
            $table->bigInteger('target_quantity')->default(0);
            $table->bigInteger('redemption_limit_per_order')->default(0);
            $table->json('coupon_codes')->nullable();
            $table->string('public_coupon_code', 20)->nullable();
            $table->bigInteger('redeem_limit_per_user')->default(0);
            $table->timestamp('start_date_time');
            $table->timestamp('end_date_time')->nullable();
            $table->boolean('exclude_sale_priced_products')->default(false);
            $table->text('offer_terms')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('meta_catalog_id')
                ->references('id')
                ->on('meta_catalogs')
                ->cascadeOnDelete();

            $table->index(['meta_catalog_id', 'status']);
            $table->index('offer_id');
            $table->index(['start_date_time', 'end_date_time']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE meta_catalog_offers ADD CONSTRAINT chk_application_type CHECK (application_type IN ('SALE', 'AUTOMATIC_AT_CHECKOUT', 'BUYER_APPLIED'))");
            DB::statement("ALTER TABLE meta_catalog_offers ADD CONSTRAINT chk_value_type CHECK (value_type IN ('FIXED_AMOUNT', 'PERCENTAGE'))");
            DB::statement("ALTER TABLE meta_catalog_offers ADD CONSTRAINT chk_target_type CHECK (target_type IN ('LINE_ITEM', 'SHIPPING'))");
            DB::statement("ALTER TABLE meta_catalog_offers ADD CONSTRAINT chk_target_granularity CHECK (target_granularity IN ('ITEM_LEVEL', 'ORDER_LEVEL'))");
            DB::statement("ALTER TABLE meta_catalog_offers ADD CONSTRAINT chk_target_selection CHECK (target_selection IN ('ALL_CATALOG_PRODUCTS', 'SPECIFIC_PRODUCTS'))");
            DB::statement("ALTER TABLE meta_catalog_offers ADD CONSTRAINT chk_offer_status CHECK (status IN ('active', 'inactive', 'expired'))");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE meta_catalog_offers ADD CONSTRAINT chk_application_type CHECK (application_type IN ('SALE', 'AUTOMATIC_AT_CHECKOUT', 'BUYER_APPLIED'))");
            DB::statement("ALTER TABLE meta_catalog_offers ADD CONSTRAINT chk_value_type CHECK (value_type IN ('FIXED_AMOUNT', 'PERCENTAGE'))");
            DB::statement("ALTER TABLE meta_catalog_offers ADD CONSTRAINT chk_target_type CHECK (target_type IN ('LINE_ITEM', 'SHIPPING'))");
            DB::statement("ALTER TABLE meta_catalog_offers ADD CONSTRAINT chk_target_granularity CHECK (target_granularity IN ('ITEM_LEVEL', 'ORDER_LEVEL'))");
            DB::statement("ALTER TABLE meta_catalog_offers ADD CONSTRAINT chk_target_selection CHECK (target_selection IN ('ALL_CATALOG_PRODUCTS', 'SPECIFIC_PRODUCTS'))");
            DB::statement("ALTER TABLE meta_catalog_offers ADD CONSTRAINT chk_offer_status CHECK (status IN ('active', 'inactive', 'expired'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_catalog_offers');
    }
};