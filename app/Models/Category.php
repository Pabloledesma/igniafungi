<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relation\HasMany;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'image', 'is_active'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
