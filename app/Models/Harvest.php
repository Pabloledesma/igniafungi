<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Harvest extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id', 
        'weight',
        'harvest_date', 
        'notes', 
        'phase_id', 
        'user_id'
    ];
    
    protected $casts = [
        'harvest_date' => 'date'
    ];


    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function phase()
    {
        return $this->belongsTo(Phase::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
