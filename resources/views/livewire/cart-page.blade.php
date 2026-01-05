<div class="w-full max-w-[85rem] py-10 px-4 sm:px-6 lg:px-8 mx-auto">
  <div class="container mx-auto px-4">
    <h1 class="text-white text-2xl font-semibold mb-4">Carro de compras</h1>
    <div class="flex flex-col md:flex-row gap-4">
      <div class="md:w-3/4">
        <div class="bg-white overflow-x-auto rounded-lg shadow-md p-6 mb-4">
          <table class="w-full">
            <thead>
              <tr>
                <th class="text-left font-semibold">Producto</th>
                <th class="text-left font-semibold">Precio</th>
                <th class="text-left font-semibold">Cantidad</th>
                <th class="text-left font-semibold">Total</th>
                <th class="text-left font-semibold">Eliminar</th>
              </tr>
            </thead>
            <tbody>
              @forelse($cart_items as $item)
                <tr wire:key="{{ $item['product_id'] }}">
                  <td class="py-4">
                    <div class="flex items-center">
                      <img class="h-16 w-16 mr-4 truncate" src="{{ asset('/storage/' . $item['image']) }}" alt="{{ $item['name'] }}">
                      <span class="font-semibold truncate">{{ $item['name'] }}</span>
                    </div>
                  </td>
                  <td class="py-4">{{ Number::currency($item['unit_amount'], 'COP') }}</td>
                  <td class="py-4">
                    <div class="flex items-center">
                      <button wire:click='decrementQuantity({{ $item['product_id'] }})' class="border rounded-md py-2 px-4 mr-2">-</button>
                      <span class="text-center w-8">{{ $item['quantity'] }}</span>
                      <button wire:click='incrementQuantity({{ $item['product_id'] }})' class="border rounded-md py-2 px-4 ml-2">+</button>
                    </div>
                  </td>
                  <td class="py-4">{{ Number::currency($item['total_amount'], 'COP') }}</td>
                  <td><button wire:click='removeItem({{ $item['product_id'] }})' class="bg-slate-300 border-2 border-slate-400 rounded-lg px-3 py-1 hover:bg-red-500 hover:text-white hover:border-red-700">Eliminar</button></td>
                </tr>

              @empty
                <tr>
                  <td colspan="5" class="text-center py-4">No hay productos en el carro</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="mb-6">
          <label class="block text-white text-lg font-semibold mb-3">¿Dónde recibes tu pedido?</label>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus:outline-none border-gray-200 has-[:checked]:border-blue-500 has-[:checked]:ring-1 has-[:checked]:ring-blue-500">
                  <input type="radio" wire:model.live="is_bogota" value="1" class="sr-only">
                  <span class="flex flex-1">
                      <span class="flex flex-col">
                          <span class="block text-sm font-medium text-gray-900">Bogotá (Domicilio Local)</span>
                          <span class="mt-1 flex items-center text-xs text-gray-500">Hongos Frescos y Cosechados</span>
                      </span>
                  </span>
                  @if($is_bogota)
                    <div class="ms-6 flex-none" wire:key="location-selector-container">
                      <label for="location">Localidad</label>
                      <select wire:model.live="location" class="py-2 px-3 block w-40 border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500">
                          <option value="">Seleccione</option>
                          @foreach(array_keys(\App\Helpers\CartManagement::LOCALIDAD_PRECIOS) as $loc)
                              <option value="{{ $loc }}">{{ $loc }}</option>
                          @endforeach
                      </select>
                      @error('location') <div class="text-red-500 text-[10px] mt-1">{{ $message }}</div> @enderror
                    </div>
                  @endif
                  <svg class="h-5 w-5 text-blue-600 {{ $is_bogota ? '' : 'hidden' }}" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
              </label>

              <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus:outline-none border-gray-200 has-[:checked]:border-blue-500 has-[:checked]:ring-1 has-[:checked]:ring-blue-500">
                  <input type="radio" wire:model.live="is_bogota" value="0" class="sr-only">
                  <span class="flex flex-1">
                      <span class="flex flex-col">
                          <span class="block text-sm font-medium text-gray-900">Resto del país</span>
                          <span class="mt-1 flex items-center text-xs text-orange-600">Solo productos deshidratados</span>
                      </span>
                  </span>
                  <svg class="h-5 w-5 text-blue-600 {{ !$is_bogota ? '' : 'hidden' }}" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
              </label>
          </div>
      </div>

      @if($is_bogota)
          <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-md">
          <p class="text-green-800 text-sm font-bold mb-2">📅 Selecciona tu fecha de entrega:</p>
          <div class="space-y-2">
              @foreach($this->deliveryOptions as $index => $option)
                  <label class="flex items-center p-2 bg-white border rounded-md cursor-pointer hover:bg-green-100 transition-colors">
                      <input type="radio" wire:model.live="selected_delivery_index" value="{{ $index }}" class="mr-2 text-green-600 focus:ring-green-500">
                      <span class="text-sm {{ $selected_delivery_index == $index ? 'font-bold text-green-900' : 'text-gray-600' }}">
                          {{ $option['label'] }}
                      </span>
                  </label>
              @endforeach
          </div>
          <p class="mt-3 text-xs text-green-700 italic">
              * Entregamos solo días pares según restricción de movilidad de nuestra flota.
          </p>
    </div>
      @else
          <div class="mt-4 p-4 bg-orange-50 border border-orange-200 rounded-md">
              <p class="text-orange-800 text-sm italic">
                  Estamos trabajando para llevar nuestros hongos deshidratados a todo el país próximamente. 
                  Por ahora, los hongos frescos solo están disponibles en Bogotá.
              </p>
          </div>
      @endif
      </div>
      <div class="md:w-1/4">
        @if($is_bogota)
          @php $data = $this->getProgressData(); @endphp

          <div class="p-4 bg-white border-b">
              <div class="flex justify-between mb-1 text-sm">
                  @if($data['is_free'])
                      <span class="text-green-600 font-bold">¡Genial! Tienes envío gratis 🚚</span>
                  @else
                      <span>Te faltan <strong>${{ number_format($data['missing']) }}</strong> para envío gratis</span>
                  @endif
                  <span>$200k</span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-2.5">
                  <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-500" 
                      style="width: {{ $data['percentage'] }}%"></div>
              </div>
          </div>
        @endif
        <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-gold-ignia">
          <h2 class="text-lg font-semibold mb-4">Resumen</h2>

          <div class="flex justify-between mb-2">
            <span>Subtotal</span>
            <span>{{ Number::currency($grand_total, 'COP') }}</span>
          </div>

          <div class="flex justify-between mb-2 {{ $is_bogota ? 'text-blue-600' : 'text-gray-400' }}">
            <span>Domicilio</span>
            <span>{{ $this->shippingCost > 0 ? 'COP ' . number_format($this->shippingCost) : '¡Gratis!' }}</span>
          </div>

          <hr class="my-2">
          
          <div class="flex justify-between mb-4">
            <span class="font-bold text-xl">Total</span>
            <span class="font-bold text-xl text-slate-900">{{ Number::currency($this->finalTotal, 'COP') }}</span>
          </div>

           @if($cart_items)
            @if(!$is_bogota && $this->hasRestrictedProducts)
                {{-- Mensaje de advertencia --}}
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700">
                                <strong>Atención:</strong> Los productos de la categoría <span class="font-bold">Hongos Gourmet</span> solo están disponibles para entrega en Bogotá. Por favor, elimínalos o selecciona Bogotá como destino.
                            </p>
                        </div>
                    </div>
                </div>
                {{-- Botón deshabilitado o simplemente oculto --}}
                <button disabled class="bg-gray-300 cursor-not-allowed block text-center text-white font-bold py-3 px-4 rounded-lg w-full">
                    Pedido no disponible para Nacional
                </button>
            @else
                {{-- Botón normal --}}
                @if ($is_bogota && !$this->location)
                  <div class="ml-3">
                      <p class="text-sm text-red-700">
                          <strong>Atención:</strong> Seleccione una localidad
                      </p>
                  </div>
                @else
                <button wire:click="checkout" class="bg-gold-ignia hover:bg-black transition-colors block text-center text-white font-bold py-3 px-4 rounded-lg w-full shadow-md">
                    Agendar Pedido
                </button>
                @endif
            @endif
            @else
                <button disabled class="bg-gray-300 cursor-not-allowed block text-center text-white py-3 px-4 rounded-lg w-full">
                    Carrito vacío
                </button>
            @endif
        </div>
      </div>
    </div>
  </div>
  <x-whatsapp-btn />
</div>