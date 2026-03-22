<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_catalogs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meta_business_account_id', 26);
            $table->string('meta_catalog_id', 50)->unique();
            $table->string('name', 255)->nullable();
            $table->enum('vertical', [
                'commerce',
                'vehicles',
                'hotels',
                'flights',
                'destinations',
                'home_listings',
                'vehicle_offers',
            ])->default('commerce');
            $table->string('country', 10)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('timezone_id', 100)->nullable();
            $table->unsignedInteger('product_count')->default(0);
            $table->boolean('is_catalog_segment')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('meta_business_account_id')
                ->references('id')
                ->on('meta_business_accounts')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_catalogs');
    }
};
