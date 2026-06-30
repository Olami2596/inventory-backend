<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'supplier_id',
        'name',
        'sku',
        'description',
        'price',
        'cost',
        'image_url',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            $model->company_id = auth()->user()->company_id;
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
