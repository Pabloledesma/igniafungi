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
        'short_description', 
        'description', 
        'price', 
        'images', 
        'is_active', 
        'is_featured', 
        'in_stock', 
        'stock', 
        'on_sale'
    ];

    protected $casts = [
        'images' => 'array',
    ];

    // Accessor para no romper el frontend que use $product->in_stock
    public function getInStockAttribute(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Método para reducir stock de forma segura
     */
    public function reduceStock(int $quantity)
    {
        if ($this->stock < $quantity) {
            throw new \Exception("Stock insuficiente para el producto: {$this->name}");
        }

        $this->decrement('stock', $quantity);
    }

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
