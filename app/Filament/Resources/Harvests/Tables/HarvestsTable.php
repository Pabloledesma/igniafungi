<?php

namespace App\Filament\Resources\Harvests\Tables;

use Dom\Text;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\Summarizers\Sum;

class HarvestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch.code')
                    ->label('Lote Origen')
                    ->searchable(),
                TextColumn::make('weight')
                    ->label('Peso')
                    ->suffix(' kg')
                    ->summarize([ // ¡Truco Pro! Muestra el total abajo de la tabla
                        Sum::make()
                            ->label('Total Cosechado'),
                    ]),
                TextColumn::make('harvest_date')
                    ->label('Fecha de Cosecha')
                    ->date()
                    ->sortable(),
                TextColumn::make('notes')
                    ->label('Observaciones')
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
                SelectFilter::make('strain')
                    ->relationship('batch.strain', 'name')
                    ->label('Filtrar por Genética'),
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
