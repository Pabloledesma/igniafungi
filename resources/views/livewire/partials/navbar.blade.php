<header class="flex flex-wrap lg:justify-start lg:flex-nowrap z-50 w-full py-7 bg-white dark:bg-neutral-900 shadow-sm lg:shadow-none">
  <nav class="relative max-w-7xl w-full flex items-center justify-between px-4 md:px-6 lg:px-8 mx-auto">
  
  <div class="flex items-center gap-x-2.5 flex-none">
    <a class="flex-none rounded-xl text-xl inline-block font-semibold focus:outline-none focus:opacity-80" href="{{ route('home') }}" aria-label="Ignia Fungi">
      <img src="{{ asset('images/logo_ignia_sin_texto.png') }}" alt="Logo Ignia Fungi" class="h-10 lg:h-12 w-auto object-contain"> 
    </a>
    <span class="text-gold-ignia text-[10px] tracking-[0.2em] font-medium uppercase whitespace-nowrap hidden sm:inline-block pt-1">
        Ignia Fungi
    </span>
  </div>

  <div class="flex items-center gap-x-2 flex-none">
    <div class="lg:hidden">
      <button type="button" class="hs-collapse-toggle size-9 flex justify-center items-center rounded-xl border border-gray-200 dark:border-neutral-700 text-gray-800 dark:text-neutral-200 hover:bg-gray-100 dark:hover:bg-neutral-800 focus:outline-none" data-hs-collapse="#hs-navbar-primary">
        <svg class="hs-collapse-open:hidden shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="3" x2="21" y1="6" y2="6"/>
          <line x1="3" x2="21" y1="12" y2="12"/>
          <line x1="3" x2="21" y1="18" y2="18"/>
        </svg>
        <svg class="hs-collapse-open:block hidden shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 6 6 18"/>
          <path d="m6 6 12 12"/>
        </svg>
      </button>
    </div>
  </div>

 <div id="hs-navbar-primary" class="hs-collapse hidden overflow-hidden transition-all duration-300 basis-full grow lg:block lg:w-auto px-2 lg:px-0">
  <div class="flex flex-col gap-y-4 gap-x-0 mt-5 pb-4 lg:pb-0 lg:flex-row lg:justify-center lg:items-center lg:gap-y-0 lg:gap-x-8 lg:mt-0">
    @php
      $navItems = [
          ['name' => 'Home', 'route' => 'home'],
          ['name' => 'Categorias', 'route' => 'categories'],
          ['name' => 'Productos', 'route' => 'products'],
          ['name' => 'Blog', 'route' => 'blog.index'],
      ];
    @endphp

    @foreach($navItems as $item)
      <a wire:navigate 
         class="relative inline-block text-black dark:text-white hover:text-orange-600 transition py-1 lg:py-0
         {{ request()->routeIs($item['route']) ? 'font-bold before:absolute before:bottom-[-4px] before:start-0 before:w-full before:h-0.5 before:bg-gold-ignia' : '' }}" 
         href="{{ route($item['route']) }}">
        {{ $item['name'] }}
      </a>
    @endforeach
  </div>
</div>

  <div class="flex items-center gap-x-2 flex-none">
    
    <a wire:navigate href="{{ route('cart') }}" class="size-9 relative flex justify-center items-center rounded-xl bg-stone-100 dark:bg-neutral-800 text-black dark:text-white hover:bg-stone-200 transition">
      <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
      <span class="absolute -top-1.5 -right-1.5 size-4 bg-orange-600 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white dark:border-neutral-900">
        {{ $total_count }}
      </span>
    </a>

    @guest
      <a wire:navigate href="{{ route('login') }}" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-xl bg-gold-ignia text-black hover:bg-yellow-500 transition">
        Login
      </a>
    @endguest

    @auth
    <div class="hs-dropdown relative inline-flex [--placement:bottom-right]">
      <button id="hs-dropdown-account" type="button" class="hs-dropdown-toggle size-9 inline-flex justify-center items-center rounded-xl border border-gray-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 overflow-hidden">
        <img class="size-full object-cover" src="https://ui-avatars.com/api/?name={{ auth()->user()->name }}&background=b18a2e&color=fff" alt="Avatar">
      </button>

      <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-48 bg-white dark:bg-neutral-800 shadow-xl rounded-xl p-2 mt-2 z-[60] border border-gray-200 dark:border-neutral-700" aria-labelledby="hs-dropdown-account">
        <a class="flex items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-gray-800 dark:text-neutral-300 hover:bg-gray-100 dark:hover:bg-neutral-700" href="/my-orders">Mis pedidos</a>
        <div class="my-1 border-t border-gray-200 dark:border-neutral-700"></div>
        <a class="flex items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-red-600 hover:bg-gray-50" href="/logout">Salir</a>
      </div>
    </div>
    @endauth

  </div>
</nav>
</header>