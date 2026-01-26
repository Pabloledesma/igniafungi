<?php

namespace App\Filament\Resources\Batches\Tables;

use App\Models\Batch;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;
use App\Filament\Resources\Batches\BatchResource;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select; // Ensure this is imported if used as Select::make
use Filament\Forms\Get;

class BatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([

                TextColumn::make('strain.name')
                    ->label('Genética')
                    ->searchable(),
                TextColumn::make('code')
                    ->label('Código')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('inoculation_date')
                    ->label('Fecha Inoculación')
                    ->date()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('current_phase.name')
                    ->label('Fase Actual')
                    ->badge()
                    ->color('info')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo de Lote')
                    ->options([
                        'grain' => 'Grano',
                        'bulk' => 'Sustrato',
                    ]),
                SelectFilter::make('strain')
                    ->relationship('strain', 'name')
                    ->label('Filtrar por Genética'),
            ])
            ->recordActions([
                // Botón Editar (El lápiz estándar)
                EditAction::make(),

                // Botón QR (El nuevo)
                Action::make('pdf')
                    ->label('QR')
                    ->icon('heroicon-o-qr-code')
                    ->color('info')
                    ->action(function (Batch $record) {
                        // Generar ruta para editar este lote específico
                        $url = BatchResource::getUrl('edit', ['record' => $record]);

                        // Descargar PDF
                        return response()->streamDownload(function () use ($record, $url) {
                            echo Pdf::loadView('pdf.qr-label', [
                                'batch' => $record,
                                'url' => $url,
                            ])->output();
                        }, "{$record->code}.pdf");
                    }),

                Action::make('expand_g2g')
                    ->label('Expandir (G2G)')
                    ->icon('heroicon-o-arrows-pointing-out')
                    ->color('info')
                    ->visible(fn(Batch $record) => $record->type === 'grain' && $record->quantity > 0 && $record->strain_id)
                    ->form([
                        TextInput::make('parent_quantity')
                            ->label('¿Cuántos frascos MADRE vas a usar?')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(1)
                            ->maxValue(fn(Batch $record) => $record->quantity),

                        TextInput::make('child_quantity')
                            ->label('¿Cuántos NUEVOS frascos/bolsas creaste?')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText('Cantidad total de nuevas unidades inoculadas'),
                    ])
                    ->before(function (Batch $record, \Filament\Actions\Action $action) {
                        if ($record->inoculation_date && now()->diffInDays($record->inoculation_date) < 15) {
                            Notification::make()
                                ->title('Tiempo de incubación insuficiente')
                                ->body('El grano debe tener al menos 15 días de incubación para ser expandido.')
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    })
                    ->action(function (Batch $record, array $data) {
                        $parentQty = (int) $data['parent_quantity'];
                        $childQty = (int) $data['child_quantity'];

                        DB::transaction(function () use ($record, $parentQty, $childQty) {
                            // 1. Decrementar Inventario Padre
                            $record->decrement('quantity', $parentQty);

                            // Si llegamos a 0, cerramos el lote
                            if ($record->fresh()->quantity <= 0) {
                                $record->update(['status' => 'seeded']);
                                $currentPhase = $record->current_phase;
                                if ($currentPhase) {
                                    $record->phases()->updateExistingPivot($currentPhase->id, [
                                        'finished_at' => now(),
                                        'notes' => 'Lote consumido totalmente en expansión G2G.'
                                    ]);
                                }
                            }

                            // 2. Crear Lote Hijo
                            $newBatch = $record->replicate();
                            $newBatch->parent_batch_id = $record->id;
                            $newBatch->type = 'grain'; // Mismo tipo (G2G)
                            $newBatch->status = 'incubation'; // Vuelve a empezar
                            $newBatch->quantity = $childQty;
                            $newBatch->inoculation_date = now();
                            $newBatch->user_id = auth()->id();
                            $newBatch->code = $record->code . '-G-' . rand(10, 99);

                            // Limpieza de datos heredados que no aplican
                            $newBatch->contaminated_quantity = 0;
                            $newBatch->observations = "Expansión G2G desde {$record->code}";

                            $newBatch->save();

                            // Transición explícita a fase de Incubación
                            $incubationPhase = \App\Models\Phase::where('slug', 'incubation')->first();
                            if ($incubationPhase) {
                                $newBatch->transitionTo($incubationPhase, "Expansión G2G iniciada desde {$record->code}");
                            }
                        });

                        Notification::make()
                            ->title('Expansión G2G exitosa')
                            ->body("Se crearon {$childQty} nuevas unidades a partir de {$parentQty} madres.")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Expansión Grano a Grano')
                    ->modalDescription('Usa unidades de este lote para inocular nuevo grano estéril.'),

                Action::make('spawn_substrate')
                    ->label('Sembrar en Sustrato')
                    ->icon('heroicon-o-beaker')
                    ->color('warning')
                    ->visible(fn(Batch $record) => $record->type === 'grain' && $record->status === 'active' && $record->quantity > 0 && $record->strain_id)
                    ->form([
                        Select::make('target_batch_id')
                            ->label('Seleccionar Lote a Inocular')
                            ->options(function () {
                                return Batch::query()
                                    ->where('type', 'bulk')
                                    ->whereNull('strain_id')
                                    ->get()
                                    ->mapWithKeys(function ($batch) {
                                        return [$batch->id => "{$batch->code} ({$batch->quantity} un. / {$batch->bag_weight}kg)"];
                                    });
                            })
                            ->required()
                            ->searchable()
                            ->preload(),

                    ])
                    ->before(function (Batch $record, \Filament\Actions\Action $action) {
                        // Aseguramos que la fecha sea un objeto Carbon para poder comparar
                        $fechaInoculacion = \Carbon\Carbon::parse($record->inoculation_date);

                        // Calculamos la diferencia en días absolutos hasta el inicio de hoy
                        $diasTranscurridos = $fechaInoculacion->diffInDays(now()->startOfDay());

                        if ($diasTranscurridos < 15) {
                            Notification::make()
                                ->title('Tiempo de incubación insuficiente')
                                ->body("El lote solo lleva {$diasTranscurridos} días. Faltan " . (15 - $diasTranscurridos) . " días para poder sembrar.")
                                ->danger()
                                ->send();

                            $action->halt(); // Detiene la ejecución
                        }
                    })
                    ->action(function (Batch $record, array $data) {
                        // Variables comunes
                        $requiredGrain = 0;
                        $targetBatch = null;
                        $targetBatch = Batch::find($data['target_batch_id']);

                        $recipe = $targetBatch->recipe;
                        if (!$recipe) {
                            $recipe = \App\Models\Recipe::with('supplies')->find($targetBatch->recipe_id);
                        }

                        $inoculumSupply = $recipe->supplies->first(function ($supply) {
                            $name = strtolower($supply->name);
                            return str_contains($name, 'semilla') || str_contains($name, 'inoculo') || str_contains($name, 'grano') || str_contains($name, 'spawn');
                        });

                        if ($inoculumSupply) {
                            $totalWeight = $targetBatch->quantity * $targetBatch->bag_weight;
                            if ($inoculumSupply->pivot->calculation_mode === 'percentage') {
                                $requiredGrain = ($totalWeight * $inoculumSupply->pivot->value) / 100;
                            } else {
                                $requiredGrain = $inoculumSupply->pivot->value * $targetBatch->quantity;
                            }
                        } else {
                            Notification::make()->title('Precaución')->body('No se calculó consumo de grano por falta de insumo semilla en receta. Se asumirá 0.')->warning()->send();
                        }


                        // VALIDAR STOCK DE GRANO
                        $availableMass = $record->quantity * $record->bag_weight;
                        if ($availableMass < $requiredGrain) {
                            Notification::make()
                                ->title('No hay suficiente grano')
                                ->body("Se requieren {$requiredGrain}kg de inóculo, pero el lote tiene {$availableMass}kg.")
                                ->danger()
                                ->send();
                            return;
                        }

                        // Calcular unidades de grano a descontar
                        // Asumimos que si requiredGrain = 0 (caso raro), no descontamos.
                        $unitsToConsume = ($record->bag_weight > 0) ? ceil($requiredGrain / $record->bag_weight) : 0;

                        if ($record->quantity < $unitsToConsume) {
                            Notification::make()->title('Error de Unidades')->body("Se requieren $unitsToConsume unidades de grano, pero solo hay {$record->quantity}.")->danger()->send();
                            return;
                        }

                        DB::transaction(function () use ($record, $targetBatch, $unitsToConsume, $data) {
                            // 1. Descontar grano
                            if ($unitsToConsume > 0) {
                                $record->decrement('quantity', $unitsToConsume);

                                // Si llegamos a 0, cerramos el lote
                                if ($record->fresh()->quantity <= 0) {
                                    $record->update(['status' => 'seeded']); // o completed/consumido
                                    // Cerrar fase actual
                                    $currentPhase = $record->current_phase;
                                    if ($currentPhase) {
                                        $record->phases()->updateExistingPivot($currentPhase->id, [
                                            'finished_at' => now(),
                                            'notes' => 'Lote consumido totalmente en siembra.'
                                        ]);
                                    }
                                }
                            }

                            // 2. Configurar Lote Destino (común para Create y Existing)
                            $targetBatch->parent_batch_id = $record->id;
                            $targetBatch->strain_id = $record->strain_id;
                            $targetBatch->inoculation_date = now();
                            $targetBatch->user_id = auth()->id();

                            $targetBatch->save();
                            // Transición de fase manual para Existing
                            $incubationPhase = \App\Models\Phase::where('slug', 'incubation')->first();
                            if ($incubationPhase) {
                                $targetBatch->transitionTo($incubationPhase, "Inoculado con lote {$record->code}");
                            }
                        });

                        Notification::make()->title('Siembra registrada exitosamente.')->success()->send();
                    }),
                Action::make('advance_phase')
                    ->label('Avanzar Fase')
                    ->icon('heroicon-m-forward')
                    ->color('success')
                    ->form([
                        Select::make('phase_id')
                            ->label('Siguiente Fase')
                            ->options(function (Batch $record) {
                                $currentOrder = $record->current_phase?->order ?? 0;
                                return \App\Models\Phase::where('order', '>', $currentOrder)
                                    ->orderBy('order')
                                    ->limit(1)
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->live() // Hacerlo reactivo para mostrar/ocultar Cepa
                            ->default(function (Batch $record) {
                                $currentOrder = $record->current_phase?->order ?? 0;
                                return \App\Models\Phase::where('order', '>', $currentOrder)
                                    ->orderBy('order')
                                    ->limit(1)
                                    ->value('id');
                            }),

                        Select::make('strain_id')
                            ->label('Cepa / Genética')
                            ->relationship('strain', 'name')
                            ->searchable()
                            ->preload()
                            ->required(function ($get) {
                                $phaseId = $get('phase_id');
                                if (!$phaseId)
                                    return false;

                                $phase = \App\Models\Phase::find($phaseId);
                                return $phase && $phase->slug === 'inoculation';
                            })
                            ->visible(function ($get) {
                                $phaseId = $get('phase_id');
                                if (!$phaseId)
                                    return false;

                                $phase = \App\Models\Phase::find($phaseId);
                                return $phase && $phase->slug === 'inoculation';
                            })
                            ->default(fn(Batch $record) => $record->strain_id)
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                // Reset source batch if strain changes
                                $set('source_batch_id', null);
                            }),

                        Select::make('source_batch_id')
                            ->label('Lote de Semilla (Origen)')
                            ->options(function ($get) {
                                $strainId = $get('strain_id');
                                if (!$strainId)
                                    return [];

                                return Batch::where('type', 'grain')
                                    ->where('strain_id', $strainId)
                                    ->where('quantity', '>', 0)
                                    ->where('status', '!=', 'contaminated')
                                    ->where('inoculation_date', '<=', now()->subDays(15))
                                    ->get()
                                    ->mapWithKeys(function ($batch) {
                                        return [$batch->id => "{$batch->code} ({$batch->quantity} un. / {$batch->bag_weight}kg)"];
                                    });
                            })
                            ->required(function ($get) {
                                $phaseId = $get('phase_id');
                                if (!$phaseId)
                                    return false;
                                $phase = \App\Models\Phase::find($phaseId);
                                return $phase && $phase->slug === 'inoculation';
                            })
                            ->visible(function ($get) {
                                $phaseId = $get('phase_id');
                                if (!$phaseId)
                                    return false;
                                $phase = \App\Models\Phase::find($phaseId);
                                return $phase && $phase->slug === 'inoculation';
                            })
                            ->validationMessages([
                                'required' => 'Debes seleccionar un lote de semilla para inocular.',
                            ]),

                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label('Notas de transición'),
                    ])
                    ->visible(fn(Batch $record) => $record->current_phase?->slug !== 'fruiting')
                    ->action(function (Batch $record, array $data) {
                        $phase = \App\Models\Phase::findOrFail($data['phase_id']);

                        // Guardar Cepa si se envió
                        if (!empty($data['strain_id'])) {
                            $record->strain_id = $data['strain_id'];
                            $record->save();
                        }

                        $record->transitionTo($phase, $data['notes'] ?? null);

                        Notification::make()
                            ->title('Fase actualizada correctamente')
                            ->success()
                            ->send();
                    }),

                Action::make('harvest')
                    ->label('Cosechar')
                    ->icon('heroicon-o-shopping-bag')
                    ->color('warning')
                    ->visible(fn(Batch $record) => $record->current_phase?->slug === 'fruiting')
                    ->form([
                        TextInput::make('weight')
                            ->label('Peso Fresco (Kg)')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->maxValue(5)
                            ->validationMessages([
                                'max' => 'El valor es muy alto. ¿Estás ingresando gramos? Usa Kilos (ej: 0.5 para 500g).',
                            ])
                            ->step(0.01),
                        \Filament\Forms\Components\DatePicker::make('harvest_date')
                            ->label('Fecha de Cosecha')
                            ->default(now())
                            ->required(),
                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label('Notas (Opcional)'),
                    ])
                    ->action(function (Batch $record, array $data) {
                        $record->harvests()->create([
                            'weight' => $data['weight'],
                            'harvest_date' => $data['harvest_date'],
                            'notes' => $data['notes'],
                            'phase_id' => $record->current_phase?->id,
                            'user_id' => auth()->id(),
                        ]);

                        Notification::make()
                            ->title('Cosecha registrada')
                            ->body("Se registraron {$data['weight']} kg exitosamente.")
                            ->success()
                            ->send();
                    }),

                Action::make('discard')
                    ->label('Descartar')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Descartar Unidades')
                    ->modalDescription('Registra unidades dañadas o contaminadas. Si descartas todo, el lote cambiará a estado contaminado.')
                    ->form([
                        Select::make('reason')
                            ->label('Razón del descarte')
                            ->options([
                                'Contaminación' => 'Contaminación',
                                'Exceso de Temperatura' => 'Exceso de Temperatura',
                                'Falta de Humedad' => 'Falta de Humedad',
                                'Plagas' => 'Plagas (Mosquitos/Ácaros)',
                                'Error de Manejo' => 'Error de Manejo',
                                'Legado / Histórico' => 'Legado / Histórico',
                            ])
                            ->required()
                            ->searchable(),
                        \Filament\Forms\Components\Textarea::make('details')
                            ->label('Detalles adicionales'),
                        TextInput::make('quantity')
                            ->label('Cantidad a descartar')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->minValue(1)
                            ->validationMessages([
                                'min' => 'Debes descartar al menos 1 unidad.',
                            ])
                            ->maxValue(fn(Batch $record) => $record->quantity),
                    ])
                    ->action(function (Batch $record, array $data) {
                        $record->discard($data['reason'], $data['quantity'], $data['details'] ?? null);

                        Notification::make()
                            ->title('Lote descartado')
                            ->body('El lote ha sido marcado como contaminado.')
                            ->danger()
                            ->send();
                    }),
                // Botón Borrar (Opcional, pero útil en desarrollo)
                // DeleteAction removed as per requirements
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DeleteBulkAction removed
                ]),
            ]);
    }
}
