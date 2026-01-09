<!-- Card Blog -->
<div class="max-w-[85rem] px-4 py-10 sm:px-6 lg:px-8 lg:py-14 mx-auto">
  <!-- Title -->
  <div class="max-w-2xl mx-auto text-center mb-10 lg:mb-14">
    <h2 class="text-2xl font-bold md:text-4xl md:leading-tight dark:text-white">Blog</h2>
    <p class="mt-1 text-gray-600 dark:text-neutral-400">Explora el mundo fungi con recetas, consejos de cultivo y beneficios para tu salud desde nuestra granja en Bogotá.</p>
  </div>
  <!-- End Title -->
  <!-- Grid -->
  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
  @forelse ($posts as $post)
  
        <!-- Card -->
        <a class="group flex flex-col h-full border border-gray-200 hover:border-transparent hover:shadow-lg focus:outline-hidden focus:border-transparent focus:shadow-lg transition duration-300 rounded-xl p-5 dark:border-neutral-700 dark:hover:border-transparent dark:hover:shadow-black/40 dark:focus:border-transparent dark:focus:shadow-black/40" 
            href="{{ route('blog.show', $post->slug) }}">
        <div class="aspect-w-16 aspect-h-11">
            <img 
                class="w-full object-cover rounded-xl" 
                src="{{ $post->image ? asset('storage/' . $post->image) : 'https://via.placeholder.com/400x250' }}" 
                alt="{{ $post->title }}">
        </div>
        <div class="my-6">
            <h3 class="text-xl font-semibold text-gray-800 dark:text-neutral-300 dark:group-hover:text-white">
            {{ $post->title }}
            </h3>
            <p class="mt-5 text-gray-600 dark:text-neutral-400">
            {{ $post->summary }}
            </p>
        </div>
        <div class="mt-auto flex items-center gap-x-3">
            <img class="size-8 rounded-full" 
                src="{{ asset('images/logo_ignia_sin_texto.png') }}" alt="Logo Ignia fungi">
            <div><h5 class="text-sm text-gray-800 dark:text-neutral-200">Por Ignia Fungi</h5></div>
        </div>
        </a>
        <!-- End Card -->
      
  @empty
    <div class="group flex flex-col h-full border border-gray-200 hover:border-transparent hover:shadow-lg focus:outline-hidden focus:border-transparent focus:shadow-lg transition duration-300 rounded-xl p-5 dark:border-neutral-700 dark:hover:border-transparent dark:hover:shadow-black/40 dark:focus:border-transparent dark:focus:shadow-black/40" href="#">
        Proximamente...
    </div>
  @endforelse


  </div>
  <!-- End Grid -->

  <!-- Card -->
  <div class="mt-12 text-center">
    <a href="{{ route('blog.index') }}" class="py-3 px-6 inline-flex items-center gap-x-2 text-sm font-bold rounded-full border border-gray-200 bg-white text-gray-800 hover:bg-gold-ignia hover:text-white transition-all shadow-sm">
      Ver más artículos
      <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
    </a>
</div>
  <!-- End Card -->
</div>
<!-- End Card Blog -->


