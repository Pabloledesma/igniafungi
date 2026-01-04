<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'grand_total',
        'payment_method',
        'payment_status',
        'status',
        'currency',
        'shipping_amount',
        'shipping_method',
        'notes',
        'reference',
        'bold_transaction_id'
    ];

    protected static function booted()
    {
        static::creating(function ($order) {
            // Solo genera la referencia si no se ha asignado una manualmente
            if (empty($order->reference)) {
                $order->reference = 'ORD-' . strtoupper(bin2hex(random_bytes(4)));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addresses(): HasOne
    {
        return $this->hasOne(Address::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    /**
     * Procesa la lógica de inventario para todos los items de la orden
     */
    public function reduceInventory()
    {
        foreach ($this->items as $item) {
            // Accedemos al producto a través de la relación definida en OrderItem
            $item->product->decrement('stock', $item->quantity);
        }
    }

    // App\Models\Order.php
    public function completeOrder()
    {
        // 1. CLÁUSULA DE GUARDA (Idempotencia)
        // Si la orden ya está pagada o procesando, salimos de inmediato.
        if (in_array($this->status, ['paid', 'processing'])) {
            return;
        }
        // Usamos una transacción para que si un producto falla, nada se descuente
        DB::transaction(function () {
            $this->update(['status' => 'paid', 'payment_status' => 'paid']);

            foreach ($this->items as $item) {
                // Llamamos a la función del modelo Product
                $item->product->reduceStock($item->quantity);
            }
            
            // Aquí podrías disparar el agendamiento de entrega
            $this->delivery()->create([
                'status' => 'scheduled',
                'scheduled_at' => now()->addDay(),
            ]);
        });
    }

}
