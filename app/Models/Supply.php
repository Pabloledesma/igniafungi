<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Supply extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'category', 'quantity', 'unit', 'min_stock', 'cost_per_unit']; 

    protected $casts = [
        'quantity' => 'float'
    ];

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_supply')
                    ->using(RecipeSupply::class) // <--- Importante
                    ->withPivot(['calculation_mode', 'value'])
                    ->withTimestamps();
    }
}