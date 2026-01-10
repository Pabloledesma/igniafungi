<div class="min-h-screen py-10 px-4">
    <div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-xl overflow-hidden">
        
       @if(strtolower($order->payment_method) === 'cod')
        <div class="bg-slate-800 p-6 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-full mb-4 shadow-sm">
                <svg class="w-10 h-10 text-gold-ignia" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
            <h1 class="text-3xl font-extrabold text-white">¡Pedido Recibido!</h1>
          
            <p class="text-slate-200 mt-2">Pagarás en efectivo al recibir tus productos.</p>
        </div>
        @endif

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
                        Resumen de la Orden
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
                            <span class="text-base font-bold text-black">Total</span>
                            <span class="text-xl font-black {{ $order->status === 'failed' ? 'text-red-600' : 'text-green-600' }}">
                                ${{ number_format($order->grand_total) }}
                            </span>
                        </div>

                        <div class="flex justify-between text-sm">
                            <span class="text-stone-500">Programado para:</span>
                            <span class="font-bold text-stone-800">
                                {{ $delivery_date ? \Carbon\Carbon::parse($delivery_date)->format('d/m/Y') : 'Pendiente por programar' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-10">
                    @if($order->status === 'failed')
                        <a href="/checkout" class="block w-full bg-slate-900 hover:bg-black text-white text-center font-bold py-4 rounded-xl transition duration-300 shadow-lg">
                            Intentar con otro método de pago
                        </a>
                    @else
                        <a href="/" class="block w-full bg-gold-ignia hover:bg-black text-white text-center font-bold py-4 rounded-xl transition duration-300 shadow-lg">
                            Volver a la tienda
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>