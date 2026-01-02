<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'category_id',
        'strain_id',
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

    public function strain(): BelongsTo
    {
        return $this->belongsTo(Strain::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getFirstImageAttribute()
    {
        if (empty($this->images) || !is_array($this->images)) {
            return 'public/storage/upload/Imagen-interrogante-2.png'; // Ruta relativa a public
        }

        return $this->images[0];
    }
}
