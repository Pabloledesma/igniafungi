<div class="min-h-screen py-10 px-4">
    <div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gold-ignia p-6 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-full mb-4 shadow-sm">
                <svg class="w-10 h-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h1 class="text-3xl font-extrabold text-white">¡Pago Confirmado!</h1>
            <p class="text-green-100 mt-2">Tu pedido ha sido recibido y está en proceso.</p>
        </div>

        <div class="p-8 text-slate-800">
            @if($order)
                <div class="flex justify-between items-center border-b pb-6 mb-6">
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wider font-semibold">Referencia</p>
                        <p class="text-lg font-mono font-bold text-black">{{ $order->reference }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-500 uppercase tracking-wider font-semibold">Fecha</p>
                        <p class="text-lg font-bold text-black">{{ $order->created_at->format('d/m/Y') }}</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <h2 class="text-xl font-bold text-black flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        Resumen del Pedido
                    </h2>
                    
                    <div class="bg-gray-50 rounded-xl p-4 space-y-3">
                        @foreach($order->items as $item)
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-700">
                                    <span class="font-bold text-black">{{ $item->quantity }}x</span> 
                                    {{ $item->product->name }}
                                </span>
                                <span class="font-semibold text-black">
                                    {{ number_format($item->total_amount) }}
                                </span>
                            </div>
                        @endforeach
                        
                        <div class="border-t pt-3 mt-3 flex justify-between items-center">
                            <span class="text-base font-bold text-black">Total Pagado</span>
                            <span class="text-xl font-black text-green-600">
                                ${{ number_format($order->grand_total) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-10">
                    <a href="/" class="block w-full bg-gold-ignia hover:bg-black text-white text-center font-bold py-4 rounded-xl transition duration-300 shadow-lg">
                        Volver a la tienda
                    </a>
                </div>
            @else
                <div class="text-center py-10">
                    <p class="text-red-500 font-bold">No se encontró la información del pedido.</p>
                    <a href="/" class="text-blue-600 underline">Ir al inicio</a>
                </div>
            @endif
        </div>
    </div>
</div>