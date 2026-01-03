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
use App\Filament\Resources\Batches\BatchResource;

class BatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                
                TextColumn::make('strain.name')
                    ->label('Genética')
                    ->searchable(),
                TextColumn::make('code')
                    ->label('Código')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('substrate_weight_dry')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('inoculation_date')
                    ->label('Fecha Inoculación')
                    ->date()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->numeric()
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
                    ->color(fn (string $state): string => match (true) {
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
                //
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
                        }, "Lote-{$record->code}.pdf");
                    }),

                Action::make('move_to_fruiting')
                    ->label('Pasar a Fructificación')
                    ->icon('heroicon-m-arrow-right-circle') // Icono de flecha
                    ->color('success') // Verde
                    ->visible(fn (Batch $record) => $record->status === 'incubation' && $record->quantity > 0) // Solo visible si está incubando
                    ->form([
                        TextInput::make('quantity_to_move')
                            ->label('¿Cuántas unidades pasan a Fructificación?')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(fn (Batch $record) => $record->quantity) // Por defecto sugiere mover todas
                            ->maxValue(fn (Batch $record) => $record->quantity), // No puedes mover más de las que hay
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

                        // ESCENARIO 2: Se mueve solo una PARTE (Dividir Lote)
                        
                        // 1. Restamos del lote original (Padre)
                        $record->decrement('quantity', $quantityToMove);
                        
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
