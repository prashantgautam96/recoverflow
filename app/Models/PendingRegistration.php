<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendingRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
        'otp_hash',
        'otp_expires_at',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'otp_expires_at' => 'datetime',
        ];
    }

    public static function hashOtp(string $email, string $otp): string
    {
        return hash('sha256', strtolower(trim($email)).'|'.trim($otp));
    }
}
