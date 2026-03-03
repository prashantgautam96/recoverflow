<?php

use App\Models\Invoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number');
            $table->char('currency', 3)->default('USD');
            $table->unsignedInteger('amount_cents');
            $table->date('issued_at');
            $table->date('due_at');
            $table->string('status')->default(Invoice::StatusPending);
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_url')->nullable();
            $table->decimal('late_fee_percent', 5, 2)->default(0);
            $table->timestamp('last_reminder_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['api_key_id', 'invoice_number']);
            $table->index(['api_key_id', 'status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
