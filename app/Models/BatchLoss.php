<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchLoss extends Model
{
    protected $fillable = ['batch_id', 'phase_id', 'quantity', 'reason', 'details', 'user_id'];

    /**
     * Relación con el Lote
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * Relación con la Fase (Opcional, pero recomendada)
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
