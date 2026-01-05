<div class="w-full max-w-[85rem] py-10 px-4 sm:px-6 lg:px-8 mx-auto">
  <div class="flex h-full items-center">
    <main class="w-full max-w-md mx-auto p-6">
      <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
        <div class="p-4 sm:p-7">
          <div class="text-center">
            <h1 class="block text-2xl font-bold text-gray-800">Sign up</h1>
            <p class="mt-2 text-sm text-gray-600">
              Already have an account?
              <a class="text-gold-ignia decoration-2 hover:underline font-medium focus:outline-none focus:ring-1 focus:ring-gray-600" href="/login">
                Sign in here
              </a>
            </p>
          </div>
          <hr class="my-5 border-slate-300">
          
          <form wire:submit.prevent='save'>
            <div class="grid gap-y-4">
              
              <div>
                <label for="name" class="block text-sm mb-2 text-gray-900 font-medium">Name</label>
                <div class="relative">
                  <input 
                    id="name" 
                    wire:model="name" 
                    type="text" 
                    class="py-3 px-4 block w-full border border-gray-300 bg-white text-gray-900 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50" 
                    placeholder="Tu nombre completo">

                    @error('name')
                    <div class="absolute inset-y-0 end-0 flex items-center pointer-events-none pe-3">
                      <svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z" />
                      </svg>
                    </div>
                    @enderror
                </div>
                @error('name')
                  <p class="text-xs text-red-600 mt-2">{{ $message }}</p>
                @enderror
              </div>

              <div>
                <label for="email" class="block text-sm mb-2 text-gray-900 font-medium">Email address</label>
                <div class="relative">
                  <input 
                    id="email" 
                    wire:model="email" 
                    type="email" 
                    class="py-3 px-4 block w-full border border-gray-300 bg-white text-gray-900 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50"
                    placeholder="ejemplo@correo.com">
                  
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
                <label for="password" class="block text-sm mb-2 text-gray-900 font-medium">Password</label>
                <div class="relative">
                  <input 
                    id="password" 
                    wire:model="password" 
                    type="password" 
                    class="py-3 px-4 block w-full border border-gray-300 bg-white text-gray-900 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50" 
                    placeholder="••••••••">
                  
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

              <button type="submit" class="w-full py-3 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-gold-ignia text-white hover:bg-black transition-colors disabled:opacity-50">
                Sign up
              </button>
            </div>
          </form>
          </div>
      </div>
    </main>
  </div>
</div>