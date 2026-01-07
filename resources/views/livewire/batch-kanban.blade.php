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
                            <button wire:click="openTransitionModal({{ $batch->id }})" 
                                    class="{{ $phase->slug === 'harvest' ? 'text-green-600' : 'text-blue-600' }} hover:underline text-xs font-bold uppercase">
                                {{ $phase->slug === 'harvest' ? 'Cosechar/Finalizar' : 'Avanzar →' }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    @if($showModal)
    <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/80" wire:click="close"></div>

        <div class="relative bg-white rounded-xl shadow-2xl max-w-lg w-full p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">
                {{ $isLastPhase ? 'Registrar Cosecha' : 'Avanzar a Siguiente Fase' }}
            </h3>

            <div class="space-y-4">
                @if($isLastPhase)
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Peso obtenido (kg)</label>
                        <input type="number" step="0.01" wire:model="harvestWeight" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-green-500 focus:border-green-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fecha de Cosecha</label>
                        <input type="date" wire:model="harvestDate" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-green-500">
                    </div>

                    <div class="flex items-center p-3 bg-yellow-50 rounded-lg border border-yellow-100">
                        <input type="checkbox" wire:model="shouldFinishBatch" id="finishCheck" 
                            class="h-4 w-4 text-green-600 border-gray-300 rounded focus:ring-green-500">
                        <label for="finishCheck" class="ml-2 block text-sm text-yellow-800 font-semibold">
                            ¿Finalizar bloque definitivamente? (No habrá más oleadas)
                        </label>
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700">Observaciones</label>
                    <textarea wire:model="notes" rows="3" 
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Ej: Primera oleada con sombreros grandes..."></textarea>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button wire:click="close" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg">Cancelar</button>
                
                @if($isLastPhase)
                    <button wire:click="harvestBatch" class="px-4 py-2 bg-green-600 text-white rounded-lg font-bold hover:bg-green-700">
                        Registrar Cosecha
                    </button>
                @else
                    <button wire:click="confirmTransition" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-bold hover:bg-blue-700">
                        Confirmar Avance
                    </button>
                @endif
            </div>
        </div>
    </div>
    @endif
    @if($showLossModal)
    <div class="fixed inset-0 z-[9999] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-slate-900/90 backdrop-blur-sm" wire:click="$set('showLossModal', false)"></div>

            <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
                <h3 class="text-xl font-bold text-red-600 mb-4 flex items-center">
                    <i class="fas fa-biohazard mr-2"></i> Reportar Incidencia
                </h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Cantidad afectada</label>
                        <input type="number" wire:model="lossQuantity" class="w-full rounded-lg border-gray-300">
                        <p class="text-[10px] text-gray-500 mt-1">Total del lote: {{ $selectedBatch?->quantity }} unidades</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Motivo</label>
                        <select wire:model="lossReason" class="w-full rounded-lg border-gray-300">
                            <option value="">Seleccione...</option>
                            <option value="Trichoderma">Trichoderma (Moho Verde)</option>
                            <option value="Bacteriosis">Bacteriosis</option>
                            <option value="Mal Manejo">Error de Manipulación</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>

                    <div class="flex items-center p-3 bg-red-50 rounded-lg">
                        <input type="checkbox" wire:model="isTotalLoss" id="totalLoss" class="rounded text-red-600">
                        <label for="totalLoss" class="ml-2 text-sm text-red-800 font-bold">¿Descarte total del lote?</label>
                    </div>
                </div>

                <div class="mt-6 flex gap-3">
                    <button wire:click="$set('showLossModal', false)" class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg">Cancelar</button>
                    <button wire:click="processLoss" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg font-bold">Registrar</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>