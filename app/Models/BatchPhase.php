<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BatchPhase extends Model
{
    protected $fillable = ['batch_id', 'phase_id', 'user_id', 'started_at', 'finished_at', 'notes'];

    public function batch()
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
