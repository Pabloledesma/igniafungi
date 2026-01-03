<?php

namespace App\Filament\Resources\Supplies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SuppliesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Insumo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('category')
                    ->label('Categoría')
                    ->badge()
                    ->colors([
                        'primary' => 'substrate',
                        'warning' => 'grain',
                        'info' => 'liquid',
                        'gray' => 'packaging',
                    ]),

            TextColumn::make('quantity')
                ->label('Stock Actual')
                ->suffix(fn ($record) => ' ' . $record->unit) // Agrega "kg" o "L" al final
                ->color(fn ($record) => $record->quantity <= $record->min_stock ? 'danger' : 'success') // Rojo si falta, Verde si hay
                ->weight('bold')
                ->sortable(),

            TextColumn::make('cost_per_unit')
                ->label('Costo Unit.')
                ->money('USD')
                ->sortable(),
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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
