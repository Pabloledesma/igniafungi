<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use App\Services\InventoryService;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('stock')
                    ->sortable(),
                TextColumn::make('category.name')
                    ->sortable(),
                TextColumn::make('strain.name')
                    ->sortable(),
                TextColumn::make('price')
                    ->money()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                IconColumn::make('is_featured')
                    ->boolean(),
                IconColumn::make('in_stock')
                    ->boolean(),
                IconColumn::make('on_sale')
                    ->boolean(),
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
                SelectFilter::make('category')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    ViewAction::make(),
                    DeleteAction::make(),
                    Action::make('deshidratar') // Ahora Filament lo registra en la tabla
                        ->label('Deshidratar')
                        ->icon('heroicon-o-beaker')
                        ->color('warning')
                        ->form([
                            TextInput::make('cantidad_fresco')
                                ->numeric()
                                ->required() // Importante para el test
                                ->minValue(0.1),
                            TextInput::make('cantidad_seco')
                                ->numeric()
                                ->placeholder('Opcional')
                                ->minValue(0.01),
                        ])
                        ->action(function (Product $record, array $data) {
                            try {
                                InventoryService::processDehydration(
                                    sourceProduct: $record,
                                    quantityRemoved: (float) $data['cantidad_fresco'],
                                    quantityAdded: (float) $data['cantidad_seco']
                                );

                                Notification::make()
                                    ->title('Transformación completada')
                                    ->success()
                                    ->body("Se procesaron {$data['cantidad_fresco']}kg frescos y se sumaron al stock deshidratado.")
                                    ->send();
                                    
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Error en el proceso')
                                    ->danger()
                                    ->body($e->getMessage())
                                    ->send();
                            }
                        }),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
