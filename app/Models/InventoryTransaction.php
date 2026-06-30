<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    protected $fillable = [
        'product_id',
        'type',
        'quantity',
        'notes',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($transaction) {
            $transaction->company_id = auth()->user()->company_id;
            $transaction->created_by = auth()->user()->id;
        });

        static::created(function ($transaction) {
            $transaction->product->increment('current_stock', $transaction->quantity);
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
