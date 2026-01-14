<footer class="bg-white w-full border-t border-gray-100">
  <div class="w-full max-w-[85rem] py-10 px-4 sm:px-6 lg:px-8 lg:pt-20 mx-auto">
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6">
      <div class="col-span-full lg:col-span-1">
        <a class="flex-none text-xl font-bold text-stone-900 focus:outline-none" href="{{ route('home') }}"
          aria-label="Ignia Fungi">
          <span class="text-gold-ignia">Ignia</span> Fungi
        </a>
        <p class="mt-3 text-xs text-gray-500 leading-relaxed">
          Transformando el fuego en vida a través del reino fungi.
        </p>
      </div>

      <div class="col-span-1">
        <h4 class="font-bold text-stone-900 uppercase tracking-wider text-xs">Producto</h4>

        <div class="mt-3 grid space-y-3">
          <p><a class="inline-flex gap-x-2 text-gray-600 hover:text-gold-ignia transition text-sm"
              href="{{ route('categories') }}">Categorías</a></p>
          <p><a class="inline-flex gap-x-2 text-gray-600 hover:text-gold-ignia transition text-sm"
              href="{{ route('products') }}">Todos los productos</a></p>
          <p><a class="inline-flex gap-x-2 text-gray-600 hover:text-gold-ignia transition text-sm"
              href="{{ route('products', ['featured' => true]) }}">Productos destacados</a></p>
        </div>
      </div>

      <div class="col-span-1">
        <h4 class="font-bold text-stone-900 uppercase tracking-wider text-xs">Empresa</h4>

        <div class="mt-3 grid space-y-3">
          <p><a class="inline-flex gap-x-2 text-gray-600 hover:text-gold-ignia transition text-sm"
              href="{{ route('about') }}">Sobre nosotros</a></p>
          <p><a class="inline-flex gap-x-2 text-gray-600 hover:text-gold-ignia transition text-sm"
              href="{{ route('blog.index') }}">Blog Fungi</a></p>
        </div>
      </div>

      <div class="col-span-2">
        <h4 class="font-bold text-stone-900 uppercase tracking-wider text-xs">Mantente conectado</h4>

        <form>
          <div
            class="mt-4 flex flex-col items-center gap-2 sm:flex-row sm:gap-3 bg-gray-50 border border-gray-200 rounded-xl p-2">
            <div class="w-full">
              <input type="email" id="footer-input"
                class="py-3 px-4 block w-full border-transparent bg-transparent rounded-lg text-sm focus:ring-0 focus:border-transparent"
                placeholder="Tu correo electrónico">
            </div>
            <button type="submit"
              class="w-full sm:w-auto whitespace-nowrap py-3 px-6 inline-flex justify-center items-center gap-x-2 text-sm font-bold rounded-lg bg-gold-ignia text-white hover:bg-stone-800 transition shadow-md">
              Suscribirse
            </button>
          </div>
          <p class="mt-3 text-[10px] text-gray-400 italic text-center sm:text-left">
            Recibe consejos de cultivo y ofertas exclusivas.
          </p>
        </form>
      </div>
    </div>

    <div
      class="mt-5 sm:mt-12 border-t border-gray-100 pt-5 grid gap-y-4 sm:gap-y-0 sm:flex sm:justify-between sm:items-center">
      <div>
        <p class="text-xs text-gray-500">© 2026 Ignia Fungi. Cosechado con ❤️ en Bogotá.</p>
      </div>

      <div class="flex items-center gap-x-3">
        <!-- Google Reviews Badge -->
        <a href="https://g.page/r/CeaSqLtP62KVEBI/review" target="_blank"
          class="hidden sm:flex items-center gap-x-2 mr-4 bg-white border border-gray-200 rounded-full px-3 py-1 hover:shadow-sm transition">
          <img src="https://upload.wikimedia.org/wikipedia/commons/2/2f/Google_2015_logo.svg" alt="Google" class="w-4">
          <div class="flex items-center gap-x-1">
            <span class="text-[10px] font-bold text-gray-800">4.9</span>
            <div class="flex text-amber-400">
              @for($i = 0; $i < 5; $i++)
                <svg class="size-2 fill-current" viewBox="0 0 20 20">
                  <path
                    d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
              @endfor
            </div>
          </div>
        </a>

        <a class="w-8 h-8 inline-flex justify-center items-center rounded-full border border-gray-100 text-gray-600 hover:bg-gold-ignia hover:text-white transition"
          href="https://instagram.com/ignia_fungi" target="_blank">
          <svg class="size-4" fill="currentColor" viewBox="0 0 16 16">
            <path
              d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.917 3.917 0 0 0-1.417.923A3.927 3.927 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.916 3.916.0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.197-.509.332-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.926 3.926 0 0 0-.923-1.417A3.911 3.911.0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0h.003zm-.717 1.442h.718c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599.28.28.453.546.598.92.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.47 2.47 0 0 1-.599.919c-.28.28-.546.453-.92.598-.282.11-.705.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.478 2.478 0 0 1-.92-.598 2.48 2.48 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233 0-2.136.008-2.388.046-3.231.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92.28-.28.546-.453.92-.598.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045v.002zm4.988 1.328a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92zm-4.27 1.122a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217zm0 1.441a2.667 2.667 0 1 1 0 5.334 2.667 2.667 0 0 1 0-5.334z" />
          </svg>
        </a>
        <a class="w-8 h-8 inline-flex justify-center items-center rounded-full border border-gray-100 text-gray-600 hover:bg-blue-600 hover:text-white transition"
          href="https://facebook.com/igniafungi" target="_blank">
          <svg class="size-4" fill="currentColor" viewBox="0 0 16 16">
            <path
              d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951z" />
          </svg>
        </a>
      </div>
    </div>
  </div>
</footer>