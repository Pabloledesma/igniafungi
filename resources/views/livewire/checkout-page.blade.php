@push('scripts')
	<script>
		document.addEventListener('livewire:init', () => {
			Livewire.on('open-bold-checkout', (event) => {
				const config = event.config;

				// Convertimos los objetos a strings JSON como pide el manual
				if (config.customerData) {
					config.customerData = JSON.stringify(config.customerData);
				}
				if (config.billingAddress) {
					config.billingAddress = JSON.stringify(config.billingAddress);
				}

				const launch = () => {
					try {
						const checkout = new BoldCheckout(config);
						checkout.open();
					} catch (e) {
						console.error("Error Bold:", e);
					}
				};

				if (window.BoldCheckout) {
					launch();
				} else {
					window.addEventListener('boldCheckoutLoaded', launch, { once: true });
				}
			});
		});
	</script>
@endpush
<div class="bg-white rounded-xl shadow p-4 sm:p-7 dark:bg-slate-900">
	<div class="grid grid-cols-12 gap-4">
		<div class="md:col-span-12 lg:col-span-8 col-span-12">
			<form wire:submit.prevent='placeOrder'>
				<!-- Card -->
				<div class="bg-white rounded-xl shadow p-4 sm:p-7 dark:bg-slate-900">
					<!-- Shipping Address -->
					<div class="mb-6">
						<h2 class="text-xl font-bold underline text-gray-700 dark:text-white mb-2">
							Información del cliente
						</h2>
						<div class="grid grid-cols-2 gap-4">
							<div>
								<label class="block text-gray-700 dark:text-white mb-1" for="first_name">Nombre</label>
								<input wire:model="first_name"
									class="w-full rounded-lg border border-gray-300 bg-white py-2 px-3 text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:bg-gray-800 dark:text-white dark:border-gray-600 @error('first_name') border-red-500 @enderror"
									id="first_name" type="text">
								</input>
								@error('first_name')
									<div class="text-red-500 text-sm">{{ $message }}</div>
								@enderror
							</div>
							<div>
								<label class="block text-gray-700 dark:text-white mb-1" for="last_name">
									Apellido
								</label>
								<input wire:model="last_name"
									class="w-full rounded-lg border border-gray-300 bg-white py-2 px-3 text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:bg-gray-800 dark:text-white dark:border-gray-600 @error('last_name') border-red-500 @enderror"
									id="last_name" type="text">
								</input>
								@error('last_name')
									<div class="text-red-500 text-sm">{{ $message }}</div>
								@enderror
							</div>
							<div>
								<label class="block text-gray-700 dark:text-white mb-1" for="last_name">
									Email
								</label>
								<input wire:model="email" type="email"
									class="w-full rounded-lg border border-gray-300 bg-white py-2 px-3 text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:bg-gray-800 dark:text-white dark:border-gray-600 @error('email') border-red-500 @enderror"
									id="email" type="text">
								</input>
								@error('email')
									<div class="text-red-500 text-sm">{{ $message }}</div>
								@enderror
							</div>
							<div>
								<label class="block text-gray-700 dark:text-white mb-1" for="phone">
									Teléfono
								</label>
								<input wire:model="phone"
									class="w-full rounded-lg border border-gray-300 bg-white py-2 px-3 text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:bg-gray-800 dark:text-white dark:border-gray-600 @error('phone') border-red-500 @enderror"
									id="phone" type="text">
								</input>
								@error('phone')
									<div class="text-red-500 text-sm">{{ $message }}</div>
								@enderror
							</div>
							<div>
								<label class="block text-gray-700 dark:text-white mb-1" for="phone">
									Tipo de documento
								</label>
								<select wire:model="document_type"
									class="py-3 px-4 pe-9 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none">
									<option selected="">Seleccione una opción</option>
									<option value="CC">Cédula de ciudadanía</option>
									<option value="CE">Cédula de Extrangería</option>
									<option value="NIT">Número de identificación tributaria</option>
									<option value="PP">Pasaporte</option>
								</select>
								@error('document_type')
									<div class="text-red-500 text-sm">{{ $message }}</div>
								@enderror
							</div>
							<div>
								<label class="block text-gray-700 dark:text-white mb-1" for="phone">
									Número de documento
								</label>
								<input wire:model="document_number"
									class="w-full rounded-lg border border-gray-300 bg-white py-2 px-3 text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:bg-gray-800 dark:text-white dark:border-gray-600 @error('document_number') border-red-500 @enderror"
									id="document_number" type="text">
								</input>
								@error('document_number')
									<div class="text-red-500 text-sm">{{ $message }}</div>
								@enderror
							</div>
						</div>

						<h2 class="text-xl font-bold underline text-gray-700 dark:text-white mb-2">
							Dirección de envío
						</h2>

						<div class="grid grid-cols-2 gap-4">
							<div>
								<label class="block text-gray-700 dark:text-white mb-1" for="city">
									Ciudad
								</label>
								<select wire:model.live="city"
									class="py-3 px-4 pe-9 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none">
									<option value="">Seleccione una ciudad</option>
									@foreach(\App\Helpers\CartManagement::getColombiaCities() as $cityName)
										<option value="{{ $cityName }}">{{ $cityName }}</option>
									@endforeach
								</select>
								@error('city')
									<div class="text-red-500 text-sm">{{ $message }}</div>
								@enderror
							</div>
							@if ($city === 'Bogotá')
								<div>
									<label class="block text-gray-700 dark:text-white mb-1" for="location">
										Localidad
									</label>
									<select wire:model.live="location"
										class="py-3 px-4 pe-9 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none">
										<option value="">Seleccione una localidad</option>
										<option value="Suba">Suba</option>
										<option value="Engativa">Engativa</option>
										<option value="Kennedy">Kennedy</option>
										<option value="Fontibon">Fontibon</option>
										<option value="Teusaquillo">Teusaquillo</option>
										<option value="Usaquen">Usaquen</option>
										<option value="Puente Aranda">Puente Aranda</option>
										<option value="Usme">Usme</option>
										<option value="Bosa">Bosa</option>
										<option value="Ciudad Bolivar">Ciudad Bolivar</option>
										<option value="Rafael Uribe Uribe">Rafael Uribe Uribe</option>
										<option value="Tunjuelito">Tunjuelito</option>
										<option value="Santa Fe">Santa Fe</option>
										<option value="San Cristobal">San Cristobal</option>
										<option value="Barrios Unidos">Barrios Unidos</option>
										<option value="Antonio Nariño">Antonio Nariño</option>
										<option value="Martires">Martires</option>
										<option value="Candelaria">Candelaria</option>
										<option value="Chapinero">Chapinero</option>
										<option value="Sumapaz">Sumapaz</option>
									</select>
									@error('location')
										<div class="text-red-500 text-sm">{{ $message }}</div>
									@enderror
								</div>
							@endif
							@if ($city != 'Bogotá')
								<div>
									<label class="block text-gray-700 dark:text-white mb-1" for="department">
										Departamento
									</label>
									<select wire:model.live="department"
										class="py-3 px-4 pe-9 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none">
										<option value="">Seleccione un departamento</option>
										@foreach(\App\Helpers\CartManagement::getColombiaDepartments() as $departmentName)
											<option value="{{ $departmentName }}">{{ $departmentName }}</option>
										@endforeach
									</select>
									@error('department')
										<div class="text-red-500 text-sm">{{ $message }}</div>
									@enderror
								</div>
							@endif
						</div>
						<div>
							<label class="block text-gray-700 dark:text-white mb-1" for="address">
								Dirección
							</label>
							<input wire:model="street_address"
								class="w-full rounded-lg border border-gray-300 bg-white py-2 px-3 text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:bg-gray-800 dark:text-white dark:border-gray-600 @error('street_address') border-red-500 @enderror"
								id="street_address" type="text">
							</input>
							@error('street_address')
								<div class="text-red-500 text-sm">{{ $message }}</div>
							@enderror
						</div>
						<div class="mb-4">
							<label class="block text-gray-700 dark:text-white mb-2 font-bold">
								Método de Envío
							</label>
							<div class="grid gap-4 md:grid-cols-1">
								{{-- Recoger en Tienda --}}
								<div class="relative flex items-start p-4 border rounded-lg cursor-pointer hover:bg-gray-50 {{ $shipping_method === 'pickup' ? 'border-blue-500 bg-blue-50' : 'border-gray-200' }}"
									wire:click="$set('shipping_method', 'pickup'); $call('calculateShipping')">
									<div class="flex items-center h-5">
										<input type="radio" wire:model="shipping_method" value="pickup"
											class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500" />
									</div>
									<div class="ml-3 text-sm">
										<label class="font-medium text-gray-700 dark:text-gray-300">Recoger en
											Tienda</label>
										<p class="text-xs text-gray-500">Gratis. Calle 123 #45-67.</p>
									</div>
									<span class="ml-auto font-bold text-gray-700 dark:text-white">Gratis</span>
								</div>

								{{-- Interrapidísimo --}}
								<div class="relative flex items-start p-4 border rounded-lg cursor-pointer hover:bg-gray-50 {{ $shipping_method === 'interrapidisimo' ? 'border-blue-500 bg-blue-50' : 'border-gray-200' }}"
									wire:click="$set('shipping_method', 'interrapidisimo'); $call('calculateShipping')">
									<div class="flex items-center h-5">
										<input type="radio" wire:model="shipping_method" value="interrapidisimo"
											class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500" />
									</div>
									<div class="ml-3 text-sm">
										<label
											class="font-medium text-gray-700 dark:text-gray-300">Interrapidísimo</label>
										<p class="text-xs text-gray-500">Envíos nacionales. Calculado por peso.</p>
									</div>
								</div>

								{{-- Domicilio Bogotá --}}
								@if($city === 'Bogotá')
									<div class="relative flex items-start p-4 border rounded-lg cursor-pointer hover:bg-gray-50 {{ $shipping_method === 'bogota' ? 'border-blue-500 bg-blue-50' : 'border-gray-200' }}"
										wire:click="$set('shipping_method', 'bogota'); $call('calculateShipping')">
										<div class="flex items-center h-5">
											<input type="radio" wire:model="shipping_method" value="bogota"
												class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500" />
										</div>
										<div class="ml-3 text-sm">
											<label class="font-medium text-gray-700 dark:text-gray-300">Domicilio Bogotá
												(Días Pares)</label>
											<p class="text-xs text-gray-500">Entrega sostenible. Solo días pares.</p>
										</div>
										<span
											class="ml-auto font-bold text-gray-700 dark:text-white">{{ Number::currency(10000, 'COP') }}</span>
									</div>
								@endif
							</div>
							@error('shipping_method')
								<div class="text-red-500 text-sm mt-1">{{ $message }}</div>
							@enderror
						</div>

						<!-- Delivery Date Selection -->
						<div class="mt-4">
							<label class="block text-gray-700 dark:text-white mb-1 font-bold">
								Fecha de envío
							</label>

							@if($shipping_method === 'bogota')
								<div class="bg-blue-50 border border-blue-200 rounded-md p-3">
									<p class="text-sm text-blue-800 mb-2 font-semibold">Selecciona una fecha (Días Pares):</p>
									<div class="space-y-2">
										@foreach($this->deliveryOptions as $option)
											<label class="flex items-center p-3 bg-white border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors {{ $delivery_date == $option['date'] ? 'ring-2 ring-blue-500 border-transparent' : '' }}">
												<input type="radio" wire:model.live="delivery_date" value="{{ $option['date'] }}" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
												<span class="ml-3 block text-sm font-medium text-gray-700">
													{{ $option['label'] }}
												</span>
											</label>
										@endforeach
									</div>
									<p class="mt-2 text-xs text-blue-600">
										* Solo días pares y hábiles.
									</p>
								</div>
							@else
								<input wire:model="delivery_date"
									class="w-full rounded-lg border border-gray-300 bg-white py-2 px-3 text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:bg-gray-800 dark:text-white dark:border-gray-600 @error('delivery_date') border-red-500 @enderror"
									id="delivery_date" type="date" min="{{ date('Y-m-d') }}">
							@endif
							@error('delivery_date')
								<div class="text-red-500 text-sm mt-1">{{ $message }}</div>
							@enderror
						</div>
						<div class="text-lg font-semibold mb-4">
							Seleccione el metodo de pago
						</div>
						<select wire:model="payment_method"
							class="py-3 px-4 pe-9 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none">
							<option selected="">Seleccione una opción</option>
							<option value="COD">Pago contra entrega</option>
							<option value="BOLD">Bold</option>
						</select>
						@error('payment_method')
							<div class="text-red-500 text-sm">{{ $message }}</div>
						@enderror
					</div>
				</div>
				<!-- End Card -->
			</form>
		</div>
		<div class="md:col-span-12 lg:col-span-4 col-span-12">
			<div class="sticky top-20"> <!-- Sticky Sidebar -->
				<div class="text-xl font-bold underline text-gray-700 dark:text-white mb-2">
					RESUMEN DEL PEDIDO
				</div>

				@if ($errors->any())
					<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
						<ul>
							@foreach ($errors->all() as $error)
								<li>{{ $error }}</li>
							@endforeach
						</ul>
					</div>
				@endif

				@if (session()->has('success'))
					<div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 dark:bg-gray-800 dark:text-green-400"
						role="alert">
						<span class="font-medium">Success!</span> {{ session('success') }}
					</div>
				@endif
				@if (session()->has('error'))
					<div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400"
						role="alert">
						<span class="font-medium">Error!</span> {{ session('error') }}
					</div>
				@endif
				@if (session()->has('warning'))
					<div class="p-4 mb-4 text-sm text-yellow-800 rounded-lg bg-yellow-50 dark:bg-gray-800 dark:text-yellow-300"
						role="alert">
						<span class="font-medium">Warning!</span> {{ session('warning') }}
					</div>
				@endif
				@if (session()->has('info'))
					<div class="p-4 mb-4 text-sm text-blue-800 rounded-lg bg-blue-50 dark:bg-gray-800 dark:text-blue-400"
						role="alert">
						<span class="font-medium">Info!</span> {{ session('info') }}
					</div>
				@endif

				<div class="bg-white mt-4 rounded-xl shadow p-4 sm:p-7 dark:bg-slate-900">
					<!-- Coupon Section -->
					<div class="mb-4 border-b pb-4 dark:border-gray-700">
						<label class="block text-gray-700 dark:text-white mb-1 font-bold">
							¿Tienes un cupón?
						</label>
						@if($applied_coupon_code)
							<div class="flex items-center justify-between bg-green-50 p-2 rounded border border-green-200">
								<span class="text-green-700 font-bold">
									{{ $applied_coupon_code }} ({{ Number::currency($discount_amount, 'COP') }} OFF)
								</span>
								<button type="button" wire:click="removeCoupon"
									class="text-red-500 hover:text-red-700 text-sm underline">
									Remover
								</button>
							</div>
						@else
							<div class="flex gap-2">
								<input wire:model="coupon_code_input" type="text" placeholder="Código de cupón"
									class="w-full rounded-lg border border-gray-300 bg-white py-2 px-3 text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:bg-gray-800 dark:text-white dark:border-gray-600">
								<button type="button" wire:click="applyCoupon"
									class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
									Aplicar
								</button>
							</div>
						@endif
					</div>

					<ul class="divide-y divide-gray-200 dark:divide-gray-700" role="list">
						@foreach ($cart_items as $item)
							<li class="py-3 sm:py-4">
								<div class="flex items-center">
									<div class="flex-1 min-w-0 ms-4">
										<p class="text-sm font-medium text-gray-900 truncate dark:text-white">
											{{ $item['name']  }}
										</p>
										<p class="text-sm text-wood-200 truncate dark:text-gray-400">
											Quantity: {{ $item['quantity'] }}

										</p>
									</div>
									<div
										class="inline-flex items-center text-base font-semibold text-gray-900 dark:text-white">
										{{ Number::currency($item['total_amount'], 'COP') }}
									</div>
								</div>
							</li>

						@endforeach

						@if($discount_amount > 0)
							<li class="py-3 sm:py-4">
								<div class="flex justify-between mb-2 font-medium text-green-600">
									<span>Descuento ({{ $applied_coupon_code }})</span>
									<span>-{{ Number::currency($discount_amount, 'COP') }}</span>
								</div>
							</li>
						@endif

						@if($city === 'Bogotá')
							<li class="py-3 sm:py-4">
								<div class="flex justify-between mb-2 font-medium text-blue-600">
									<span>Envío (Bogotá)</span>
									<span>{{ Number::currency($shipping_cost, 'COP') }}</span>
								</div>
							</li>
						@endif
						<li class="py-3 sm:py-4">
							<div class="flex justify-between mb-2 font-bold text-lg">
								<span>Total a Pagar</span>
								<span>{{ Number::currency($grand_total, 'COP') }}</span>
							</div>
						</li>
					</ul>

				</div>
				@if(!$order_id)
					<button wire:click='placeOrder'
						class="bg-gold-ignia w-full mt-4 p-3 rounded-lg text-lg text-white hover:bg-green-600 font-bold shadow-lg transition-transform transform hover:scale-105">
						Pagar Ahora
					</button>
				@endif
			</div>
		</div>
	</div>
	<x-whatsapp-btn />
</div>