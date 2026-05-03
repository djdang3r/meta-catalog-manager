<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_catalog_items', function (Blueprint $table) {
            // Core fields
            $table->char('id', 26)->primary();
            $table->char('meta_catalog_id', 26);
            $table->char('meta_product_feed_id', 26)->nullable();
            $table->string('meta_product_item_id', 100)->unique()->nullable();
            $table->string('retailer_id', 255);
            $table->string('item_type', 20)->default('PRODUCT_ITEM');

            // Basic product info
            $table->string('title', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('brand', 255)->nullable();
            $table->string('category', 500)->nullable();
            $table->string('item_group_id', 255)->nullable();

            // Pricing
            $table->string('price', 50)->nullable();
            $table->string('sale_price', 50)->nullable();
            $table->string('sale_price_effective_date', 100)->nullable();

            // Availability & Status
            $table->string('availability', 30)->default('in stock');
            $table->string('condition', 20)->default('new');
            $table->bigInteger('quantity_to_sell_on_facebook')->nullable();

            // Images
            $table->string('image_url', 1000)->nullable();
            $table->json('additional_image_urls')->nullable();

            // Links
            $table->string('link', 1000)->nullable();
            $table->string('mobile_link', 1000)->nullable();

            // Variants
            $table->string('color', 255)->nullable();
            $table->string('size', 255)->nullable();
            $table->string('gender', 50)->nullable();
            $table->string('age_group', 50)->nullable();
            $table->string('material', 255)->nullable();
            $table->string('pattern', 255)->nullable();
            $table->json('additional_variant_attribute')->nullable();

            // Custom labels
            $table->string('custom_label_0', 255)->nullable();
            $table->string('custom_label_1', 255)->nullable();
            $table->string('custom_label_2', 255)->nullable();
            $table->string('custom_label_3', 255)->nullable();
            $table->string('custom_label_4', 255)->nullable();

            // Shipping
            $table->json('shipping')->nullable();
            $table->string('shipping_weight', 50)->nullable();

            // Product Categories
            $table->string('fb_product_category', 500)->nullable();
            $table->string('google_product_category', 500)->nullable();
            $table->string('gtin', 100)->nullable();
            $table->string('mpn', 100)->nullable();

            // Rich text description
            $table->text('rich_text_description')->nullable();

            // Product type (internal categorization)
            $table->string('product_type', 750)->nullable();

            // App Links (Deep Links)
            $table->json('app_links')->nullable();

            // Internal labels
            $table->json('internal_label')->nullable();

            // Status & errors
            $table->string('visibility', 20)->default('published');
            $table->string('review_status', 50)->nullable();
            $table->json('errors')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->foreign('meta_catalog_id')
                ->references('id')
                ->on('meta_catalogs')
                ->cascadeOnDelete();

            $table->foreign('meta_product_feed_id')
                ->references('id')
                ->on('meta_product_feeds')
                ->nullOnDelete();

            // Indexes
            $table->index(['meta_catalog_id', 'retailer_id']);
            $table->index('availability');
            $table->index('item_type');
            $table->index('fb_product_category');
            $table->index('gtin');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE meta_catalog_items ADD CONSTRAINT chk_item_type CHECK (item_type IN ('PRODUCT_ITEM', 'VEHICLE', 'HOTEL', 'HOTEL_ROOM', 'FLIGHT', 'DESTINATION', 'HOME_LISTING', 'VEHICLE_OFFER'))");
            DB::statement("ALTER TABLE meta_catalog_items ADD CONSTRAINT chk_availability CHECK (availability IN ('in stock', 'out of stock', 'preorder', 'available for order', 'discontinued'))");
            DB::statement("ALTER TABLE meta_catalog_items ADD CONSTRAINT chk_condition CHECK (condition IN ('new', 'refurbished', 'used'))");
            DB::statement("ALTER TABLE meta_catalog_items ADD CONSTRAINT chk_visibility CHECK (visibility IN ('published', 'staging'))");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE meta_catalog_items ADD CONSTRAINT chk_item_type CHECK (item_type IN ('PRODUCT_ITEM', 'VEHICLE', 'HOTEL', 'HOTEL_ROOM', 'FLIGHT', 'DESTINATION', 'HOME_LISTING', 'VEHICLE_OFFER'))");
            DB::statement("ALTER TABLE meta_catalog_items ADD CONSTRAINT chk_availability CHECK (availability IN ('in stock', 'out of stock', 'preorder', 'available for order', 'discontinued'))");
            DB::statement("ALTER TABLE meta_catalog_items ADD CONSTRAINT chk_condition CHECK (condition IN ('new', 'refurbished', 'used'))");
            DB::statement("ALTER TABLE meta_catalog_items ADD CONSTRAINT chk_visibility CHECK (visibility IN ('published', 'staging'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_catalog_items');
    }
};