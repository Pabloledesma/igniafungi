<div class="flex flex-col h-[calc(100vh-64px)] overflow-hidden bg-gray-100">
    <div class="mb-6 flex items-center space-x-4 bg-white p-4 rounded-lg shadow-sm">
        {{-- Buscador --}}
        <div class="relative">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar lote..." 
                class="pl-3 pr-4 py-2 border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        {{-- Filtro Cepa --}}
        <div>
            <select wire:model.live="selectedStrain" class="border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500 py-2 pl-3 pr-8">
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
                    <span class="bg-gray-500 text-white text-xs px-2 py-1 rounded-full">{{ $phase->batches->count() }}</span>
                   
                </div>

                <div class="p-3 flex-1 overflow-y-auto space-y-3 custom-scrollbar">
                    @foreach($phase->batches as $batch)
                        <div class="relative bg-white rounded-lg shadow border-l-4 {{ $batch->harvests_count > 0 ? 'border-green-500' : 'border-blue-400' }} p-4">
                           
                            <div class="absolute top-2 right-2 flex gap-2">
                                <button wire:click="openDiscardModal({{ $batch }})" class="text-gray-400 hover:text-red-500 transition-colors" title="Descartar unidades">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                            <span class="text-xs font-mono text-gray-500 pr-8 block">{{ $batch->code }}</span>
                            <h4 class="font-semibold text-gray-800">
                                {{ $batch->strain->name ?? '⚠️ Sustrato en Preparación' }}
                            </h4>
                            @if($batch->harvests_count > 0 || $batch->harvests()->exists())
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z" />
                                    </svg>
                                    Produciendo ({{ $batch->harvests()->count() }})
                                </span>
                            @endif
                            
                            <div class="mt-3 text-xs text-gray-600">
                                <i class="far fa-calendar-alt mr-1"></i>
                                {{ round($batch->days_in_current_phase) }} días aquí
                            </div>

                            @if($phase->slug === 'fruiting')
                                <button wire:click="openTransitionModal({{ $batch->id }})" 
                                        class="text-green-600 hover:underline text-xs font-bold uppercase">
                                    Cosechar / Finalizar
                                </button>
                            @else
                                <button wire:click="openTransitionModal({{ $batch->id }})" 
                                        class="text-blue-600 hover:underline text-xs font-bold uppercase">
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