@section('title', 'Shiitake, Melena de León y Eryngii Fresco y Deshidratado en Bogotá | Ignia Fungi')
@section('meta_description', 'Hongos premium en Bogotá: Shiitake, Melena de León y Eryngii (Orellana Rey). Compra cultivo sostenible fresco para cocina gourmet y deshidratados para biohacking.')

<div>
  {{-- Hero section Start --}}
  <div class="w-full h-screen relative overflow-hidden flex items-center justify-center bg-stone-950">
    <div class="absolute inset-0 z-0">
      <img src="{{ asset('images/bg_hericium.webp') }}" alt="Cultivo de Melena de León fresco en Bogotá"
        class="w-full h-full object-cover opacity-60" loading="eager" width="1920" height="1080">
      <div class="absolute inset-0 bg-gradient-to-b from-stone-950/90 via-[#260D01]/50 to-emerald-950/80"></div>
    </div>

    <div class="relative z-10 max-w-[85rem] mx-auto px-4 sm:px-6 lg:px-8 w-full">
      <div class="flex flex-col items-center justify-center text-center">
        <div class="max-w-3xl">
          <h1 class="block text-3xl font-bold text-orange-100 sm:text-4xl lg:text-6xl lg:leading-tight">
            Hongos de Especialidad <span class="text-gold-ignia">Frescos y Deshidratados</span>
          </h1>
          <p class="mt-5 text-lg text-stone-200 max-w-2xl mx-auto drop-shadow-md">
            Desde la frescura del cultivo hasta la potencia del hongo deshidratado. Descubre el sabor profundo del
            Shiitake y la medicina cognitiva de la Melena de León.
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
            <h3 class="text-lg font-semibold text-white group-hover:text-orange-400 transition-colors">Cultivo 100%
              Orgánico</h3>
            <p class="mt-1 text-stone-400 text-sm">Sin químicos ni aditivos. Solo sustrato natural y agua pura de
              manantial.</p>
          </div>
        </div>
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
            <h3 class="text-lg font-semibold text-white group-hover:text-gold-ignia transition-colors">Sabiduría
              Ancestral</h3>
            <p class="mt-1 text-stone-400 text-sm">Rescatamos tradiciones milenarias para potenciar tu bienestar
              moderno.</p>
          </div>
        </div>
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
            <h3 class="text-lg font-semibold text-white group-hover:text-emerald-400 transition-colors">Envíos Seguros
            </h3>
            <p class="mt-1 text-stone-400 text-sm">Llegamos a todo el país con empaques discretos y ecológicos.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
  {{-- Benefits Section End --}}

  {{-- Strain section Start --}}
  <section class="relative py-20 overflow-hidden bg-stone-900">
    <div class="absolute inset-0 z-0">
      <img src="{{ asset('images/bg_pink.webp') }}" alt="Cultivo de Orellana Rosa en sustrato"
        class="object-cover w-full h-full opacity-30" loading="lazy">
      <div class="absolute inset-0 bg-gradient-to-b from-stone-950/80 via-stone-900/40 to-stone-950/80"></div>
    </div>
    <div class="relative z-10">
      <div class="max-w-xl mx-auto text-center">
        <h2 class="text-5xl font-bold text-stone-50">Cepas <span class="text-gold-ignia">Ancestrales</span></h2>
        <div class="flex w-40 mt-2 mb-6 overflow-hidden rounded mx-auto">
          <div class="flex-1 h-2 bg-wood-200"></div>
          <div class="flex-1 h-2 bg-wood-800"></div>
          <div class="flex-1 h-2 bg-wood-900"></div>
        </div>
        <p class="mb-12 text-base text-stone-300">Cada especie es un maestro silencioso. Seleccionamos las mejores cepas
          por su potencia sanadora y su belleza estructural.</p>
      </div>
      <div class="justify-center max-w-6xl px-4 py-4 mx-auto">
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3 md:grid-cols-2">
          @foreach($strains as $strain)
            <div wire:key="{{ $strain->id }}"
              class="group bg-white/5 backdrop-blur-md rounded-2xl shadow-lg hover:shadow-orange-900/20 hover:-translate-y-2 transition-all duration-500 overflow-hidden border border-white/10 hover:border-orange-500/30">
              <a href="/products?selected_strains[0]={{ $strain->id }}" class="block overflow-hidden">
                <img src="{{ asset('storage/' . $strain->image) }}" alt="Cepa de {{ $strain->name }} cultivo orgánico"
                  class="object-cover w-full h-72 group-hover:scale-110 group-hover:rotate-1 transition-transform duration-700"
                  loading="lazy">
              </a>
              <div class="p-6 text-center">
                <h3 class="text-xl font-bold text-stone-100 uppercase tracking-widest">{{ $strain->name }}</h3>
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
      <img src="{{ asset('images/bg_pioppino.webp') }}" alt="Fondo de hongos Pioppino"
        class="object-cover w-full h-full opacity-30" loading="lazy">
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
                  src="{{ asset('storage/' . $category->image) }}" alt="Categoría {{ $category->name }} Bogotá"
                  loading="lazy">
                <div class="mt-4">
                  <h3 class="text-lg font-semibold text-stone-200 group-hover:text-gold-ignia">{{ $category->name }}</h3>
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

  {{-- Sección Deshidratados --}}
  <div class="bg-stone-900/50 py-16 border-y border-gold-ignia/10">
    <div class="max-w-[85rem] px-4 mx-auto grid lg:grid-cols-2 gap-12 items-center">
      <div class="order-2 lg:order-1">
        <h2 class="text-3xl font-bold text-white mb-6">El Secreto del <span class="text-orange-500">Sabor Umami</span>
        </h2>
        <p class="text-stone-300 mb-6 text-lg">Nuestros hongos deshidratados no solo duran más; concentran sus
          propiedades y sabores. Ideales para risottos, caldos medicinales o para tener siempre medicina natural en tu
          despensa.</p>
        <ul class="space-y-4 text-stone-400">
          <li class="flex items-start gap-3">
            <svg class="text-gold-ignia w-5 h-5 mt-1 flex-shrink-0" fill="none" stroke="currentColor"
              viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span><strong class="text-orange-200">Shiitake Deshidratado:</strong> Potencia el sistema inmune y aporta un
              sabor profundo.</span>
          </li>
          <li class="flex items-start gap-3">
            <svg class="text-gold-ignia w-5 h-5 mt-1 flex-shrink-0" fill="none" stroke="currentColor"
              viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span><strong class="text-orange-200">Melena de León:</strong> Polvo y trozos para biohacking, enfoque y
              regeneración neuronal.</span>
          </li>
        </ul>
      </div>
      <div class="relative order-1 lg:order-2 px-4 lg:px-0">
        <div class="relative inline-block w-full">
          <a href="/products" class="block transition-transform duration-300 hover:scale-[1.01]">
            <div
              class="absolute -bottom-4 -right-2 md:-right-6 bg-orange-600 p-4 md:p-6 rounded-xl shadow-xl z-30 max-w-[150px] md:max-w-none">
              <p class="text-white font-bold text-lg md:text-xl">+6 meses</p>
              <p class="text-orange-100 text-xs md:text-sm leading-tight">Vida útil garantizada</p>
            </div>
          </a>
        </div>
      </div>
    </div>
  </div>

  {{-- Reviews Section Start --}}
  <div class="bg-stone-950 py-20 border-t border-white/5 relative z-20">
    <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 mx-auto">
      <div class="max-w-xl mx-auto text-center mb-16">
        <h2 class="text-3xl font-bold text-white mb-4">Lo que dicen nuestros <span
            class="text-gold-ignia">Clientes</span></h2>
        <p class="text-stone-400">La comunidad fungi crece cada día. Historias reales de sanación y sabor.</p>
      </div>

      @if(isset($reviews) && count($reviews) > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
          @foreach($reviews as $review)
            <div wire:key="{{ $loop->index }}"
              class="bg-stone-900 border border-stone-800 p-6 rounded-2xl relative flex flex-col h-full">
              <div class="flex items-center gap-x-1 text-gold-ignia mb-4">
                @for($i = 0; $i < 5; $i++)
                  @if($i < $review->rating)
                    <svg class="size-4 fill-current" viewBox="0 0 20 20">
                      <path
                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                  @else
                    <svg class="size-4 text-stone-700 fill-current" viewBox="0 0 20 20">
                      <path
                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                  @endif
                @endfor
              </div>
              <div class="flex-grow">
                <p class="text-stone-300 italic mb-6 line-clamp-4">"{{ $review->text }}"</p>
              </div>
              <div class="flex items-center gap-x-4 mt-auto pt-4 border-t border-stone-800">
                <div class="shrink-0">
                  @if(!empty($review->profile_photo_url))
                    <img src="{{ $review->profile_photo_url }}" alt="Reseña de {{ $review->author_name }}"
                      class="w-10 h-10 rounded-full object-cover" loading="lazy">
                  @else
                    <div class="w-10 h-10 rounded-full bg-stone-700 flex items-center justify-center text-white font-bold">
                      {{ substr($review->author_name, 0, 1) }}
                    </div>
                  @endif
                </div>
                <div>
                  <h4 class="text-white font-semibold text-sm">{{ $review->author_name }}</h4>
                  <p class="text-xs text-stone-500">{{ $review->relative_time_description }}</p>
                </div>
                <img src="https://upload.wikimedia.org/wikipedia/commons/2/2f/Google_2015_logo.svg"
                  class="h-5 ml-auto opacity-50 contrast-0 grayscale invert" alt="Google">
              </div>
            </div>
          @endforeach
        </div>
      @else
        <div
          class="max-w-3xl mx-auto text-center mb-12 p-8 rounded-2xl bg-gradient-to-br from-stone-900 to-stone-800 border border-gold-ignia/20 shadow-[0_0_30px_rgba(234,179,8,0.05)]">
          <h3 class="text-2xl font-bold text-orange-100 mb-4 font-serif">¿Ya probaste nuestra cosecha?</h3>
          <p class="text-stone-300 mb-8 max-w-xl mx-auto">Tu opinión es vital para que nuestro micelio siga creciendo.
            Cuéntanos tu experiencia y ayuda a otros a descubrir el poder de los hongos.</p>
          <a href="https://g.page/r/CeaSqLtP62KVEBI/review" target="_blank"
            class="inline-flex items-center gap-3 px-8 py-3 bg-gold-ignia/90 hover:bg-gold-ignia text-stone-950 font-bold rounded-full transition-all duration-300 hover:scale-105 hover:shadow-[0_0_20px_rgba(234,179,8,0.4)]">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
              <path
                d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15h-2v-6h2v6zm-1-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1z" />
            </svg>
            Déjanos tu opinión en Google
          </a>
        </div>
      @endif

      <div class="text-center mt-12">
        <a href="https://g.page/r/CeaSqLtP62KVEBI/review" target="_blank"
          class="inline-flex justify-center items-center gap-x-3 text-center bg-white hover:bg-gray-100 border border-transparent text-black text-sm font-medium rounded-full py-3 px-6 transition-all duration-300 shadow-lg hover:shadow-xl">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
            <path
              d="M12.545,10.239v3.821h5.445c-0.712,2.315-2.647,3.972-5.445,3.972c-3.332,0-6.033-2.701-6.033-6.032s2.701-6.032,6.033-6.032c1.498,0,2.866,0.549,3.921,1.453l2.814-2.814C17.503,2.988,15.139,2,12.545,2C7.021,2,2.543,6.477,2.543,12s4.478,10,10.002,10c8.396,0,10.249-7.85,9.426-11.748L12.545,10.239z" />
          </svg>
          Leer más reseñas en Google
        </a>
      </div>
    </div>
  </div>
  {{-- Reviews Section End --}}


  <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "LocalBusiness",
        "name": "Ignia Fungi",
        "image": "{{ asset('images/bg_hericium.webp') }}",
        "description": "Cultivo orgánico de hongos gourmet y medicinales en Bogotá. Shiitake, Melena de León y Eryngii frescos y deshidratados.",
        "telephone": "+573508418843",
        "address": {
          "@@type": "PostalAddress",
          "addressLocality": "Bogotá",
          "addressCountry": "CO"
        },
        "url": "{{ url('/') }}",
        "priceRange": "$$"
    }
  </script>
  <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@@type": "FAQPage",
      "mainEntity": [{
        "@@type": "Question",
        "name": "¿Hacen envíos de hongos frescos en Bogotá?",
        "acceptedAnswer": {
          "@@type": "Answer",
          "text": "Sí, realizamos envíos de Shiitake, Eryngii y Melena de León frescos refrigerados a toda Bogotá, garantizando su textura y sabor."
        }
      }, {
        "@@type": "Question",
        "name": "¿Cuáles son los beneficios de la Melena de León?",
        "acceptedAnswer": {
          "@@type": "Answer",
          "text": "La Melena de León es conocida por sus propiedades nootrópicas que favorecen la concentración, la memoria y la regeneración neuronal (NGF)."
        }
      }, {
        "@@type": "Question",
        "name": "¿Cómo conservo los hongos deshidratados?",
        "acceptedAnswer": {
          "@@type": "Answer",
          "text": "Nuestros hongos deshidratados tienen una vida útil de +6 meses si se mantienen en un lugar fresco y seco, o en su empaque sellado original."
        }
      }]
    }
  </script>
</div>