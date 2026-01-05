<x-layouts.app> <div class="min-h-[70vh] flex flex-col items-center justify-center px-4">
        <div class="max-w-md w-full bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100">
            
            <div class="bg-gold-ignia p-6 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-full mb-4 shadow-sm">
                    <svg class="w-8 h-8 text-gold-ignia" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-white uppercase tracking-wider">Verifica tu correo</h2>
            </div>

            <div class="p-8 text-center">
                <p class="text-gray-600 leading-relaxed mb-6">
                    ¡Casi listo! Hemos enviado un enlace de activación a tu correo electrónico. 
                    <span class="font-semibold text-gray-800">Por favor, haz clic en el enlace para habilitar tu cuenta y continuar con tu pedido.</span>
                </p>

                @if (session('status') == 'verification-link-sent')
                    <div class="mb-6 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700 font-medium animate-bounce">
                        ✨ ¡Nuevo enlace enviado! Revisa tu bandeja de entrada o spam.
                    </div>
                @endif

                <div class="space-y-4">
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <button type="submit" class="w-full bg-slate-900 hover:bg-black text-white font-bold py-3 px-4 rounded-xl transition-all duration-300 transform hover:scale-[1.02] shadow-lg">
                            No recibí el correo, reenviar
                        </button>
                    </form>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-gray-400 hover:text-gold-ignia underline transition-colors">
                            Cerrar sesión e intentar después
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-gray-50 p-4 border-t border-gray-100">
                <p class="text-[10px] text-gray-400 uppercase tracking-widest">
                    Ignia Fungi - Producción Sostenible de Hongos
                </p>
            </div>
        </div>
    </div>
</x-layouts.app>