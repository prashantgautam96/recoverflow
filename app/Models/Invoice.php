<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    public const StatusPending = 'pending';

    public const StatusPaid = 'paid';

    public const StatusOverdue = 'overdue';

    protected $fillable = [
        'api_key_id',
        'client_id',
        'invoice_number',
        'currency',
        'amount_cents',
        'issued_at',
        'due_at',
        'status',
        'paid_at',
        'payment_url',
        'webhook_secret',
        'late_fee_percent',
        'last_reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'issued_at' => 'date',
            'due_at' => 'date',
            'paid_at' => 'datetime',
            'late_fee_percent' => 'decimal:2',
            'last_reminder_sent_at' => 'datetime',
        ];
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(InvoiceReminder::class);
    }
}
