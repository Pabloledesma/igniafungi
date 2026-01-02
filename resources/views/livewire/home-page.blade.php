<div>
  {{-- Hero section Start --}}
<div class="w-full h-screen relative overflow-hidden flex items-center justify-center bg-stone-950">
  
  <div class="absolute inset-0 z-0">
    <img src="{{ asset('storage/uploads/bg_hericium.webp') }}" 
         alt="Hericium Background" 
         class="w-full h-full object-cover opacity-60">
    <div class="absolute inset-0 bg-gradient-to-b from-[#260D01]/80 via-stone-950/60 to-emerald-950/40"></div>
  </div>

  <div class="relative z-10 max-w-[85rem] mx-auto px-4 sm:px-6 lg:px-8 w-full">
    <div class="flex flex-col items-center justify-center text-center">
      <div class="max-w-3xl">
        
        <h1 class="block text-3xl font-bold text-orange-100 sm:text-4xl lg:text-6xl lg:leading-tight">
          La Fuerza del Fuego, <span class="text-gold-ignia">El Poder del Reino Fungi</span>
        </h1>
        
        <p class="mt-5 text-lg text-stone-200 max-w-2xl mx-auto drop-shadow-md">
          En Ignia Fungi creemos en la transformación. Descubre la medicina ancestral y la belleza oculta de los hongos para sanar cuerpo y espíritu.
        </p>

        <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
          <a class="py-3 px-8 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-wood-800 text-white hover:bg-gold-ignia transition shadow-lg shadow-wood-900/20" href="/products">
            Iniciar Transformación
            <svg class="flex-shrink-0 w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 2c1 2 2.5 3.5 4.5 4.5m-9 0c2 1 3.5 2.5 4.5 4.5M12 22c-1-2-2.5-3.5-4.5-4.5m9 0c-2-1-3.5-2.5-4.5-4.5" />
            </svg>
          </a>
          <a class="py-3 px-8 inline-flex justify-center items-center gap-x-2 text-sm font-medium rounded-lg border border-stone-500 bg-white/10 backdrop-blur-sm text-white hover:bg-white/20 transition" href="/about">
            Nuestra Filosofía
          </a>
        </div>

        <div class="mt-12 flex justify-center">
          <div class="border-t-2 border-gold-ignia/50 pt-4 px-6">
            <p class="text-orange-100/80 italic text-xl drop-shadow-sm">
              "Lo que el fuego transforma, el hongo lo hace eterno."
            </p>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
  {{-- Hero section End --}}
  
  {{-- Brand section Start --}}
<section class="py-20 bg-stone-50">
  <div class="max-w-xl mx-auto text-center">
    <div class="relative flex flex-col items-center">
      <h2 class="text-5xl font-bold text-stone-900"> Cepas <span class="text-gold-ignia">Ancestrales</span> </h2>
      <div class="flex w-40 mt-2 mb-6 overflow-hidden rounded">
        <div class="flex-1 h-2 bg-wood-200"></div>
        <div class="flex-1 h-2 bg-wood-800"></div>
        <div class="flex-1 h-2 bg-wood-900"></div>
      </div>
    </div>
    <p class="mb-12 text-base text-stone-600">
      Cada especie es un maestro silencioso. Seleccionamos las mejores cepas por su potencia sanadora y su belleza estructural.
    </p>
  </div>

  <div class="justify-center max-w-6xl px-4 py-4 mx-auto">
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3 md:grid-cols-2">
      @foreach($brands as $brand)
        <div wire:key="{{ $brand->id }}" class="group bg-white rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden border border-stone-100">
          <a href="/products?selected_brands[0]={{ $brand->id }}" class="block overflow-hidden">
            <img src="{{ asset('storage/' . $brand->image) }}" alt="{{ $brand->name }}" 
              class="object-cover w-full h-72 group-hover:scale-105 transition-transform duration-500">
          </a>
          <div class="p-6 text-center">
            <h3 class="text-xl font-bold text-stone-800 uppercase tracking-widest">
              {{ $brand->name }}
            </h3>
            <p class="text-orange-600 text-sm mt-1 font-medium">Ver Colección</p>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</section>
{{-- Brand section End --}}
{{-- Category section Start --}}
{{-- Category section Start --}}
<div class="bg-stone-900 py-24">
  <div class="max-w-xl mx-auto text-center mb-16">
    <h2 class="text-4xl font-bold text-white mb-4">El Poder <span class="text-gold-ignia">Transformador</span></h2>
    <p class="text-stone-400">Explora nuestro reino según tu necesidad. Sanación, Belleza o Conocimiento.</p>
  </div>

  <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 mx-auto">
    <div class="grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
      @foreach($categories as $category)
      <a wire:key="{{ $category->id }}" href="/products?selected_categories[0]={{ $category->id }}"
        class="group flex flex-col bg-stone-800/50 border border-stone-700 p-6 rounded-2xl hover:bg-orange-600/10 hover:border-gold-ignia transition-all duration-300">
        <div class="flex flex-col items-center text-center">
          <img class="h-20 w-20 rounded-full border-2 border-gold-ignia/30 p-1 group-hover:border-gold-ignia transition" 
               src="{{ asset('storage/' . $category->image) }}" alt="{{ $category->name }}">
          <div class="mt-4">
            <h3 class="text-lg font-semibold text-stone-200 group-hover:text-gold-ignia">
              {{ $category->name }}
            </h3>
            <p class="text-xs text-stone-500 mt-2">Explorar Medicina</p>
          </div>
        </div>
      </a>
      @endforeach
    </div>
  </div>
</div>
{{-- Category section End --}}
</div>
