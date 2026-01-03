<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Recipe extends Model
{
    protected $fillable = ['name', 'type'];

    public function supplies(): BelongsToMany
    {
        return $this->belongsToMany(Supply::class, 'recipe_supply')
                    ->using(RecipeSupply::class) // <--- Importante
                    ->withPivot(['calculation_mode', 'value'])
                    ->withTimestamps();
    }

    // En Recipe.php
    public function recipeSupplies(): HasMany
    {
        return $this->hasMany(RecipeSupply::class);
    }
}
