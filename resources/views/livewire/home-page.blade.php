<div>
  {{-- Hero section Start --}}
  <div class="w-full h-screen relative overflow-hidden flex items-center justify-center bg-stone-950">

    <div class="absolute inset-0 z-0">
      <img src="{{ asset('images/bg_hericium.webp') }}" alt="Hericium Background"
        class="w-full h-full object-cover opacity-60">
      <div class="absolute inset-0 bg-gradient-to-b from-stone-950/90 via-[#260D01]/50 to-emerald-950/80"></div>
    </div>

    <div class="relative z-10 max-w-[85rem] mx-auto px-4 sm:px-6 lg:px-8 w-full">
      <div class="flex flex-col items-center justify-center text-center">
        <div class="max-w-3xl">

          <h1 class="block text-3xl font-bold text-orange-100 sm:text-4xl lg:text-6xl lg:leading-tight">
            La Fuerza del Fuego, <span class="text-gold-ignia">El Poder del Reino Fungi</span>
          </h1>

          <p class="mt-5 text-lg text-stone-200 max-w-2xl mx-auto drop-shadow-md">
            En Ignia Fungi creemos en la transformación. Descubre la medicina ancestral y la belleza oculta de los
            hongos para sanar cuerpo y espíritu.
          </p>

          <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
            <a class="group relative py-3 px-8 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-orange-600 text-white transition-all duration-300 hover:bg-orange-500 hover:scale-105 hover:shadow-[0_0_20px_5px_rgba(234,88,12,0.6)] active:scale-95"
              href="/products">

              <span
                class="absolute inset-0 rounded-lg bg-orange-400 opacity-0 group-hover:opacity-20 blur-md transition-opacity duration-300"></span>

              <span class="relative z-10">Iniciar Transformación</span>

              <svg class="relative z-10 flex-shrink-0 w-4 h-4 transition-transform duration-500 group-hover:rotate-12"
                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path
                  d="M12 2c1 2 2.5 3.5 4.5 4.5m-9 0c2 1 3.5 2.5 4.5 4.5M12 22c-1-2-2.5-3.5-4.5-4.5m9 0c-2-1-3.5-2.5-4.5-4.5" />
              </svg>
            </a>
            <a class="py-3 px-8 inline-flex justify-center items-center gap-x-2 text-sm font-medium rounded-lg border border-stone-500 bg-white/10 backdrop-blur-sm text-white hover:bg-white/20 transition"
              href="/about">
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

  {{-- Benefits Section Start --}}
  <div class="bg-stone-950 border-y border-white/5 relative z-20">
    <div class="max-w-[85rem] px-4 py-10 sm:px-6 lg:px-8 lg:py-14 mx-auto">
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
        {{-- Feature A --}}
        <div class="flex gap-x-5 group hover:bg-white/5 p-4 rounded-xl transition-colors duration-300">
          <div class="flex-shrink-0 relative">
            <div class="absolute inset-0 bg-orange-500 blur-lg opacity-20 group-hover:opacity-40 transition-opacity">
            </div>
            <svg class="flex-shrink-0 size-8 text-orange-500 relative z-10" xmlns="http://www.w3.org/2000/svg"
              width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
              <path d="m9 12 2 2 4-4" />
            </svg>
          </div>
          <div class="grow">
            <h3 class="text-lg font-semibold text-white group-hover:text-orange-400 transition-colors">
              Cultivo 100% Orgánico
            </h3>
            <p class="mt-1 text-stone-400 text-sm">
              Sin químicos ni aditivos. Solo sustrato natural y agua pura de manantial.
            </p>
          </div>
        </div>
        {{-- Feature B --}}
        <div class="flex gap-x-5 group hover:bg-white/5 p-4 rounded-xl transition-colors duration-300">
          <div class="flex-shrink-0 relative">
            <div class="absolute inset-0 bg-gold-ignia blur-lg opacity-20 group-hover:opacity-40 transition-opacity">
            </div>
            <svg class="flex-shrink-0 size-8 text-gold-ignia relative z-10" xmlns="http://www.w3.org/2000/svg"
              width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" />
              <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" />
            </svg>
          </div>
          <div class="grow">
            <h3 class="text-lg font-semibold text-white group-hover:text-gold-ignia transition-colors">
              Sabiduría Ancestral
            </h3>
            <p class="mt-1 text-stone-400 text-sm">
              Rescatamos tradiciones milenarias para potenciar tu bienestar moderno.
            </p>
          </div>
        </div>
        {{-- Feature C --}}
        <div class="flex gap-x-5 group hover:bg-white/5 p-4 rounded-xl transition-colors duration-300">
          <div class="flex-shrink-0 relative">
            <div class="absolute inset-0 bg-emerald-500 blur-lg opacity-20 group-hover:opacity-40 transition-opacity">
            </div>
            <svg class="flex-shrink-0 size-8 text-emerald-500 relative z-10" xmlns="http://www.w3.org/2000/svg"
              width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <path d="M10 17h4V5H2v12h3m15 0h2v-3.34a4 4 0 0 0-1.17-2.83L19 9h-5" />
              <path d="M14 17h1" />
              <circle cx="7.5" cy="17.5" r="2.5" />
              <circle cx="17.5" cy="17.5" r="2.5" />
            </svg>
          </div>
          <div class="grow">
            <h3 class="text-lg font-semibold text-white group-hover:text-emerald-400 transition-colors">
              Envíos Seguros
            </h3>
            <p class="mt-1 text-stone-400 text-sm">
              Llegamos a todo el país con empaques discretos y ecológicos.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
  {{-- Benefits Section End --}}

  {{-- Strain section Start --}}
  <section class="relative py-20 overflow-hidden bg-stone-900">

    <div class="absolute inset-0 z-0">
      <img src="{{ asset('images/bg_pink.webp') }}" alt="Background Cepas"
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
          Cada especie es un maestro silencioso. Seleccionamos las mejores cepas por su potencia sanadora y su belleza
          estructural.
        </p>
      </div>

      <div class="justify-center max-w-6xl px-4 py-4 mx-auto">
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3 md:grid-cols-2">
          @foreach($strains as $strain)
            <div wire:key="{{ $strain->id }}"
              class="group bg-white/5 backdrop-blur-md rounded-2xl shadow-lg hover:shadow-orange-900/20 hover:-translate-y-2 transition-all duration-500 overflow-hidden border border-white/10 hover:border-orange-500/30">
              <a href="/products?selected_strains[0]={{ $strain->id }}" class="block overflow-hidden">
                <img src="{{ asset('storage/' . $strain->image) }}" alt="{{ $strain->name }}"
                  class="object-cover w-full h-72 group-hover:scale-110 group-hover:rotate-1 transition-transform duration-700">
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
      <img src="{{ asset('images/bg_pioppino.webp') }}" alt="Background Cepas"
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
                <img
                  class="h-20 w-20 rounded-full border-2 border-gold-ignia/30 p-1 group-hover:border-gold-ignia transition"
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
  <x-whatsapp-btn />
</div>