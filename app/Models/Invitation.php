<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Invitation extends Model
{
    protected $fillable = [
        'email',
        'role',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($invitation) {
            $invitation->company_id = auth()->user()->company_id;
            $invitation->token = Str::random(60);
            $invitation->expires_at = now()->addDays(7);
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
