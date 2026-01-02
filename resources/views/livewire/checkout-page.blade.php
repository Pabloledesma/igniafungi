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
                    console.log('Iniciando Bold con config final:', config);
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
<div class="w-full max-w-[85rem] py-10 px-4 sm:px-6 lg:px-8 mx-auto">
	<h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">
		Checkout
	</h1>
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
								<label class="block text-gray-700 dark:text-white mb-1" for="first_name">
									Nombre
								</label>
								<input wire:model="first_name" class="w-full rounded-lg border py-2 px-3 dark:bg-gray-700 dark:text-white dark:border-none @error('first_name') border-red-500 @enderror" id="first_name" type="text">
								</input>
								@error('first_name')
									<div class="text-red-500 text-sm">{{ $message }}</div>
								@enderror
							</div>
							<div>
								<label class="block text-gray-700 dark:text-white mb-1" for="last_name">
									Apellido
								</label>
								<input wire:model="last_name" class="w-full rounded-lg border py-2 px-3 dark:bg-gray-700 dark:text-white dark:border-none @error('last_name') border-red-500 @enderror" id="last_name" type="text">
								</input>
								@error('last_name')
									<div class="text-red-500 text-sm">{{ $message }}</div>
								@enderror
							</div>
							<div>
								<label class="block text-gray-700 dark:text-white mb-1" for="last_name">
									Email
								</label>
								<input wire:model="email" type="email" class="w-full rounded-lg border py-2 px-3 dark:bg-gray-700 dark:text-white dark:border-none @error('email') border-red-500 @enderror" id="email" type="text">
								</input>
								@error('email')
									<div class="text-red-500 text-sm">{{ $message }}</div>
								@enderror
							</div>
						</div>
						<div class="mt-4">
							<label class="block text-gray-700 dark:text-white mb-1" for="phone">
								Tipo de documento
							</label>
							<select wire:model="document_type" class="py-3 px-4 pe-9 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
								<option selected="">Seleccione una opción</option>
								<option value="CC">Cédula de ciudadanía</option>
								<option value="CE">Cédula de Extrangería</option>
								<option value="NIT">Número de identificación tributaria</option>
								<option value="PP">Pasaporte</option>
								<option value="DIE">Documento de identificación extranjero</option>
							</select>
							@error('document_type')
								<div class="text-red-500 text-sm">{{ $message }}</div>
							@enderror
						</div>
						<div class="mt-4">
							<label class="block text-gray-700 dark:text-white mb-1" for="phone">
								Número de documento
							</label>
							<input wire:model="document_number" class="w-full rounded-lg border py-2 px-3 dark:bg-gray-700 dark:text-white dark:border-none @error('document_number') border-red-500 @enderror" id="document_number" type="text">
							</input>
							@error('document_number')
								<div class="text-red-500 text-sm">{{ $message }}</div>
							@enderror
						</div>
						<div class="mt-4">
							<label class="block text-gray-700 dark:text-white mb-1" for="phone">
								Teléfono
							</label>
							<input wire:model="phone" class="w-full rounded-lg border py-2 px-3 dark:bg-gray-700 dark:text-white dark:border-none @error('phone') border-red-500 @enderror" id="phone" type="text">
							</input>
							@error('phone')
								<div class="text-red-500 text-sm">{{ $message }}</div>
							@enderror
						</div>
						<div class="mt-4">
							<label class="block text-gray-700 dark:text-white mb-1" for="phone">
								Indicatívo
							</label>
							<input wire:model="dial_code" class="w-full rounded-lg border py-2 px-3 dark:bg-gray-700 dark:text-white dark:border-none @error('dial_code') border-red-500 @enderror" id="dial_code" type="text">
							</input>
							@error('dial_code')
								<div class="text-red-500 text-sm">{{ $message }}</div>
							@enderror
						</div>
						<h2 class="text-xl font-bold underline text-gray-700 dark:text-white mb-2">
							Dirección de envío
						</h2>
						
						<div class="mt-4">
							<label class="block text-gray-700 dark:text-white mb-1" for="address">
								Dirección
							</label>
							<input wire:model="street_address" class="w-full rounded-lg border py-2 px-3 dark:bg-gray-700 dark:text-white dark:border-none @error('street_address') border-red-500 @enderror" id="street_address" type="text">
							</input>
							@error('street_address')
								<div class="text-red-500 text-sm">{{ $message }}</div>
							@enderror
						</div>
						<div class="mt-4">
							<label class="block text-gray-700 dark:text-white mb-1" for="city">
								Ciudad
							</label>
							<input wire:model="city" class="w-full rounded-lg border py-2 px-3 dark:bg-gray-700 dark:text-white dark:border-none @error('city') border-red-500 @enderror" id="city" type="text">
							</input>
							@error('city')
								<div class="text-red-500 text-sm">{{ $message }}</div>
							@enderror
						</div>
						<div class="grid grid-cols-2 gap-4 mt-4">
							<div>
								<label class="block text-gray-700 dark:text-white mb-1" for="state">
									Departamento
								</label>
								<input wire:model="state" class="w-full rounded-lg border py-2 px-3 dark:bg-gray-700 dark:text-white dark:border-none @error('state') border-red-500 @enderror" id="state" type="text">
								</input>
								@error('state')
									<div class="text-red-500 text-sm">{{ $message }}</div>
								@enderror
							</div>
							<div>
								<label class="block text-gray-700 dark:text-white mb-1" for="zip">
									ZIP
								</label>
								<input wire:model="zip_code" class="w-full rounded-lg border py-2 px-3 dark:bg-gray-700 dark:text-white dark:border-none @error('zip_code') border-red-500 @enderror" id="zip_code" type="text">
								</input>
								@error('zip_code')
									<div class="text-red-500 text-sm">{{ $message }}</div>
								@enderror
							</div>
						</div>
					</div>
					<div class="text-lg font-semibold mb-4">
						Seleccione el metodo de pago
					</div>
					<select wire:model="payment_method" class="py-3 px-4 pe-9 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
						<option selected="">Seleccione una opción</option>
						<option value="COD">Pago contra entrega</option>
						<option value="BOLD">Bold</option>
					</select>
					@error('payment_method')
						<div class="text-red-500 text-sm">{{ $message }}</div>
					@enderror
				</div>
			<!-- End Card -->
			</form>
		</div>
		<div class="md:col-span-12 lg:col-span-4 col-span-12">
			<div class="bg-white rounded-xl shadow p-4 sm:p-7 dark:bg-slate-900">
				<div class="text-xl font-bold underline text-gray-700 dark:text-white mb-2">
					ORDER SUMMARY
				</div>
				<div class="flex justify-between mb-2 font-bold">
					<span>
						Subtotal
					</span>
					<span>
						{{ Number::currency($grand_total, 'COP') }}
					</span>
				</div>
				<div class="flex justify-between mb-2 font-bold">
					<span>
						Taxes
					</span>
					<span>
						0.00
					</span>
				</div>
				<div class="flex justify-between mb-2 font-bold">
					<span>
						Shipping Cost
					</span>
					<span>
						0.00
					</span>
				</div>
				<hr class="bg-slate-400 my-4 h-1 rounded">
				<div class="flex justify-between mb-2 font-bold">
					<span>
						Grand Total
					</span>
					<span>
						{{ Number::currency($grand_total, 'COP') }}
					</span>
				</div>
				</hr>
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

			@if(!$order_id)
				<button wire:click='placeOrder' class="bg-green-500 mt-4 w-full p-3 rounded-lg text-lg text-white hover:bg-green-600">
					Place Order
				</button>
			@endif
			
			<div class="bg-white mt-4 rounded-xl shadow p-4 sm:p-7 dark:bg-slate-900">
				<div class="text-xl font-bold underline text-gray-700 dark:text-white mb-2">
					BASKET SUMMARY
				</div>
				<ul class="divide-y divide-gray-200 dark:divide-gray-700" role="list">
					@foreach ($cart_items as $item)
						<li class="py-3 sm:py-4">
							<div class="flex items-center">
								<div class="flex-shrink-0">
									<img alt="{{ $item['name'] }}" class="w-12 h-12 rounded-full" src="{{ asset('/storage/' . $item['image']) }}">
									</img>
								</div>
								<div class="flex-1 min-w-0 ms-4">
									<p class="text-sm font-medium text-gray-900 truncate dark:text-white">
										{{ $item['name']  }}
									</p>
									<p class="text-sm text-wood-200 truncate dark:text-gray-400">
										Quantity: {{ $item['quantity'] }}
									
									</p>
								</div>
								<div class="inline-flex items-center text-base font-semibold text-gray-900 dark:text-white">
									{{ Number::currency($item['total_amount'], 'COP') }}
								</div>
							</div>
						</li>
				
					@endforeach
				</ul>
			</div>
		</div>
	</div>
</div>
