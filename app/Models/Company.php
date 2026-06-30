<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'logo',
        'currency',
        'timezone',
    ];
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
