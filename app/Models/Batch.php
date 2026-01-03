<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_batch_id', 
        'strain_id', 
        'code', 
        'substrate_weight_dry',
        'inoculation_date', 
        'quantity',
        'bag_weight',
        'type',
        'grain_type',
        'container_type',
        'status',
        'observations'
    ];

    protected $casts = [
        'inoculation_date' => 'date', // Esto convierte el texto en Objeto Fecha
    ];

    // Asignación automática de usuario al crear
    protected static function booted()
    {
        static::creating(function ($batch) {
            if (auth()->check()) {
                $batch->user_id = auth()->id();
            }
        });
    }

    public function strain() : BelongsTo
    {
        return $this->belongsTo(Strain::class);
    }

    // Relación: Un lote tiene muchas cosechas
    public function harvests(): HasMany
    {
        return $this->hasMany(Harvest::class);
    }

    public function user() 
    { 
        return $this->belongsTo(User::class); 
    }

    // Relación hacia el lote original (Padre)
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'parent_batch_id');
    }

    // Relación hacia los lotes derivados (Hijos/Fructificaciones)
    public function children(): HasMany
    {
        return $this->hasMany(Batch::class, 'parent_batch_id');
    }

    protected function biologicalEfficiency(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Si el peso seco es 0 o nulo, retornamos 0 para evitar error de división
                if ($this->substrate_weight_dry <= 0) return 0;

                // Sumamos los kilos de todas las cosechas
                $totalHarvest = $this->harvests()->sum('weight');

                // Fórmula: (Total Cosechado / Total Sustrato Seco) * 100
                return round(($totalHarvest / $this->substrate_weight_dry) * 100, 2);
            }
        );
    }
}
