<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PasswordReset extends Model
{
    protected $fillable = [
        'email',
    ];

    protected static function booted()
    {
        static::creating(function ($passwordReset) {
            $passwordReset->token = Str::random(60);
            $passwordReset->expires_at = now()->addHour();
        });
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }
}
