<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_business_accounts', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('meta_business_id', 50)->unique();
            $table->string('name', 255)->nullable();
            $table->text('app_id')->nullable();
            $table->text('app_secret')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamp('fully_removed_at')->nullable();
            $table->text('disconnection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE meta_business_accounts ADD CONSTRAINT chk_meta_business_accounts_status CHECK (status IN ('active', 'disconnected', 'removed'))");
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE meta_business_accounts ADD CONSTRAINT chk_meta_business_accounts_status CHECK (status IN ('active', 'disconnected', 'removed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_business_accounts');
    }
};