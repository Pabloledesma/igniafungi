<div class="w-full max-w-[85rem] py-10 px-4 sm:px-6 lg:px-8 mx-auto">
  <div class="flex h-full items-center">
    <main class="w-full max-w-md mx-auto p-6">
      <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
        <div class="p-4 sm:p-7">
          <div class="text-center">
            <h1 class="block text-2xl font-bold text-gray-800">Login</h1>
            <p class="mt-2 text-sm text-gray-600">
              Aun no tienes una cuenta?
              <a wire:navigate class="text-gold-ignia decoration-2 hover:underline font-medium focus:outline-none focus:ring-1 focus:ring-gray-600" href="/register">
                Registrate aquí
              </a>
            </p>
          </div>

          <hr class="my-5 border-slate-300">

          <form wire:submit.prevent='save'>
            @if (session('error'))
              <div class="mt-2 bg-red-500 text-sm text-white rounded-lg p-4 mb-4" role="alert">
                <span class="font-bold">{{ session('error') }}</span> 
              </div>
            @endif

            <div class="grid gap-y-4">
              <div>
                <label for="email" class="block text-sm mb-2 text-gray-800 font-medium">Email</label>
                <div class="relative">
                  <input type="email" id="email" wire:model="email" class="py-3 px-4 block w-full border border-gray-300 bg-white text-gray-800 rounded-lg text-sm focus:border-gold-ignia focus:ring-gold-ignia disabled:opacity-50 disabled:pointer-events-none" required>
                  @error('email')
                    <div class="absolute inset-y-0 end-0 flex items-center pointer-events-none pe-3">
                      <svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z" />
                      </svg>
                    </div>
                  @enderror
                </div>
                @error('email')
                  <p class="text-xs text-red-600 mt-2">{{ $message }}</p>
                @enderror
              </div>

              <div>
                <div class="flex justify-between items-center">
                  <label for="password" class="block text-sm mb-2 text-gray-800 font-medium">Contraseña</label>
                  <a wire:navigate class="text-sm text-gold-ignia decoration-2 hover:underline font-medium" href="/forgot">Olvidaste la contraseña?</a>
                </div>
                <div class="relative">
                  <input type="password" id="password" wire:model="password" class="py-3 px-4 block w-full border border-gray-300 bg-white text-gray-800 rounded-lg text-sm focus:border-gold-ignia focus:ring-gold-ignia disabled:opacity-50 disabled:pointer-events-none" required>
                  @error('password')
                    <div class="absolute inset-y-0 end-0 flex items-center pointer-events-none pe-3">
                      <svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z" />
                      </svg>
                    </div>
                  @enderror
                </div>
                @error('password')
                  <p class="text-xs text-red-600 mt-2">{{ $message }}</p>
                @enderror
              </div>

              <button type="submit" class="w-full py-3 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-gold-ignia text-white hover:opacity-90 transition-all disabled:opacity-50 disabled:pointer-events-none">Iniciar sesión</button>
            </div>
          </form>
        </div>
      </div>
    </main>
  </div>
</div>