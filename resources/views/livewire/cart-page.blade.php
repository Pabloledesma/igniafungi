<div class="w-full max-w-[85rem] py-10 px-4 sm:px-6 lg:px-8 mx-auto">
  <div class="container mx-auto px-4">
    <h1 class="text-white text-3xl font-bold mb-6">Carro de compras</h1>

    @if(count($cart_items) > 0)
      <div class="flex flex-col md:flex-row gap-8 relative items-start">
        <!-- Left Column: Products -->
        <div class="md:w-2/3 w-full">
          <div class="bg-white overflow-hidden rounded-xl shadow-lg border border-gray-100 p-6 mb-6">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="border-b border-gray-200">
                  <th class="py-4 font-semibold text-gray-700">Producto</th>
                  <th class="py-4 font-semibold text-gray-700">Precio</th>
                  <th class="py-4 font-semibold text-gray-700">Cantidad</th>
                  <th class="py-4 font-semibold text-gray-700">Total</th>
                  <th class="py-4 font-semibold text-gray-700"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                @foreach($this->cartItemsWithMetadata as $item)
                  <tr wire:key="{{ $item['product_id'] }}" class="group transition-colors hover:bg-gray-50/50">
                    <td class="py-5 pr-4">
                      <div class="flex items-center gap-4">
                        <div class="w-20 h-20 rounded-lg overflow-hidden border border-gray-200 flex-shrink-0">
                          <img class="w-full h-full object-cover" src="{{ asset('/storage/' . $item['image']) }}"
                            alt="{{ $item['name'] }}">
                        </div>
                        <div class="flex flex-col">
                          <span
                            class="font-bold text-gray-900 group-hover:text-blue-600 transition-colors">{{ $item['name'] }}</span>
                          @if(!empty($item['is_preorder']))
                            <span
                              class="inline-flex items-center gap-1 mt-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                              🕒 Reserva para {{ $item['delivery_date_label'] ?? 'Fecha por confirmar' }}
                            </span>
                          @endif
                        </div>
                      </div>
                    </td>
                    <td class="py-5 font-medium text-gray-600">{{ Number::currency($item['unit_amount'], 'COP') }}</td>
                    <td class="py-5">
                      <div class="flex items-center bg-gray-100 rounded-lg w-fit border border-gray-200"
                        wire:loading.class="opacity-50 pointer-events-none">
                        <button wire:click='decrementQuantity({{ $item['product_id'] }})'
                          class="px-3 py-1 hover:bg-gray-200 text-gray-600 transition-colors rounded-l-lg disabled:opacity-50">-</button>
                        <span class="text-center w-8 text-sm font-semibold text-gray-800">{{ $item['quantity'] }}</span>
                        <button wire:click='incrementQuantity({{ $item['product_id'] }})'
                          class="px-3 py-1 hover:bg-gray-200 text-gray-600 transition-colors rounded-r-lg">+</button>
                      </div>
                    </td>
                    <td class="py-5 font-bold text-gray-900">{{ Number::currency($item['total_amount'], 'COP') }}</td>
                    <td class="py-5 text-right">
                      <button wire:click='removeItem({{ $item['product_id'] }})'
                        class="text-gray-400 hover:text-red-500 transition-colors tooltip" aria-label="Eliminar">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                          stroke="currentColor" class="w-5 h-5">
                          <path stroke-linecap="round" stroke-linejoin="round"
                            d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                      </button>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="size-5 text-blue-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                <path stroke-linecap="round" stroke-linejoin="round"
                  d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
              </svg>
              ¿Dónde recibes tu pedido?
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ciudad / Municipio</label>
                <div class="relative">
                  <select wire:model.live="city"
                    class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2.5">
                    @foreach($cities as $c)
                      <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                  </select>
                </div>
                @if(!$is_bogota)
                  <p class="mt-2 text-sm text-gray-500">
                    🚚 Envíos nacionales disponibles solo para productos deshidratados.
                  </p>
                @endif
              </div>

              @if($is_bogota)
                <div class="" wire:key="location-selector-container">
                  <label for="location" class="block text-sm font-medium text-gray-700 mb-1">Localidad (Bogotá)</label>
                  <div class="relative">
                    <select wire:model.live="location"
                      class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2.5">
                      <option value="">Seleccione Localidad</option>
                      @foreach(array_keys(\App\Helpers\CartManagement::LOCALIDAD_PRECIOS) as $loc)
                        <option value="{{ $loc }}">{{ $loc }}</option>
                      @endforeach
                    </select>
                  </div>
                  @error('location') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                </div>
              @endif
            </div>

            @if($is_bogota)
              <div class="mt-6 p-4 bg-green-50 border border-green-100 rounded-lg">
                <p class="text-green-800 text-sm font-bold mb-3 flex items-center gap-2">
                  📅 Fecha de entrega sugerida:
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  @foreach($this->deliveryOptions as $index => $option)
                    <label
                      class="relative flex items-center ps-4 border border-green-200 rounded-lg cursor-pointer bg-white hover:bg-green-50 transition-colors {{ $selected_delivery_index == $index ? 'ring-2 ring-green-500 border-transparent' : '' }}">
                      <input type="radio" wire:model.live="selected_delivery_index" value="{{ $index }}"
                        class="w-4 h-4 text-green-600 bg-gray-100 border-gray-300 focus:ring-green-500">
                      <span class="w-full py-4 ms-2 text-sm font-medium text-gray-900">{{ $option['label'] }}</span>
                    </label>
                  @endforeach
                </div>
                <p class="mt-3 text-xs text-green-700 opacity-80">
                  * Entregamos solo días pares según logística eficiente.
                </p>
              </div>
            @endif
          </div>
        </div>

        <!-- Right Column: Summary (Sticky) -->
        <div class="md:w-1/3 w-full sticky top-24">

          <!-- Free Shipping Progress -->
          @if($is_bogota)
            @php $data = $this->getProgressData(); @endphp
            <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-5 mb-4 relative overflow-hidden">
              <div class="relative z-10">
                <div class="flex flex-col mb-2 text-sm">
                  @if($data['is_free'])
                    <span class="text-green-600 font-bold flex items-center gap-2">
                      🎉 ¡Felicidades! Tienes envío gratis.
                    </span>
                  @else
                    <span class="text-gray-700">
                      ¡Estás a <strong class="text-blue-600">{{ Number::currency($data['missing'], 'COP') }}</strong> de
                      obtener envío gratis en Bogotá!
                    </span>
                  @endif
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2">
                  <div
                    class="bg-gradient-to-r from-blue-500 to-green-400 h-2 rounded-full transition-all duration-500 ease-out"
                    style="width: {{ $data['percentage'] }}%"></div>
                </div>
              </div>
              <!-- Decorative background element -->
              <div
                class="absolute top-0 right-0 -mt-2 -mr-2 w-16 h-16 bg-gradient-to-br from-yellow-300 to-yellow-500 rounded-full opacity-20 blur-xl">
              </div>
            </div>
          @endif

          <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6">Resumen del Pedido</h2>

            <div class="space-y-3 mb-6">
              <div class="flex justify-between text-gray-600">
                <span>Subtotal</span>
                <span
                  class="font-medium text-gray-900">{{ Number::currency($grand_total + $this->discountAmount, 'COP') }}</span>
              </div>

              @if($this->discountAmount > 0)
                <div class="flex justify-between text-green-600">
                  <span>Descuento Preventa (10%)</span>
                  <span class="font-medium">- {{ Number::currency($this->discountAmount, 'COP') }}</span>
                </div>
              @endif

              <div class="flex justify-between {{ $is_bogota ? 'text-blue-600' : 'text-gray-500' }}">
                <span>Envío estimado</span>
                <span class="font-medium">
                  @if($this->shippingCost > 0)
                    {{ Number::currency($this->shippingCost, 'COP') }}
                  @else
                    <span class="text-green-500 font-bold">¡Gratis!</span>
                  @endif
                </span>
              </div>
            </div>

            <hr class="border-dashed border-gray-200 my-4">

            <div class="flex justify-between items-center mb-6">
              <span class="font-bold text-xl text-gray-900">Total</span>
              <span class="font-bold text-2xl text-slate-900">{{ Number::currency($this->finalTotal, 'COP') }}</span>
            </div>

            <!-- Action Buttons -->
            @if(!$is_bogota && $this->hasRestrictedProducts)
              <div class="bg-amber-50 border-l-4 border-amber-500 p-4 mb-4 rounded-r-md">
                <div class="flex">
                  <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd"
                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                        clip-rule="evenodd" />
                    </svg>
                  </div>
                  <div class="ml-3">
                    <p class="text-sm text-amber-800">
                      <strong>Nota:</strong> Los hongos frescos no pueden enviarse fuera de Bogotá por su corta vida útil.
                    </p>
                  </div>
                </div>
              </div>
              <button disabled class="w-full bg-gray-200 text-gray-400 font-bold py-3.5 px-4 rounded-lg cursor-not-allowed">
                Ir a Finalizar Compra
              </button>
            @elseif($is_bogota && !$location)
              <button disabled class="w-full bg-gray-200 text-gray-500 font-bold py-3.5 px-4 rounded-lg cursor-not-allowed">
                Selecciona una Localidad
              </button>
            @else
              <button wire:click="checkout"
                class="group w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-4 px-4 rounded-xl shadow-lg hover:shadow-xl transition-all flex justify-center items-center gap-2">
                Finalizar Compra
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                  stroke="currentColor" class="size-5 group-hover:translate-x-1 transition-transform">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                </svg>
              </button>
            @endif

            <div class="mt-4 flex justify-center">
              <img src="https://cdn.shopify.com/s/files/1/0605/7361/4311/files/secure-checkout.png?v=1647432328"
                class="h-6 opacity-60 grayscale" alt="Pagos Seguros">
            </div>
          </div>
        </div>
      </div>
    @else
      <!-- Empty State -->
      <div class="max-w-4xl mx-auto text-center py-16">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10">
          <div class="bg-gray-50 dark:bg-slate-800 rounded-full w-24 h-24 flex items-center justify-center mx-auto mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
              stroke="currentColor" class="size-10 text-gray-400">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
            </svg>
          </div>
          <h2 class="text-2xl font-bold text-gray-900 mb-2">Tu canasta está vacía</h2>
          <p class="text-gray-500 mb-8 max-w-md mx-auto">Parece que aún no has agregado hongos frescos o productos a tu
            pedido. ¡Revisa nuestra cosecha disponible!</p>

          <a href="/products"
            class="inline-flex items-center justify-center px-8 py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-lg transition-colors">
            Ver Cosecha Disponible
          </a>

          @if($this->recentBatches->count() > 0)
            <div class="mt-12 border-t pt-10">
              <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-6">Cosecha reciente</h3>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                @foreach($this->recentBatches as $batch)
                  <div class="group relative rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                    <div class="aspect-square bg-gray-200 overflow-hidden">
                      @if($batch->strain->image)
                        <img src="{{ asset('storage/' . $batch->strain->image) }}"
                          class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                          alt="{{ $batch->strain->name }}">
                      @endif
                    </div>
                    <div class="p-3 bg-white text-left">
                      <p class="text-sm font-bold text-gray-900">{{ $batch->strain->name }}</p>
                      <p class="text-xs text-gray-500">Lote #{{ $batch->id }}</p>
                    </div>
                    <a href="/products" class="absolute inset-0 z-10"></a>
                  </div>
                @endforeach
              </div>
            </div>
          @endif
        </div>
      </div>
    @endif
  </div>

</div>