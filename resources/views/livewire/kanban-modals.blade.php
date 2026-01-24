@if($showModal)
    <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/80" wire:click="close"></div>

        <div class="relative bg-white rounded-xl shadow-2xl max-w-lg w-full p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">
                {{ $isLastPhase ? 'Registrar Cosecha' : 'Avanzar a Siguiente Fase' }}
            </h3>

            @if($showHarvestFields)
                <div class="mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded text-[10px] text-yellow-700">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    ¿Primordios detectados? Puedes cosechar ahora.
                </div>
                <button wire:click="openTransitionModal({{ $batch->id }})"
                    class="mt-1 text-orange-600 hover:underline text-xs font-bold uppercase">
                    Cosecha Temprana / Mover
                </button>
            @endif

            <div class="space-y-4">
                @if($isLastPhase)
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Peso obtenido (kg)</label>
                        <input type="number" step="0.001" wire:model="harvestWeight" placeholder="0.000"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-green-500 focus:border-green-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fecha de Cosecha</label>
                        <input type="date" wire:model="harvestDate"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-green-500">
                    </div>

                    <div class="flex items-center p-3 bg-yellow-50 rounded-lg border border-yellow-100">
                        <input type="checkbox" wire:model="shouldFinishBatch" id="finishCheck"
                            class="h-4 w-4 text-green-600 border-gray-300 rounded focus:ring-green-500">
                        <label for="finishCheck" class="ml-2 block text-sm text-yellow-800 font-semibold">
                            ¿Finalizar bloque definitivamente? (No habrá más oleadas)
                        </label>
                    </div>
                @endif

                @if(count($strains) > 0)
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Seleccionar Genética (Cepa)</label>
                        <select wire:model.live="strainId"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">-- Elige una Cepa --</option>
                            @foreach($strains as $strain)
                                <option value="{{ $strain->id }}">{{ $strain->name }}</option>
                            @endforeach
                        </select>
                        @error('strainId') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    {{-- Selector de Inoculo (Seed Batch) --}}
                    @if($strainId)
                        @if($availableInoculumBatches && count($availableInoculumBatches) > 0)
                            <div class="mt-4 border-t pt-4 border-gray-100">
                                <label for="inoculumBatchId" class="block text-sm font-medium text-gray-700">Lote de Semilla
                                    (Origen)</label>
                                <select wire:model.live="inoculumBatchId" id="inoculumBatchId"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">-- Seleccionar Semilla --</option>
                                    @foreach($availableInoculumBatches as $seedBatch)
                                        <option value="{{ $seedBatch['id'] }}">
                                            {{-- Formatted Label with Date & Qty --}}
                                            {{ $seedBatch['formatted_label'] ?? ($seedBatch['code'] . ' - ' . \Carbon\Carbon::parse($seedBatch['inoculation_date'])->format('d/m') . ' - ' . $seedBatch['quantity'] . ' u') }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="text-[10px] text-gray-500 mt-1">FIFO: Se muestran primero los lotes más antiguos.</p>
                                @error('inoculumBatchId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div class="mt-4">
                                <label for="inoculumRatio" class="block text-sm font-medium text-gray-700">% de Inoculación</label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <input type="number" wire:model.live="inoculumRatio" id="inoculumRatio"
                                        class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-3 pr-12 rounded-md border-gray-300 shadow-sm"
                                        placeholder="10">
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">%</span>
                                    </div>
                                </div>
                                @error('inoculumRatio') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        @else
                            {{-- Warning for Empty List --}}
                            <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-md flex items-start">
                                <i class="fas fa-exclamation-circle text-red-600 mt-0.5 mr-2"></i>
                                <span class="text-sm text-red-700 font-semibold">
                                    No hay semilla disponible para esa cepa (mínimo 20 días de incubación).
                                </span>
                            </div>
                        @endif
                    @endif
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
                    <button wire:click="harvestBatch"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg font-bold hover:bg-green-700">
                        Registrar Cosecha
                    </button>
                @else
                    {{-- Disable logic: If Next Phase is Inoculation, Strain is Selected, BUT no Seed available -> Disable --}}
                    @php
                        $disableAdvance = false;
                        if ($nextPhaseId && \App\Models\Phase::find($nextPhaseId)?->slug === 'inoculation') {
                            if ($strainId && count($availableInoculumBatches) === 0) {
                                $disableAdvance = true;
                            }
                        }
                    @endphp
                    <div class="flex flex-col items-end">
                        <button wire:click="confirmTransition" @if($disableAdvance) disabled @endif
                            class="px-4 py-2 text-white rounded-lg font-bold transition-colors {{ $disableAdvance ? 'bg-gray-400 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700' }}">
                            Confirmar Avance
                        </button>
                        @if($disableAdvance)
                            <p class="text-xs text-red-500 mt-1">Acción bloqueada: falta semilla.</p>
                        @endif
                    </div>
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
                        <p class="text-[10px] text-gray-500 mt-1">Total del lote: {{ $selectedBatch?->quantity }} unidades
                        </p>
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
                    <button wire:click="$set('showLossModal', false)"
                        class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg">Cancelar</button>
                    <button wire:click="processLoss"
                        class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg font-bold">Registrar</button>
                </div>
            </div>
        </div>
    </div>
@endif
@if($showDiscardModal)
    <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/80" wire:click="closeDiscard"></div>

        <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
            <h3 class="text-xl font-bold text-red-600 mb-4 flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Descartar / Sacar Unidades
            </h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Cantidad a sacar manualmente</label>
                    <input type="number" step="0.01" wire:model="discardQuantity" {{ $isTotalDiscard ? 'disabled' : '' }}
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-red-500 focus:border-red-500 {{ $isTotalDiscard ? 'bg-gray-100' : 'bg-white' }}">
                </div>

                <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="isTotalDiscard"
                            class="w-5 h-5 text-red-600 border-gray-300 rounded focus:ring-red-500">
                        <span class="ml-3 text-sm font-bold text-red-700">DESCARTAR TODO EL LOTE</span>
                    </label>

                    @if($isTotalDiscard)
                        <div class="mt-2 flex items-start gap-2 text-red-600 animate-pulse">
                            <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            <p class="text-xs font-bold uppercase italic">
                                ⚠️ ¡ADVERTENCIA! Esta acción sacará el bloque completo de producción. ¿Estás seguro?
                            </p>
                        </div>
                    @endif
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Motivo</label>
                    <select wire:model="discardReason"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-red-500 focus:border-red-500 bg-white">
                        <option value="Contaminación">Contaminación</option>
                        <option value="Agotado">Bloque Agotado (Fin de ciclo)</option>
                        <option value="Dañado">Daño Físico</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Observaciones</label>
                    <textarea wire:model="discardNotes" rows="2"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-red-500 focus:border-red-500 bg-white"
                        placeholder="Detalles..."></textarea>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button wire:click="closeDiscard" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg">Cancelar</button>
                <button wire:click="processDiscard"
                    class="px-4 py-2 text-white rounded-lg font-bold transition-all
                                        {{ $isTotalDiscard ? 'bg-black hover:bg-red-700 animate-bounce' : 'bg-red-600 hover:bg-red-700' }}">
                    {{ $isTotalDiscard ? '¡SÍ, DESCARTAR TODO!' : 'Confirmar Descarte' }}
                </button>
            </div>
        </div>
    </div>
@endif