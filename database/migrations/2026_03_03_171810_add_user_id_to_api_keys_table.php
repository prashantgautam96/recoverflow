<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('api_keys', 'user_id')) {
            Schema::table('api_keys', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
                $table->index(['user_id', 'plan']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('api_keys', 'user_id')) {
            Schema::table('api_keys', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};
