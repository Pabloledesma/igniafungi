<x-layouts.app>
    @section('title', 'Manuales y Documentación')

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="text-center mb-12">
            <h1 class="text-4xl font-extrabold text-gray-900 dark:text-gray-100 sm:text-5xl">
                🍄 Documentación Ignia Fungi
            </h1>
            <p class="mt-4 text-xl text-gray-500 dark:text-gray-400">
                Guías operativas, reglas de negocio y manuales técnicos.
            </p>
        </div>

        <div class="space-y-12">
            @foreach($manuals as $category => $categoryManuals)
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex items-center gap-3">
                        <span class="inline-flex items-center justify-center p-2 rounded-lg 
                            @match($category)
                                'Usuario' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                'Negocio' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                'Técnico' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
                            @endmatch
                        ">
                            @switch($category)
                                @case('Usuario') 👥 @break
                                @case('Negocio') 💼 @break
                                @case('Técnico') 🔧 @break
                                @default 📁
                            @endswitch
                        </span>
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white">{{ $category }}</h2>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6">
                        @foreach($categoryManuals as $manual)
                            <a href="{{ route('wiki.show', $manual->slug) }}" 
                               class="group relative flex flex-col p-6 bg-white dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-green-500 dark:hover:border-green-500 hover:shadow-lg transition-all duration-300">
                                
                                <div class="flex items-center justify-between mb-4">
                                    <span class="text-3xl filter grayscale group-hover:grayscale-0 transition-all duration-300 transform group-hover:scale-110">
                                        {{ $manual->icon ?? '📄' }}
                                    </span>
                                    <svg class="w-5 h-5 text-gray-300 group-hover:text-green-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </div>

                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white group-hover:text-green-600 dark:group-hover:text-green-400 mb-2">
                                    {{ $manual->title }}
                                </h3>
                                
                                <div class="mt-auto pt-4 flex items-center justify-between text-sm text-gray-500 dark:text-gray-400 border-t border-gray-100 dark:border-gray-700/50">
                                    <span>{{ $manual->updated_at->format('d M, Y') }}</span>
                                    @if($manual->user)
                                        <span class="flex items-center gap-1">
                                            {{ $manual->user->name }}
                                            <div class="w-5 h-5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                                @if($manual->user->avatar_url)
                                                    <img src="{{ $manual->user->avatar_url }}" class="w-full h-full object-cover">
                                                @endif
                                            </div>
                                        </span>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.app>

