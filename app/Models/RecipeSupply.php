<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeSupply extends Pivot
{
    /**
     * Indica si los IDs en la tabla pivote son incrementales.
     */
    public $incrementing = true;

    /**
     * Los atributos que se pueden asignar masivamente.
     */
    protected $fillable = [
        'recipe_id',
        'supply_id',
        'calculation_mode',
        'value',
    ];

    /**
     * Casteo de atributos para asegurar tipos de datos correctos.
     */
    protected $casts = [
        'value' => 'decimal:2',
    ];

    /**
     * Relación con la Receta.
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * Relación con el Insumo (Supply).
     */
    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class, 'supply_id');
    }
}