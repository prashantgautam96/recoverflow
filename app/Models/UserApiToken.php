<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserApiToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'token_hash',
        'last_used_at',
        'expires_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return array{0:self,1:string}
     */
    public static function issueForUser(User $user, string $name = 'web'): array
    {
        $plainTextToken = 'rf_user_'.Str::lower(Str::random(50));

        $token = self::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => self::hashPlainTextToken($plainTextToken),
            'expires_at' => now()->addDays((int) config('recoverflow.auth_token_ttl_days', 30)),
        ]);

        return [$token, $plainTextToken];
    }

    public static function hashPlainTextToken(string $plainTextToken): string
    {
        return hash('sha256', trim($plainTextToken));
    }

    public static function findValidByPlainTextToken(string $plainTextToken): ?self
    {
        return self::query()
            ->where('token_hash', self::hashPlainTextToken($plainTextToken))
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
