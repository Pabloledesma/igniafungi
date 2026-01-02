<div class="w-full max-w-[85rem] py-10 px-4 sm:px-6 lg:px-8 mx-auto">
  <section class="py-10 bg-gray-50 font-poppins dark:bg-gray-800 rounded-lg">
    <div class="px-4 py-4 mx-auto max-w-7xl lg:py-6 md:px-6">
      <div class="grid grid-cols-1 md:grid-cols-12 gap-8">
        <!-- Inicio de filtros -->
        <aside class="md:col-span-3 lg:col-span-2">
          <div class="md:sticky md:top-6 space-y-4">
            <div class="p-3 bg-white border border-gray-100 dark:border-gray-900 dark:bg-gray-900 rounded-xl shadow-sm">
              <h2 class="text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Categorias</h2>
              <div class="w-8 mt-1 mb-4 border-b-2 border-gold-ignia"></div>
              
              <ul class="space-y-2">
                @foreach($categories as $category)
                <li wire:key="{{ $category->id }}">
                  <label for="{{ $category->slug }}" class="flex items-center cursor-pointer group">
                    <input 
                      id="{{ $category->slug }}" 
                      type="checkbox" 
                      wire:model.change="selected_categories" 
                      class="w-3.5 h-3.5 text-orange-600 border-gray-300 rounded focus:ring-orange-500" 
                      value="{{ $category->id }}">
                    <span class="ml-2 text-sm text-gray-600 group-hover:text-gold-ignia transition-colors">{{ $category->name }}</span>
                  </label>
                </li>
                @endforeach
              </ul>
            </div>
            <div class="p-3 bg-white border border-gray-100 dark:border-gray-900 dark:bg-gray-900 rounded-xl shadow-sm">
              <h2 class="text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Cepa</h2>
              <div class="w-8 mt-1 mb-4 border-b-2 border-gold-ignia"></div>
              <ul class="space-y-2">
                @foreach($strains as $strain)
                <li wire:key="{{ $strain->id }}">
                  <label for="{{ $strain->slug }}" class="flex items-center cursor-pointer group">
                    <input 
                      id="{{ $strain->slug }}"
                      wire:model.change="selected_strains" 
                      type="checkbox" 
                      class="w-3.5 h-3.5 text-orange-600 border-gray-300 rounded focus:ring-orange-500" 
                      value="{{ $strain->id }}">
                    <span class="ml-2 text-sm text-gray-600 group-hover:text-gold-ignia transition-colors">{{ $strain->name }}</span>
                  </label>
                </li>
               @endforeach
              </ul>
            </div>
            <div class="p-3 bg-white border border-gray-100 dark:border-gray-900 dark:bg-gray-900 rounded-xl shadow-sm">
              <h2 class="text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Estado del producto</h2>
              <div class="w-8 mt-1 mb-4 border-b-2 border-gold-ignia"></div>
              <ul class="space-y-2">
                <li>
                  <label for="featured" class="flex items-center cursor-pointer group">
                    <input 
                      id="featured" 
                      type="checkbox" 
                      wire:model.change="featured" 
                      class="w-3.5 h-3.5 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                    <span class="ml-2 text-sm text-gray-600 group-hover:text-gold-ignia transition-colors">Es destacado</span>
                  </label>
                </li>
                <li>
                  <label for="in_stock" class="flex items-center cursor-pointer group">
                    <input 
                      id="in_stock"
                      type="checkbox" 
                      wire:model.change="in_stock" 
                      class="w-3.5 h-3.5 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                    <span class="ml-2 text-sm text-gray-600 group-hover:text-gold-ignia transition-colors"">En inventario</span>
                  </label>
                </li>
              </ul>
            </div>
            <div class="p-3 bg-white border border-gray-100 dark:border-gray-900 dark:bg-gray-900 rounded-xl shadow-sm">
              <h2 class="text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Precio</h2>
              <div class="w-8 mt-1 mb-4 border-b-2 border-gold-ignia"></div>
              <div>
                <div class="flex justify-between ">
                  <div class="finline-block text-sm text-blue-400">{{ Number::currency($price_range, 'COP') }}</div>
                </div>
                <input 
                  wire:model.change="price_range"
                  type="range" 
                  class="w-full bg-transparent cursor-pointer appearance-none disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden
  [&::-webkit-slider-thumb]:w-2.5
  [&::-webkit-slider-thumb]:h-2.5
  [&::-webkit-slider-thumb]:-mt-0.5
  [&::-webkit-slider-thumb]:appearance-none
  [&::-webkit-slider-thumb]:bg-white
  [&::-webkit-slider-thumb]:shadow-[0_0_0_4px_rgba(37,99,235,1)]
  [&::-webkit-slider-thumb]:rounded-full
  [&::-webkit-slider-thumb]:transition-all
  [&::-webkit-slider-thumb]:duration-150
  [&::-webkit-slider-thumb]:ease-in-out
  dark:[&::-webkit-slider-thumb]:bg-neutral-700

  [&::-moz-range-thumb]:w-2.5
  [&::-moz-range-thumb]:h-2.5
  [&::-moz-range-thumb]:appearance-none
  [&::-moz-range-thumb]:bg-white
  [&::-moz-range-thumb]:border-4
  [&::-moz-range-thumb]:border-blue-600
  [&::-moz-range-thumb]:rounded-full
  [&::-moz-range-thumb]:transition-all
  [&::-moz-range-thumb]:duration-150
  [&::-moz-range-thumb]:ease-in-out

  [&::-webkit-slider-runnable-track]:w-full
  [&::-webkit-slider-runnable-track]:h-2
  [&::-webkit-slider-runnable-track]:bg-gray-100
  [&::-webkit-slider-runnable-track]:rounded-full
  dark:[&::-webkit-slider-runnable-track]:bg-neutral-700

  [&::-moz-range-track]:w-full
  [&::-moz-range-track]:h-2
  [&::-moz-range-track]:bg-gray-100
  [&::-moz-range-track]:rounded-full" 
                  max="100000" value="1000000" step="1000">
                <div class="flex justify-between ">
                  <span class="ml-2 text-sm text-gray-600 group-hover:text-gold-ignia transition-colors">{{ Number::currency(1000, 'COP', precision: 0) }}</span>
                  <span class="ml-2 text-sm text-gray-600 group-hover:text-gold-ignia transition-colors">{{ Number::currency(100000, 'COP', precision: 0) }}</span>
                </div>
              </div>
            </div>
            <div class="p-3 bg-white border border-gray-100 dark:border-gray-900 dark:bg-gray-900 rounded-xl shadow-sm">
              <div class="items-center justify-between hidden px-3 py-2 bg-gray-100 md:flex dark:bg-gray-900 ">
                <div class="flex items-center justify-between">
                  <select wire:model.change="sort" id="sortByDateOrPrice" class="py-3 px-4 pe-9 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none"> 
                    
                    <option value="">Seleccione una opción</option>
                    <option value="latest">fecha</option>
                    <option value="price">precio</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </aside>
        <!-- Fin de filtros -->
      <main class="md:col-span-9 lg:col-span-10 px-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
          @foreach($products as $product)
            <div wire:key="{{ $product->id }}" class="flex flex-col bg-white border border-gray-200 rounded-xl overflow-hidden hover:shadow-md transition-all h-full">
              <div class="aspect-square bg-gray-50 overflow-hidden">
                <a href="/products/{{ $product->slug }}" class="block w-full h-full">
                  <img src="{{ asset('storage/' . $product->first_image) }}" 
                      alt="{{ $product->name }}" 
                      class="object-cover w-full h-full hover:scale-105 transition-transform duration-500">
                </a>
              </div>

              <div class="p-3 flex flex-col flex-grow">
                <h3 class="text-xs font-bold text-gray-800 line-clamp-2 mb-2 min-h-[2.5rem]">
                  {{ $product->name }}
                </h3>
                <div class="mt-auto">
                  <span class="text-sm font-bold text-green-600">
                    {{ Number::currency($product->price, 'COP') }}
                  </span>
                </div>
              </div>

              <div class="p-3 border-t border-gray-50">
                <button wire:click.prevent='addToCart({{ $product->id }})' 
                        class="w-full text-xs font-semibold text-gray-500 hover:text-orange-600 flex items-center justify-center gap-2">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  <span>Agregar</span>
                </button>
              </div>
            </div>
          @endforeach
        </div>

        <div class="mt-10">
          {{ $products->links() }}
        </div>
      </main>
      </div>
    </div>
  </section>

</div>