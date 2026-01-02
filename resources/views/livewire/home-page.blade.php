<div>
  {{-- Hero section Start --}}
<div class="w-full h-screen relative overflow-hidden flex items-center justify-center bg-stone-950">
  
  <div class="absolute inset-0 z-0">
    <img src="{{ asset('images/bg_hericium.webp') }}" 
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
          <a class="group relative py-3 px-8 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-orange-600 text-white transition-all duration-300 hover:bg-orange-500 hover:scale-105 hover:shadow-[0_0_20px_5px_rgba(234,88,12,0.6)] active:scale-95" 
            href="/products">
            
            <span class="absolute inset-0 rounded-lg bg-orange-400 opacity-0 group-hover:opacity-20 blur-md transition-opacity duration-300"></span>

            <span class="relative z-10">Iniciar Transformación</span>

            <svg class="relative z-10 flex-shrink-0 w-4 h-4 transition-transform duration-500 group-hover:rotate-12" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
  
  {{-- Strain section Start --}}
<section class="relative py-20 overflow-hidden bg-stone-900">
  
  <div class="absolute inset-0 z-0">
    <img src="{{ asset('images/bg_pink.webp') }}" 
         alt="Background Cepas" 
         class="object-cover w-full h-full opacity-30">
    <div class="absolute inset-0 bg-gradient-to-b from-stone-950/80 via-stone-900/40 to-stone-950/80"></div>
  </div>

  <div class="relative z-10">
    <div class="max-w-xl mx-auto text-center">
      <div class="relative flex flex-col items-center">
        <h2 class="text-5xl font-bold text-stone-50"> 
          Cepas <span class="text-gold-ignia">Ancestrales</span> 
        </h2>
        <div class="flex w-40 mt-2 mb-6 overflow-hidden rounded">
          <div class="flex-1 h-2 bg-wood-200"></div>
          <div class="flex-1 h-2 bg-wood-800"></div>
          <div class="flex-1 h-2 bg-wood-900"></div>
        </div>
      </div>
      <p class="mb-12 text-base text-stone-300">
        Cada especie es un maestro silencioso. Seleccionamos las mejores cepas por su potencia sanadora y su belleza estructural.
      </p>
    </div>

    <div class="justify-center max-w-6xl px-4 py-4 mx-auto">
      <div class="grid grid-cols-1 gap-8 lg:grid-cols-3 md:grid-cols-2">
        @foreach($strains as $strain)
          <div wire:key="{{ $strain->id }}" class="group bg-white/5 backdrop-blur-sm rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden border border-white/10">
            <a href="/products?selected_strains[0]={{ $strain->id }}" class="block overflow-hidden">
                <img src="{{ asset('storage/' . $strain->image) }}" alt="{{ $strain->name }}" 
                class="object-cover w-full h-72 group-hover:scale-105 transition-transform duration-500">
            </a>
            <div class="p-6 text-center">
              <h3 class="text-xl font-bold text-stone-100 uppercase tracking-widest">
                {{ $strain->name }}
              </h3>
              <p class="text-gold-ignia text-sm mt-1 font-medium">Ver Colección</p>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>
</section>
{{-- Strain section End --}}
{{-- Category section Start --}}
<div class="relative bg-stone-900 py-24">
  <div class="absolute inset-0 z-0">
    <img src="{{ asset('images/bg_pioppino.webp') }}" 
         alt="Background Cepas" 
         class="object-cover w-full h-full opacity-30">
    <div class="absolute inset-0 bg-gradient-to-b from-stone-950/80 via-stone-900/40 to-stone-950/80"></div>
  </div>
  <div class="relative z-10">
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
</div>
{{-- Category section End --}}
<a href="https://wa.me/573247262035" 
   target="_blank" 
   class="fixed bottom-6 right-6 z-50 flex items-center justify-center w-14 h-14 bg-green-500 text-white rounded-full shadow-lg hover:bg-green-600 hover:scale-110 transition-all duration-300 group">
  <span class="absolute inset-0 rounded-full bg-green-500 animate-ping opacity-20"></span>
  
  <svg class="w-8 h-8 relative z-10" fill="currentColor" viewBox="0 0 16 16">
    <path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.06 3.973L0 16l4.104-1.076a7.858 7.858 0 0 0 3.888 1.035h.005c4.367 0 7.926-3.558 7.93-7.93a7.898 7.898 0 0 0-2.326-5.703z..."/>
  </svg>
</a>
</div>

