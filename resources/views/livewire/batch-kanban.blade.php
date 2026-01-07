<div class="flex flex-col h-[calc(100vh-64px)] overflow-hidden bg-gray-100">
    <div class="mb-6 flex items-center space-x-4 bg-white p-4 rounded-lg shadow-sm">
            <span class="text-sm font-bold text-gray-500 uppercase tracking-wider">Filtrar por:</span>
            
            <div class="flex bg-gray-100 p-1 rounded-md">
                <button wire:click="$set('batchType', '')" 
                    class="px-4 py-2 text-xs font-bold rounded-md transition-colors {{ $batchType === '' ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
                    TODOS
                </button>
                <button wire:click="$set('batchType', 'grain')" 
                    class="px-4 py-2 text-xs font-bold rounded-md transition-colors {{ $batchType === 'grain' ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
                    SEMILLA (GRAIN)
                </button>
                <button wire:click="$set('batchType', 'bulk')" 
                    class="px-4 py-2 text-xs font-bold rounded-md transition-colors {{ $batchType === 'bulk' ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
                    SUSTRATO (BULK)
                </button>
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
                        <div class="relative bg-white p-4 rounded-md shadow border-l-4 border-green-500">
                            <div class="absolute top-2 right-2 flex gap-2">
                                <button wire:click="openDiscardModal({{ $batch }})" class="text-gray-400 hover:text-red-500 transition-colors" title="Descartar unidades">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                            <span class="text-xs font-mono text-gray-500 pr-8 block">{{ $batch->code }}</span>
                            <h4 class="font-semibold text-gray-800">
                                {{ $batch->strain->name ?? '⚠️ Sustrato en Preparación' }}
                            </h4>
                            
                            <div class="mt-3 text-xs text-gray-600">
                                <i class="far fa-calendar-alt mr-1"></i>
                                {{ round($batch->days_in_current_phase) }} días aquí
                            </div>

                            <div class="mt-4 pt-3 border-t flex justify-end">
                                <button wire:click="openTransitionModal({{ $batch->id }})" 
                                        class="text-blue-600 hover:underline text-xs font-bold uppercase">
                                    Avanzar →
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    @include('livewire.kanban-modals')
</div>