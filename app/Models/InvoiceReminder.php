<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceReminder extends Model
{
    use HasFactory;

    public const StatusPending = 'pending';

    public const StatusSent = 'sent';

    public const StatusSkipped = 'skipped';

    public const StatusFailed = 'failed';

    public const MaxAttempts = 3;

    protected $fillable = [
        'invoice_id',
        'api_key_id',
        'sequence',
        'attempts',
        'scheduled_for',
        'sent_at',
        'status',
        'channel',
        'subject',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }
}
