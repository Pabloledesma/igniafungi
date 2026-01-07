<div class="flex flex-col h-[calc(100vh-64px)] overflow-hidden bg-gray-100">
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
                            <span class="text-xs font-mono text-gray-500">{{ $batch->code }}</span>
                            <h4 class="font-semibold text-gray-800">{{ $batch->strain->name }}</h4>
                            
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