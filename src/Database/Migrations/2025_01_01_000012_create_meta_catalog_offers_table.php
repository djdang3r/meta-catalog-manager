<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_catalog_offers', function (Blueprint $table) {
            $table->char('id', 26)->primary();

            // Relación con el catálogo
            $table->char('meta_catalog_id', 26);

            // IDs
            $table->string('meta_offer_id', 100)->unique()->nullable();  // ID de Meta
            $table->string('offer_id', 255)->notNull();                  // ID del vendedor (retailer_id equivalent)

            // Información básica
            $table->string('title', 500)->nullable();
            $table->text('description')->nullable();

            // Tipo de aplicación de la oferta
            $table->enum('application_type', ['SALE', 'AUTOMATIC_AT_CHECKOUT', 'BUYER_APPLIED']);

            // Tipo y valor del descuento
            $table->enum('value_type', ['FIXED_AMOUNT', 'PERCENTAGE']);
            $table->string('fixed_amount_off', 50)->nullable();       // "30.99 USD"
            $table->unsignedTinyInteger('percent_off')->nullable();   // 0-100

            // Target
            $table->enum('target_type', ['LINE_ITEM', 'SHIPPING'])->default('LINE_ITEM');
            $table->enum('target_granularity', ['ITEM_LEVEL', 'ORDER_LEVEL'])->default('ITEM_LEVEL');
            $table->enum('target_selection', ['ALL_CATALOG_PRODUCTS', 'SPECIFIC_PRODUCTS'])->default('ALL_CATALOG_PRODUCTS');
            $table->json('target_filter')->nullable();                              // product set filter rules
            $table->json('target_product_retailer_ids')->nullable();               // array de retailer IDs
            $table->json('target_product_group_retailer_ids')->nullable();
            $table->json('target_product_set_retailer_ids')->nullable();
            $table->json('target_shipping_option_types')->nullable();              // ['STANDARD','RUSH','EXPEDITED']

            // Prerrequisitos (Buy X Get Y)
            $table->json('prerequisite_filter')->nullable();
            $table->json('prerequisite_product_retailer_ids')->nullable();
            $table->json('prerequisite_product_group_retailer_ids')->nullable();
            $table->json('prerequisite_product_set_retailer_ids')->nullable();
            $table->unsignedInteger('min_quantity')->default(0);
            $table->string('min_subtotal', 50)->nullable();                        // "30.99 USD"

            // Buy X Get Y específicos
            $table->unsignedInteger('target_quantity')->default(0);
            $table->unsignedInteger('redemption_limit_per_order')->default(0);

            // Códigos de cupón
            $table->json('coupon_codes')->nullable();                              // array de códigos (max 100)
            $table->string('public_coupon_code', 20)->nullable();
            $table->unsignedInteger('redeem_limit_per_user')->default(0);

            // Vigencia
            $table->timestamp('start_date_time');
            $table->timestamp('end_date_time')->nullable();
            $table->boolean('exclude_sale_priced_products')->default(false);

            // Términos y condiciones
            $table->text('offer_terms')->nullable();                               // max 2500 chars

            // Estado
            $table->enum('status', ['active', 'inactive', 'expired'])->default('active');

            $table->timestamps();
            $table->softDeletes();

            // Foreign key
            $table->foreign('meta_catalog_id')
                ->references('id')
                ->on('meta_catalogs')
                ->cascadeOnDelete();

            // Indexes
            $table->index(['meta_catalog_id', 'status']);
            $table->index('offer_id');
            $table->index(['start_date_time', 'end_date_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_catalog_offers');
    }
};
