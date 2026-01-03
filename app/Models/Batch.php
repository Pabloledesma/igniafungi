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
        'user_id', 
        'code', 
        'weigth_dry',
        'inoculation_date', 
        'quantity',
        'contaminated_quantity',
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
            // 1. Asignación de usuario
            if (auth()->check()) {
                $batch->user_id = auth()->id();
            }

            // 2. Lógica de Código Inteligente
            if (!$batch->code) {
                // Si es un lote hijo (fructificación parcial)
                if ($batch->parent_batch_id) {
                    $parent = self::find($batch->parent_batch_id);
                    $childCount = self::where('parent_batch_id', $batch->parent_batch_id)->count() + 1;
                    $batch->code = $parent->code . '-F' . $childCount;
                } 
                // Si es un lote nuevo (Padre)
                else {
                    $prefix = strtoupper(substr($batch->strain?->name ?? 'BT', 0, 3));
                    $date = now()->format('ymd');
                    $random = rand(10, 99);
                    $batch->code = "{$prefix}-{$date}-{$random}";
                }
            }
            
        });

        static::updating(function ($batch) {
        // Lógica Global de Finalización Automática
        if ($batch->isDirty('quantity') && (int)$batch->quantity === 0) {
            $batch->status = 'finalized';
            
            // Opcional: Agregar nota automática a la bitácora si no existe
            $now = now()->format('Y-m-d H:i');
            $user_name = auth()->user()->name;
            if (!str_contains($batch->observations, 'LOTE FINALIZADO')) {
                $batch->observations .= "\n- [{$now}] {$user_name}: El lote ha llegado a 0 unidades y se ha finalizado automáticamente.";
            }
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
                if ($this->weigth_dry <= 0) return 0;

                // Sumamos los kilos de todas las cosechas
                $totalHarvest = $this->harvests()->sum('weight');

                // Fórmula: (Total Cosechado / Total Sustrato Seco) * 100
                return round(($totalHarvest / $this->weigth_dry) * 100, 2);
            }
        );
    }
}
