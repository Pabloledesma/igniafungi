<header class="sticky top-4 inset-x-0 flex flex-wrap md:justify-start md:flex-nowrap z-50 w-full before:absolute before:inset-0 before:max-w-5xl before:mx-2 lg:before:mx-auto before:rounded-[26px] before:bg-neutral-800/30 before:backdrop-blur-md">
    <nav class="relative max-w-5xl w-full flex flex-wrap md:flex-nowrap basis-full items-center justify-between py-2 ps-5 pe-2 md:py-0 mx-2 lg:mx-auto">
    
      <div class="flex items-center justify-center"> 
        <a class="flex flex-col items-center justify-center rounded-md text-xl font-semibold focus:outline-hidden focus:opacity-80" href="#" aria-label="Ignia Fungi">
            <img src="{{ asset('images/logo_ignia_sin_texto.png') }}" 
                alt="Logo Ignia Fungi" 
                class="h-12 w-auto object-contain mb-1">
            
            <span class="text-gold-ignia text-[10px] tracking-[0.2em] font-medium uppercase whitespace-nowrap">
                Ignia Fungi
            </span>
        </a>
      </div>

      <div class="relative md:flex md:items-center md:justify-between">
      <div class="flex items-center justify-between">
        <a href="/" class="flex flex-col items-center py-2">
        </a>
        <div class="md:hidden">
          <button type="button" class="hs-collapse-toggle flex justify-center items-center w-9 h-9 text-sm font-semibold rounded-lg border border-gray-200 text-gray-800 hover:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:text-white dark:border-gray-700 dark:hover:bg-gray-700 dark:focus:outline-none dark:focus:ring-1 dark:focus:ring-gray-600" data-hs-collapse="#navbar-collapse-with-animation" aria-controls="navbar-collapse-with-animation" aria-label="Toggle navigation">
            <svg class="hs-collapse-open:hidden flex-shrink-0 w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="3" x2="21" y1="6" y2="6" />
              <line x1="3" x2="21" y1="12" y2="12" />
              <line x1="3" x2="21" y1="18" y2="18" />
            </svg>
            <svg class="hs-collapse-open:block hidden flex-shrink-0 w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6 6 18" />
              <path d="m6 6 12 12" />
            </svg>
          </button>
        </div>
      </div>

      <div id="navbar-collapse-with-animation" class="hs-collapse hidden overflow-hidden md:overflow-visible transition-all duration-300 basis-full grow md:block">
        <div class="md:overflow-visible overflow-hidden overflow-y-auto max-h-[75vh] md:max-h-none [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-track]:bg-gray-100 [&::-webkit-scrollbar-thumb]:bg-gray-300 dark:[&::-webkit-scrollbar-track]:bg-slate-700 dark:[&::-webkit-scrollbar-thumb]:bg-slate-500">
          <div class="flex flex-col gap-x-0 mt-5 divide-y divide-dashed divide-gray-200 md:flex-row md:items-center md:justify-end md:gap-x-7 md:mt-0 md:ps-7 md:divide-y-0 md:divide-solid dark:divide-gray-700">

            <a wire:navigate class="font-medium {{ request()->is('/') ? 'text-gold-ignia' : 'text-wood-950' }} py-3 md:py-6 dark:focus:outline-none dark:focus:ring-1 dark:focus:ring-gray-600" href="/" aria-current="page">Home</a>

            <a wire:navigate class="font-medium {{ request()->is('categories') ? 'text-gold-ignia dark:text-blue-500' : 'text-wood-200 dark:text-gray-400 dark:hover:text-wood-200 dark:focus:ring-gray-600' }} hover:text-gray-400 py-3 md:py-6 dark:focus:outline-none dark:focus:ring-1" href="/categories">
              Categorias
            </a>

            <a wire:navigate class="font-medium {{ request()->is('products') ? 'text-gold-ignia' : 'text-wood-200 dark:text-gray-400' }} hover:text-gray-400 py-3 md:py-6 dark:hover:text-wood-200 dark:focus:outline-none dark:focus:ring-1 dark:focus:ring-gray-600" href="/products">
              Productos
            </a>

            <a wire:navigate class="font-medium flex items-center {{ request()->is('cart') ? 'text-gold-ignia' : 'text-wood-200 dark:text-gray-400' }} hover:text-gray-400 py-3 md:py-6 dark:hover:text-wood-200 dark:focus:outline-none dark:focus:ring-1 dark:focus:ring-gray-600" href="/cart">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="flex-shrink-0 w-5 h-5 mr-1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
              </svg>
              <span class="mr-1">Cart</span>
              <span class="py-0.5 px-1.5 rounded-full text-xs font-medium bg-blue-50 border border-blue-200 text-gold-ignia">
                {{ $total_count }}
              </span>
            </a>
            @guest
              <div class="pt-3 md:pt-0">
                <a wire:navigate class="py-2.5 px-4 inline-flex items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-gold-ignia text-white transition-all duration-300 hover:bg-orange-500 hover:scale-105 hover:shadow-[0_0_20px_5px_rgba(234,88,12,0.6)] active:scale-95" href="/login">
                  <span class="absolute inset-0 rounded-lg bg-orange-400 opacity-0 group-hover:opacity-20 blur-md transition-opacity duration-300"></span>
                  <span class="relative z-10">Login</span>
                  <svg class="frelative z-10 flex-shrink-0 w-4 h-4 transition-transform duration-500 group-hover:rotate-12" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                  </svg>
                  
                </a>
              </div>
            @endguest
            
           @auth
            <div class="hs-dropdown relative [--strategy:static] md:[--strategy:fixed] md:py-4">
              <button id="hs-dropdown-account" type="button" class="flex items-center w-full text-wood-200 hover:text-gray-400 font-medium dark:text-gray-400">
                {{ auth()->user()->name }}
                <svg class="ms-2 w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
              </button>

              <div class="hs-dropdown-menu transition-[opacity,margin] duration-[150ms] hs-dropdown-open:opacity-100 opacity-0 md:w-48 hidden z-[100] bg-white md:shadow-md rounded-lg p-2 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 absolute md:absolute md:top-full right-0 md:right-0 mt-2">
                
                <a wire:navigate class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700" href="/my-orders">
                  My Orders
                </a>

                <a wire:navigate class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700" href="#">
                  My Account
                </a>
                
                <hr class="my-2 dark:border-gray-700">

                <a wire:navigate class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-700" href="/logout">
                  Logout
                </a>
              </div>
            </div>
            @endauth

          </div>
        </div>
      </div>
    </div>
  </nav>
</header>