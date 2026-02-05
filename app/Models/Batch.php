<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\BatchLoss;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_batch_id',
        'strain_id',
        'recipe_id',
        'user_id',
        'code',
        'initial_wet_weight',
        'inoculation_date',
        'quantity',
        'contaminated_quantity',
        'bag_weight',
        'type',
        'grain_type',
        'container_type',
        'status',
        'observations',
        'production_cost',
        'expected_yield',
        'estimated_harvest_date',
        'is_historical',
        'origin_code'
    ];

    public $lossReason = null;
    public $lossDetails = null;
    public $phase_id;

    public function discard($reason, $qty, $details = null)
    {
        return DB::transaction(function () use ($reason, $qty, $details) {
            // 1. Validate quantity
            if ($qty > $this->quantity) {
                throw new \Exception("No puedes descartar más de lo que existe ({$this->quantity}).");
            }

            // 2. Set transient properties for Observer
            $this->lossReason = $reason;
            $this->lossDetails = $details;

            // 3. Update quantities manually on the instance
            // (Avoid using decrement/increment directly so properties persist to Observer)
            $this->quantity -= $qty;
            $this->contaminated_quantity += $qty;

            // 4. Handle Partial vs Total Discard
            if ($this->quantity <= 0) {
                // Ensure quantity is exactly 0
                $this->quantity = 0;

                // Total: Close Batch Phase
                $currentPhase = $this->phases()->wherePivot('finished_at', null)->first();

                if ($currentPhase) {
                    $this->phases()->updateExistingPivot($currentPhase->id, [
                        'finished_at' => now()
                    ]);
                }

                $this->status = 'contaminated';
            }

            // 5. Save (Triggers Observer -> recordLoss)
            $this->save();

            return true;
        });
    }

    protected $casts = [
        'inoculation_date' => 'date',
        'estimated_harvest_date' => 'date',
        'is_historical' => 'boolean',
    ];

    // Status Constants
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_SEEDED = 'seeded';
    const STATUS_DISCARDED = 'discarded';
    const STATUS_CONTAMINATED = 'contaminated';
    const STATUS_FINALIZED = 'finalized';

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function strain(): BelongsTo
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

    public function phases(): BelongsToMany
    {
        return $this->belongsToMany(Phase::class, 'batch_phases')
            ->withPivot(['id', 'user_id', 'started_at', 'finished_at', 'notes'])
            ->withTimestamps();
    }

    // Relación para las mermas (Uno a Muchos)
    public function losses(): HasMany
    {
        return $this->hasMany(BatchLoss::class);
    }

    // Accessor para saber cuánto ha producido el lote en total
    public function getTotalYieldAttribute()
    {
        return $this->harvests()->sum('quantity');
    }

    // Esto nos permite verificarlo rápidamente con $batch->has_harvests
    public function getHasHarvestsAttribute(): bool
    {
        return $this->harvests()->exists();
    }

    // Accessor útil para obtener la fase activa en el test
    public function getCurrentPhaseAttribute()
    {
        return $this->phases()->wherePivot('finished_at', null)->first();
    }

    // Accessor virtual: Calcula el peso seco estimado basándose en el ratio de la receta
    public function getEstimatedDryWeightAttribute(): float
    {
        $wetWeight = floatval($this->initial_wet_weight);
        $ratio = $this->recipe?->dry_weight_ratio ?? 0.40;

        return $wetWeight * $ratio;
    }

    protected function biologicalEfficiency(): Attribute
    {
        return Attribute::make(
            get: function () {
                $dryWeight = $this->estimated_dry_weight;

                if ($dryWeight <= 0)
                    return 0;

                // 2. Sumamos los kilos. 
                // Si el lote es nuevo y no tiene relación cargada, sum() devolverá 0
                $totalHarvest = $this->harvests->sum('weight');

                // 3. Cálculo con redondeo
                return round(($totalHarvest / $dryWeight) * 100, 2);
            }
        );
    }

    public function transitionTo(Phase $nextPhase, $notes = null)
    {
        return DB::transaction(function () use ($nextPhase, $notes) {
            // Cerrar fase actual si existe
            $currentPhase = $this->phases()->wherePivot('finished_at', null)->first();

            if ($currentPhase) {
                $this->phases()->updateExistingPivot($currentPhase->id, [
                    'finished_at' => now()
                ]);
            }

            // Abrir nueva fase
            return $this->phases()->attach($nextPhase->id, [
                'user_id' => auth()->id() ?? 1, // Fallback para tests
                'started_at' => now(),
                'notes' => $notes
            ]);
        });
    }

    public function recordLoss($qty, $reason, $userId, $details = null)
    {
        // Obtenemos la fase actual. Si es null, buscamos la primera fase del lote.
        $phaseId = $this->current_phase?->id;

        // Fallback: Si no hay fase activa (ej: se acaba de cerrar por descarte total),
        // buscamos la última fase cerrada recientemente.
        if (!$phaseId) {
            $phaseId = $this->phases()
                ->wherePivot('finished_at', null)
                ->first()?->id
                ?? $this->phases()
                    ->orderByPivot('finished_at', 'desc')
                    ->first()?->id;
        }

        if (!$phaseId) {
            throw new \Exception("No se puede registrar una pérdida: el lote {$this->code} no tiene una fase activa ni historial de fases.");
        }

        return $this->losses()->create([
            'phase_id' => $phaseId,
            'quantity' => $qty,
            'reason' => $reason,
            'details' => $details,
            'user_id' => $userId
        ]);
    }



    public function getDaysInCurrentPhaseAttribute()
    {
        $current = $this->phases()->wherePivot('finished_at', null)->first();

        if (!$current || !$current->pivot->started_at) {
            return 0;
        }

        return Carbon::parse($current->pivot->started_at)->diffInDays(now());
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getEstimatedYieldAttribute()
    {
        // Assumption: 500g per batch unit.
        // Can be refined later with Strain specific yield.
        return $this->quantity * 500;
    }

    public function getPreSoldQuantityAttribute()
    {
        // Sum quantity of order items linked to this batch, 
        // exclude cancelled orders.
        return $this->orderItems()
            ->whereHas('order', function ($q) {
                $q->where('status', '!=', 'cancelled');
            })
            ->sum('quantity');
    }

    public function canTransitionToInoculation(): bool
    {
        return !is_null($this->strain_id);
    }
    /**
     * Límite físico de capacidad de producción de la planta.
     * Ayuda a prevenir errores de entrada (gramos vs kilos), ej: ingresar 5000 en lugar de 5.
     */
    public static float $MAX_PRODUCTION_CAPACITY_KG = 50.0;

    /**
     * Peso máximo lógico para una unidad individual (bolsa/frasco).
     */
    public static float $MAX_BAG_WEIGHT_KG = 25.0;

    protected static function booted()
    {
        static::saving(function ($batch) {
            // Bypass safety limits for historical data
            if ($batch->is_historical) {
                // Ensure type validation though?
                // Probably yes, type must be valid.
                if (!in_array($batch->type, ['grain', 'bulk'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'type' => ["El tipo de lote debe ser 'grain' o 'bulk'."]
                    ]);
                }
                return;
            }

            // Handbrake for Bag Weight
            if ($batch->bag_weight > self::$MAX_BAG_WEIGHT_KG) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'bag_weight' => ["Error de pesaje detectado ({$batch->bag_weight} kg). ¿Estás intentando registrar gramos? El sistema usa Kilos (ej: " . (self::$MAX_BAG_WEIGHT_KG / 10) . " para " . (self::$MAX_BAG_WEIGHT_KG * 100) . "g)."]
                ]);
            }

            // Handbrake for Total Wet Weight (Capacity Limit)
            if ($batch->initial_wet_weight > self::$MAX_PRODUCTION_CAPACITY_KG) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'initial_wet_weight' => ["Error de capacidad: El sistema no permite lotes mayores a " . self::$MAX_PRODUCTION_CAPACITY_KG . "kg. Por favor, verifica si estás ingresando gramos en lugar de kilos."]
                ]);
            }


            // Validate Type
            if (!in_array($batch->type, ['grain', 'bulk'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'type' => ["El tipo de lote debe ser 'grain' o 'bulk'."]
                ]);
            }
        });

    }
}
