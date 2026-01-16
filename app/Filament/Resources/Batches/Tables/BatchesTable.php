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
                TextColumn::make('weigth_dry')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('inoculation_date')
                    ->label('Fecha Inoculación')
                    ->date()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('production_cost')
                    ->money('COP')
                    ->label('Costo Prod.')
                    ->sortable(),
                TextColumn::make('bag_weight')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('grain_type')
                    ->searchable(),
                TextColumn::make('container_type')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('biological_efficiency')
                    ->label('Eficiencia %')
                    ->suffix('%')
                    ->numeric(2)
                    ->sortable()
                    ->badge()
                    ->color(fn(string $state): string => match (true) {
                        $state >= 100 => 'success', // Verde si rinde más del 100%
                        $state >= 70 => 'warning',  // Amarillo si es decente
                        default => 'danger',        // Rojo si estás perdiendo dinero
                    }),
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

                Action::make('sow_grain')
                    ->label('Sembrar')
                    ->icon('heroicon-m-beaker')
                    ->color('info')
                    // Solo visible para lotes de grano con existencias
                    ->visible(fn(Batch $record) => $record->type === 'grain' && $record->quantity > 0)
                    ->form([
                        TextInput::make('quantity_to_sow')
                            ->label('¿Cuántas unidades (frascos/bolsas) vas a sembrar?')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(fn(Batch $record) => $record->quantity),
                    ])
                    ->action(function (Batch $record, array $data) {
                        $quantity = (int) $data['quantity_to_sow'];
                        $now = now()->format('Y-m-d H:i');
                        $user_name = auth()->user()->name;

                        // Creamos el mensaje para la bitácora
                        $logMessage = "\n- [{$now}] {$user_name}: Se sembraron {$quantity} unidades. Quedan " . ($record->quantity - $quantity) . " disponibles.";

                        // Actualizamos el registro
                        $record->update([
                            'quantity' => $record->quantity - $quantity,
                            // Concatenamos el mensaje nuevo a lo que ya existía en la bitácora
                            'observations' => $record->observations . $logMessage
                        ]);
                        Notification::make()
                            ->title('Semilla descontada')
                            ->body($logMessage)
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),

                Action::make('move_to_fruiting')
                    ->label('Pasar a Fructificación')
                    ->icon('heroicon-m-arrow-right-circle') // Icono de flecha
                    ->color('success') // Verde
                    ->visible(fn(Batch $record) => $record->type === 'bulk' && $record->status === 'incubation' && $record->quantity > 0)
                    ->form([
                        TextInput::make('quantity_to_move')
                            ->label('¿Cuántas unidades pasan a Fructificación?')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(fn(Batch $record) => $record->quantity) // Por defecto sugiere mover todas
                            ->maxValue(fn(Batch $record) => $record->quantity), // No puedes mover más de las que hay
                    ])
                    ->action(function (Batch $record, array $data) {
                        $quantityToMove = (int) $data['quantity_to_move'];

                        // ESCENARIO 1: Se mueven TODAS las unidades
                        if ($quantityToMove === $record->quantity) {
                            $record->update([
                                'status' => 'fruiting',
                                // Opcional: Registrar fecha de fructificación si tienes el campo
                                // 'fruiting_date' => now(), 
                            ]);

                            Notification::make()->title('Lote completo pasado a Fructificación')->success()->send();
                            return;
                        }
                        // 2. Usamos replicate() para copiar TODOS los datos (Cepa, Tipo, Fechas, Responsable, etc.)
                        $newBatch = $record->replicate();

                        // 3. Sobreescribimos lo que cambia en el hijo
                        $newBatch->quantity = $quantityToMove;
                        $newBatch->status = 'fruiting';
                        $newBatch->parent_batch_id = $record->id; // Mantenemos la trazabilidad
                        $newBatch->contaminated_quantity = 0; // El nuevo lote nace sano (las contaminadas se quedaron atrás o ya se reportaron)
            
                        // 4. Generamos un nuevo Código para diferenciarlo
                        // Si el padre es "PINK-001", el hijo será "PINK-001-F1" (Fructificación 1)
                        // Usamos un uniqid corto o un contador para evitar duplicados
                        $newBatch->code = $record->code . '-F-' . rand(10, 99);

                        $newBatch->save();

                        Notification::make()
                            ->title('Lote dividido exitosamente')
                            ->body("Se creó un sub-lote con $quantityToMove unidades en Fructificación.")
                            ->success()
                            ->send();
                    }),

                Action::make('spawn_substrate')
                    ->label('Sembrar en Sustrato')
                    ->icon('heroicon-o-flask')
                    ->color('warning')
                    ->visible(fn(Batch $record) => $record->type === 'grain' && $record->status === 'active' && $record->quantity > 0)
                    ->form([
                        TextInput::make('bags_quantity')
                            ->label('Cantidad de Bolsas')
                            ->numeric()
                            ->required()
                            ->minValue(1),
                        TextInput::make('bag_weight')
                            ->label('Peso por Bolsa (kg)')
                            ->numeric()
                            ->step(0.01)
                            ->required(),
                        \Filament\Forms\Components\Select::make('recipe_id')
                            ->label('Receta de Sustrato')
                            ->options(\App\Models\Recipe::where('type', 'bulk')->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (Batch $record, array $data) {
                        $qty = (int) $data['bags_quantity'];
                        $weight = (float) $data['bag_weight'];
                        $recipeId = $data['recipe_id'];

                        $totalSubstrateWeight = $qty * $weight;

                        // Encontrar la tasa de inoculación de la receta
                        $recipe = \App\Models\Recipe::with('supplies')->find($recipeId);

                        // Heurística: Buscar insumo que sea semilla/inóculo
                        $inoculumSupply = $recipe->supplies->first(function ($supply) {
                            $name = strtolower($supply->name);
                            return str_contains($name, 'semilla') || str_contains($name, 'inoculo') || str_contains($name, 'grano') || str_contains($name, 'spawn');
                        });

                        if (!$inoculumSupply) {
                            Notification::make()
                                ->title('Error de Configuración')
                                ->body('La receta seleccionada no tiene un insumo de "Semilla" o "Inóculo" identificable.')
                                ->danger()
                                ->send();
                            return;
                        }

                        // Calcular grano requerido
                        $requiredGrain = 0;
                        if ($inoculumSupply->pivot->calculation_mode === 'percentage') {
                            $requiredGrain = ($totalSubstrateWeight * $inoculumSupply->pivot->value) / 100;
                        } else {
                            $requiredGrain = $inoculumSupply->pivot->value * $qty;
                        }

                        // Validar Stock
                        $availableMass = $record->quantity * $record->bag_weight;

                        if ($availableMass < $requiredGrain) {
                            Notification::make()
                                ->title('No hay suficiente grano')
                                ->body("Se requieren {$requiredGrain}kg de inóculo, pero el lote tiene {$availableMass}kg.")
                                ->danger()
                                ->send();
                            return;
                        }

                        DB::transaction(function () use ($record, $qty, $weight, $recipeId, $data) {
                            // 1. Crear Lote Hijo (Sustrato)
                            $childBatch = new Batch();
                            $childBatch->parent_batch_id = $record->id;
                            $childBatch->strain_id = $record->strain_id;
                            $childBatch->recipe_id = $recipeId;
                            $childBatch->user_id = auth()->id();
                            $childBatch->type = 'bulk';
                            $childBatch->status = 'incubation'; // Empieza en incubación
                            $childBatch->quantity = $qty;
                            $childBatch->bag_weight = $weight;
                            $childBatch->code = $record->code . '-S-' . rand(100, 999);
                            $childBatch->inoculation_date = now();
                            $childBatch->save();

                            // 2. Marcar Grano como Sembrado (Consumido) y cerrar fase
                            $record->update([
                                'status' => 'seeded',
                            ]);

                            // Cerrar fase actual para sacarlo del Kanban
                            $activePhase = $record->phases()->wherePivot('finished_at', null)->first();
                            if ($activePhase) {
                                $record->phases()->updateExistingPivot($activePhase->id, ['finished_at' => now()]);
                            }
                        });

                        Notification::make()->title('Siembra registrada y lote de grano cerrado.')->success()->send();
                    }),
                // Botón Borrar (Opcional, pero útil en desarrollo)
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
