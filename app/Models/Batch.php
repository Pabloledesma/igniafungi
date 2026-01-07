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

    protected static function booted()
    {
        static::observe(\App\Observers\BatchObserver::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
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

    // Accessor útil para obtener la fase activa en el test
    public function getCurrentPhaseAttribute()
    {
        return $this->phases()->wherePivot('finished_at', null)->first();
    }

    protected function biologicalEfficiency(): Attribute
    {
        return Attribute::make(
            get: function () {
                // 1. Validar que weigth_dry sea numérico y mayor a cero
                // Usamos floatval por si en la base de datos viene como string
                $dryWeight = floatval($this->weigth_dry);
                
                if ($dryWeight <= 0) {
                    return 0;
                }

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
            // Cerrar fase actual
            $this->phases()->wherePivot('finished_at', null)->updateExistingPivot(
                $this->phases()->wherePivot('finished_at', null)->first()->id,
                ['finished_at' => now()]
            );

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
        return $this->losses()->create([
            'phase_id' => $this->current_phase->id,
            'quantity' => $qty,
            'reason' => $reason,
            'details' => $details,
            'user_id' => $userId
        ]);
    }

    public function discard($reason, $qty)
    {
        return DB::transaction(function () use ($reason, $qty) {
            // Registrar la merma
            $this->recordLoss($qty, $reason, auth()->id());

            // Si es descarte total, cerramos la fase actual en la tabla PIVOTE
            $currentPhase = $this->phases()->wherePivot('finished_at', null)->first();
            
            if ($currentPhase) {
                $this->phases()->updateExistingPivot($currentPhase->id, [
                    'finished_at' => now()
                ]);
            }

            return $this->update(['status' => 'contaminated']);
        });
    }

    public function getDaysInCurrentPhaseAttribute()
    {
        $current = $this->phases()->wherePivot('finished_at', null)->first();
    
        if (!$current || !$current->pivot->started_at) {
            return 0;
        }

        return Carbon::parse($current->pivot->started_at)->diffInDays(now());
    }
}
