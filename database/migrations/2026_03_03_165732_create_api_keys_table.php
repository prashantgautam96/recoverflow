<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('owner_email')->nullable();
            $table->string('plan')->default('starter');
            $table->unsignedInteger('monthly_quota')->default(5000);
            $table->unsignedInteger('used_this_month')->default(0);
            $table->string('key_hash')->unique();
            $table->boolean('active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['active', 'plan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
