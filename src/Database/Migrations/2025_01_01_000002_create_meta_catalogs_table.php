<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_catalogs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('meta_business_account_id', 26);
            $table->string('meta_catalog_id', 50)->unique();
            $table->string('name', 255)->nullable();
            $table->string('vertical', 30)->default('commerce');
            $table->string('country', 10)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('timezone_id', 100)->nullable();
            $table->bigInteger('product_count')->default(0);
            $table->boolean('is_catalog_segment')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('meta_business_account_id')
                ->references('id')
                ->on('meta_business_accounts')
                ->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE meta_catalogs ADD CONSTRAINT chk_meta_catalogs_vertical CHECK (vertical IN ('adoptable_pets', 'apps_and_software', 'articles_and_publications', 'commerce', 'destinations', 'flights', 'generic', 'home_listings', 'hotels', 'local_service_businesses', 'media_titles', 'offer_items', 'offline_commerce', 'professional_services', 'transactable_items', 'vehicles'))");
            DB::statement("ALTER TABLE meta_catalogs ADD CONSTRAINT chk_meta_catalogs_status CHECK (status IN ('active', 'inactive'))");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE meta_catalogs ADD CONSTRAINT chk_meta_catalogs_vertical CHECK (vertical IN ('adoptable_pets', 'apps_and_software', 'articles_and_publications', 'commerce', 'destinations', 'flights', 'generic', 'home_listings', 'hotels', 'local_service_businesses', 'media_titles', 'offer_items', 'offline_commerce', 'professional_services', 'transactable_items', 'vehicles'))");
            DB::statement("ALTER TABLE meta_catalogs ADD CONSTRAINT chk_meta_catalogs_status CHECK (status IN ('active', 'inactive'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_catalogs');
    }
};