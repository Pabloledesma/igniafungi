<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Recipe extends Model
{
    use HasFactory;
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
    public function getEstimatedCost(float $totalHydratedWeight, int $units = 1): float
    {
        $totalCost = 0;

        foreach ($this->supplies as $supply) {
            $value = $supply->pivot->value;
            $mode = $supply->pivot->calculation_mode;
            $unitCost = $supply->cost_per_unit ?? 0;
            $consumedQuantity = 0;

            if ($mode === 'percentage') {
                // El value es porcentaje del peso total
                $consumedQuantity = ($totalHydratedWeight * $value) / 100;
            } elseif ($mode === 'fixed_per_unit') {
                // El value es cantidad fija por unidad de lote
                $consumedQuantity = $value * $units;
            }

            $totalCost += ($consumedQuantity * $unitCost);
        }

        return round($totalCost, 2);
    }
}
