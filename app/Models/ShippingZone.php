<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'city',
        'locality',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];
}
