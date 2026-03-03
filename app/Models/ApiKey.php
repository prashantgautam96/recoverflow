<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'owner_email',
        'plan',
        'monthly_quota',
        'used_this_month',
        'key_hash',
        'active',
        'last_used_at',
    ];

    protected $hidden = [
        'key_hash',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public static function generatePlainTextKey(): string
    {
        return 'rf_live_'.Str::lower(Str::random(40));
    }

    public static function hashPlainTextKey(string $plainTextKey): string
    {
        return hash('sha256', trim($plainTextKey));
    }

    public static function findActiveByPlainTextKey(string $plainTextKey): ?self
    {
        return self::query()
            ->where('key_hash', self::hashPlainTextKey($plainTextKey))
            ->where('active', true)
            ->first();
    }

    public function currentUsageForMonth(): int
    {
        if ($this->last_used_at === null) {
            return 0;
        }

        if (! $this->last_used_at->isSameMonth(now())) {
            return 0;
        }

        return $this->used_this_month;
    }

    public function hasReachedQuota(): bool
    {
        return $this->currentUsageForMonth() >= $this->monthly_quota;
    }

    public function registerUsage(): void
    {
        $this->forceFill([
            'used_this_month' => $this->currentUsageForMonth() + 1,
            'last_used_at' => now(),
        ])->save();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function invoiceReminders(): HasMany
    {
        return $this->hasMany(InvoiceReminder::class);
    }
}
