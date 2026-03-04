<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_reminders', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('sequence');
            $table->index(['status', 'scheduled_for'], 'invoice_reminders_status_scheduled_for_index');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_reminders', function (Blueprint $table) {
            $table->dropIndex('invoice_reminders_status_scheduled_for_index');
            $table->dropColumn('attempts');
        });
    }
};
