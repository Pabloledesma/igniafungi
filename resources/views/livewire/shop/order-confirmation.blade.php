<div>
    <div class="container mx-auto py-10">
        @if($order)
            <h1 class="text-2xl font-bold">¡Pago Confirmado!</h1>
            <p>Referencia: <strong>{{ $order->reference }}</strong></p>
            
            <div class="mt-4">
                <h2 class="font-semibold">Resumen:</h2>
                @foreach($order->items as $item)
                    <p>{{ $item->product->name }} x {{ $item->quantity }}</p>
                @endforeach
            </div>
        @endif
    </div>
</div>