<?php

use App\Models\InvoiceReminder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('api_key_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->timestamp('scheduled_for');
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default(InvoiceReminder::StatusPending);
            $table->string('channel')->default('email');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();

            $table->unique(['invoice_id', 'sequence']);
            $table->index(['api_key_id', 'status', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_reminders');
    }
};
