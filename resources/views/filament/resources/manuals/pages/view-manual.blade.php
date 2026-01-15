<x-filament-panels::page>
    <div class="flex flex-col gap-6">
        <!-- Header Section -->
        <div class="p-6 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-start justify-between">
                <div class="flex gap-4">
                    @if($record->icon)
                        <div
                            class="text-4xl bg-gray-50 dark:bg-gray-900 p-3 rounded-lg border border-gray-100 dark:border-gray-600">
                            {{ $record->icon }}
                        </div>
                    @endif
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl">
                            {{ $record->title }}
                        </h1>
                        <div class="mt-2 flex items-center gap-2">
                            <x-filament::badge :color="match ($record->category) {
        'Usuario' => 'success',
        'Negocio' => 'info',
        'Técnico' => 'warning',
        default => 'gray',
    }">
                                {{ $record->category }}
                            </x-filament::badge>

                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                Actualizado {{ $record->updated_at->diffForHumans() }}
                            </span>
                        </div>
                    </div>
                </div>

                @if($record->user)
                    <div class="text-right hidden sm:block">
                        <span class="text-xs text-gray-400 block pb-1">Editado por:</span>
                        <div class="flex items-center gap-2 justify-end">
                            <span class="font-medium text-sm text-gray-700 dark:text-gray-300">
                                {{ $record->user->name }}
                            </span>
                            <x-filament::avatar :src="$record->user->avatar_url" size="sm" />
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Content Section -->
        <div class="p-8 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
            <article class="prose prose-slate lg:prose-lg dark:prose-invert max-w-none">
                {!! $record->content !!}
            </article>
        </div>
    </div>
</x-filament-panels::page>