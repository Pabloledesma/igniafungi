<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relation\BelongsTo;
use Illuminate\Database\Eloquent\Relation\HasMany;


class Product extends Model
{
    protected $fillable = [
        'category_id',
        'brand_id',
        'name', 
        'slug', 
        'description', 
        'price', 
        'images', 
        'is_active', 
        'is_featured', 
        'in_stock', 
        'on_sale'
    ];

    protected $casts = [
        'images' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
