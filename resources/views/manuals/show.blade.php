<x-layouts.app>
    @section('title', $manual->title)

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <!-- Breadcrumb / Back Navigation -->
        <nav class="flex mb-8" aria-label="Breadcrumb">
            <a href="{{ route('wiki.index') }}"
                class="group flex items-center text-sm font-medium text-gray-500 hover:text-green-600 dark:text-gray-400 dark:hover:text-green-400 transition-colors">
                <svg class="flex-shrink-0 w-5 h-5 mr-2 text-gray-400 group-hover:text-green-500 transition-colors"
                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd"
                        d="M7.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l2.293 2.293a1 1 0 010 1.414z"
                        clip-rule="evenodd" />
                </svg>
                Volver al Índice
            </a>
        </nav>

        <article
            class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <!-- Header -->
            <header class="px-8 py-10 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50">
                <div class="flex items-center gap-4 mb-6">
                    @if($manual->icon)
                        <span
                            class="text-5xl bg-white dark:bg-gray-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
                            {{ $manual->icon }}
                        </span>
                    @endif
                    <div class="space-y-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                            @match($manual->category)
                                'Usuario' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
                                'Negocio' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',
                                'Técnico' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300',
                                default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                            @endmatch
                        ">
                            {{ $manual->category }}
                        </span>
                        <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900 dark:text-white tracking-tight">
                            {{ $manual->title }}
                        </h1>
                    </div>
                </div>

                <div
                    class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400 pt-6 mt-6 border-t border-gray-200/60 dark:border-gray-700/60">
                    <div class="flex items-center gap-3">
                        @if($manual->user)
                            <div class="flex items-center gap-2">
                                <x-filament::avatar :src="$manual->user->avatar_url" size="md"
                                    class="w-8 h-8 rounded-full ring-2 ring-white dark:ring-gray-800" />
                                <span class="font-medium text-gray-700 dark:text-gray-300">
                                    {{ $manual->user->name }}
                                </span>
                            </div>
                        @endif
                        <span class="text-gray-300 dark:text-gray-600">•</span>
                        <span>Actualizado {{ $manual->updated_at->diffForHumans() }}</span>
                    </div>

                    @can('update', $manual)
                        <a href="/admin/manuals/{{$manual->id}}/edit" target="_blank"
                            class="inline-flex items-center gap-1.5 text-green-600 hover:text-green-700 font-medium transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Editar
                        </a>
                    @endcan
                </div>
            </header>

            <!-- Content -->
            <div class="px-8 py-10">
                <div class="prose prose-stone lg:prose-xl dark:prose-invert max-w-none 
                    prose-img:rounded-xl prose-img:shadow-lg prose-img:border prose-img:border-gray-200 dark:prose-img:border-gray-700
                    prose-a:text-green-600 hover:prose-a:text-green-500
                    prose-blockquote:border-l-green-500 prose-blockquote:bg-gray-50 dark:prose-blockquote:bg-gray-800/50 prose-blockquote:py-2 prose-blockquote:px-4 prose-blockquote:rounded-r-lg
                ">
                    {!! $manual->content !!}
                </div>
            </div>
        </article>
    </div>
</x-layouts.app>