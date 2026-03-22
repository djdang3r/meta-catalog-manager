<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->enum('status', ['active', 'disconnected', 'removed'])->default('active');
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamp('fully_removed_at')->nullable();
            $table->text('disconnection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_business_accounts');
    }
};
