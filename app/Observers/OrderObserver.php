<?php

namespace App\Observers;

use App\Models\Order;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        if ($order->payment_status === 'paid') {
            $this->createTransaction($order);
        }
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Check if payment_status changed to 'paid'
        if ($order->isDirty('payment_status') && $order->payment_status === 'paid') {
            $this->createTransaction($order);
        }

        // Check if status changed to 'delivered'
        if ($order->isDirty('status') && $order->status === 'delivered') {
            \Illuminate\Support\Facades\Log::info("Orden {$order->reference} entregada. Solicitando reseña: {$order->review_url}");
        }
    }

    private function createTransaction(Order $order): void
    {
        // Prevent duplicate transactions for the same order
        if (\App\Models\Transaction::where('reference_id', $order->id)->where('reference_type', Order::class)->exists()) {
            return;
        }

        \App\Models\Transaction::create([
            'description' => "Orden #{$order->reference}",
            'amount' => $order->grand_total ?? 0,
            'type' => 'income',
            'category' => 'sales',
            'date' => now(),
            'reference_id' => $order->id,
            'reference_type' => Order::class,
        ]);
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        //
    }
}
