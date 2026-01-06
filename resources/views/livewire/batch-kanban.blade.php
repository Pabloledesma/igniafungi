<div class="flex overflow-x-auto p-6 gap-4 bg-gray-100 min-h-screen">
    @foreach($phases as $phase)
        <div class="flex-shrink-0 w-80 bg-gray-200 rounded-lg shadow-sm flex flex-col">
            <div class="p-4 border-b border-gray-300 flex justify-between items-center bg-white rounded-t-lg">
                <h3 class="font-bold text-gray-700 uppercase text-sm tracking-wider">{{ $phase->name }}</h3>
                <span class="bg-gray-500 text-white text-xs px-2 py-1 rounded-full">{{ $phase->batches->count() }}</span>
            </div>

            <div class="p-3 flex-1 overflow-y-auto space-y-3">
                @foreach($phase->batches as $batch)
                    <div class="bg-white p-4 rounded-md shadow hover:shadow-md transition-shadow cursor-pointer border-l-4 border-green-500">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-mono text-gray-500">{{ $batch->code }}</span>
                            <button class="text-gray-400 hover:text-red-500">
                                <i class="fas fa-exclamation-triangle"></i>
                            </button>
                        </div>
                        
                        <h4 class="font-semibold text-gray-800">{{ $batch->strain->name }}</h4>
                        
                        <div class="mt-3 flex items-center justify-between text-xs text-gray-600">
                            <div class="flex items-center">
                                <i class="far fa-calendar-alt mr-1"></i>
                                {{ $batch->days_in_current_phase }} días aquí
                            </div>
                            @if($batch->losses->sum('quantity') > 0)
                                <span class="text-red-600 font-bold">-{{ $batch->losses->sum('quantity') }} unidades</span>
                            @endif
                        </div>

                        <div class="mt-4 pt-3 border-t flex justify-end">
                            <button wire:click="openTransitionModal({{ $batch->id }})" class="text-blue-600 hover:text-blue-800 text-xs font-bold uppercase">
                                Avanzar →
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

   @if($showModal)
        <div class="fixed inset-0 z-[9999] overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen p-4 text-center">
                
                <div class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm transition-opacity" wire:click="close"></div>

                <div class="relative bg-white rounded-xl shadow-2xl transform transition-all sm:max-w-lg sm:w-full overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-800">Avanzar Lote</h3>
                        <button wire:click="close" class="text-gray-400 hover:text-gray-600">&times;</button>
                    </div>

                    <div class="p-6 text-left">
                        <p class="text-sm text-gray-600 mb-4">Vas a mover el lote a la siguiente etapa de cultivo.</p>
                        
                        <label class="block text-sm font-medium text-gray-700">Notas de Observación</label>
                        <textarea wire:model="notes" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm" 
                            rows="4" 
                            placeholder="Describe el estado del micelio..."></textarea>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3">
                        <button wire:click="close" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button wire:click="confirmTransition" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 shadow-md">
                            Confirmar Cambio
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>