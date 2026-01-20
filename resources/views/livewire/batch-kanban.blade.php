<div class="flex flex-col h-[calc(100vh-64px)] overflow-hidden bg-gray-100">
    <div class="mb-6 flex items-center space-x-4 bg-white p-4 rounded-lg shadow-sm">

        {{-- Buscador --}}
        <div class="relative">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar lote..."
                class="pl-3 pr-4 py-2 border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        {{-- Filtro Tipo de Lote --}}
        <div>
            <select wire:model.live="batchType"
                class="border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500 py-2 pl-3 pr-8">
                <option value="">Todos los Tipos</option>
                <option value="grain">Grano</option>
                <option value="bulk">Sustrato</option>
            </select>
        </div>

        {{-- Filtro Cepa --}}
        <div>
            <select wire:model.live="selectedStrain"
                class="border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500 py-2 pl-3 pr-8">
                <option value="">Todas las Cepas</option>
                @foreach($allStrains as $strain)
                    <option value="{{ $strain->id }}">{{ $strain->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="flex-1 flex overflow-x-auto p-6 gap-4 items-start h-full">
        @foreach($phases as $phase)
            <div class="flex-shrink-0 w-80 bg-gray-200 rounded-lg shadow-sm flex flex-col max-h-full">

                <div class="p-4 border-b border-gray-300 flex justify-between items-center bg-white rounded-t-lg">
                    <h3 class="font-bold text-gray-700 uppercase text-sm tracking-wider">{{ $phase->name }}</h3>
                    <span
                        class="bg-gray-500 text-white text-xs px-2 py-1 rounded-full">{{ $phase->batches->count() }}</span>

                </div>

                <div class="p-3 flex-1 overflow-y-auto space-y-3 custom-scrollbar">
                    @foreach($phase->batches as $batch)
                        @php
                            $borderColor = 'border-blue-400';
                            if ($batch->harvests_count > 0) {
                                $borderColor = 'border-green-500';
                            } elseif ($batch->type === 'grain') {
                                $borderColor = 'border-amber-500';
                            }
                        @endphp
                        <div class="relative bg-white rounded-lg shadow border-l-4 {{ $borderColor }} p-4">

                            <div class="absolute top-2 right-2 flex gap-2">
                                <button wire:click="openDiscardModal({{ $batch }})"
                                    class="text-gray-400 hover:text-red-500 transition-colors" title="Descartar unidades">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>

                            <div class="flex items-center gap-2 mb-1">
                                @if($batch->type === 'grain')
                                    <span class="text-amber-600 bg-amber-100 p-1 rounded" title="Lote de Grano">
                                        {{-- Heroicon: beaker --}}
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.04-.501.083-.75.128m.75-.128v5.714c0 .54-.2 1.054-.545 1.455l-3.32 3.332c-.088.089-.18.17-.276.242A6.778 6.778 0 003.5 17.252a1.498 1.498 0 001.077 2.479h14.846a1.498 1.498 0 001.077-2.48 6.778 6.778 0 00-1.359-3.235L15.65 10.87a2.25 2.25 0 01-.545-1.456V3.232m-3.957-.042A27.245 27.245 0 009.75 3.104" />
                                        </svg>
                                    </span>
                                @else
                                    <span class="text-blue-600 bg-blue-100 p-1 rounded" title="Lote de Sustrato">
                                        {{-- Heroicon: archive-box --}}
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3.25h3m-3 3.75h3m-12-6.75h12.75c.414 0 .75.336.75.75v3.75c0 .414-.336.75-.75.75H3.75c-.414 0-.75-.336-.75-.75V8.25c0-.414.336-.75.75-.75z" />
                                        </svg>
                                    </span>
                                @endif
                                <span class="text-xs font-mono text-gray-500 block">{{ $batch->code }}</span>
                            </div>

                            <h4 class="font-semibold text-gray-800">
                                {{ $batch->strain->name ?? '⚠️ Sustrato en Preparación' }}
                            </h4>
                            @if($batch->harvests_count > 0 || $batch->harvests()->exists())
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 border border-green-200 mt-1">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z" />
                                    </svg>
                                    Produciendo ({{ $batch->harvests()->count() }})
                                </span>
                            @endif

                            @if($batch->origin_code)
                                <span
                                    class="bg-gray-100 text-gray-600 text-[10px] px-1.5 py-0.5 rounded border border-gray-200 ml-1"
                                    title="Origen genético">
                                    <i class="fas fa-dna mr-0.5 text-xs"></i> {{ $batch->origin_code }}
                                </span>
                            @endif

                            <div class="mt-3 text-xs text-gray-600 flex items-center justify-between">
                                <span>
                                    <i class="far fa-calendar-alt mr-1"></i>
                                    {{ round($batch->days_in_current_phase) }} días
                                </span>
                                @if($batch->type === 'grain')
                                    <span class="text-amber-600 font-bold text-[10px] uppercase tracking-wide">GRANO</span>
                                @endif
                            </div>

                            <div class="mt-2 text-xs text-gray-500 border-t border-gray-100 pt-2 grid grid-cols-2 gap-2">
                                <div class="flex items-center" title="Cantidad de unidades">
                                    <span class="font-semibold text-gray-700 mr-1">{{ $batch->quantity }}</span>
                                    <span>unidades</span>
                                </div>
                                <div class="flex items-center text-right justify-end" title="Peso por unidad">
                                    <span class="font-semibold text-gray-700 mr-1">{{ $batch->bag_weight }}</span>
                                    <span>kg</span>
                                </div>
                            </div>

                            @if($phase->slug === 'fruiting')
                                <button wire:click="openTransitionModal({{ $batch->id }})"
                                    class="mt-2 text-green-600 hover:underline text-xs font-bold uppercase w-full text-left">
                                    Cosechar / Finalizar
                                </button>
                            @else
                                <button wire:click="openTransitionModal({{ $batch->id }})"
                                    class="mt-2 text-blue-600 hover:underline text-xs font-bold uppercase w-full text-left">
                                    Avanzar →
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    @include('livewire.kanban-modals')
</div>