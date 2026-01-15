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
                    ->summarize([
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
                TextColumn::make('user.name')
                    ->label('Responsable')
                    ->sortable()
                    ->toggleable(),
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
                SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->label('Filtrar por Responsable'),
            ])
            ->actions([ // Standard method name is actions, but BatchesTable uses recordActions. I'll stick to actions if it works, or default to recordActions if that is the standard here. 
                // Wait, BatchesTable uses ->recordActions([...]). HarvestResource.php calls HarvestsTable::configure. 
                // If I look at BatchesTable, it returns $table->...->recordActions. 
                // Standard Filament v3 has actions(). 
                // However, BatchesTable uses recordActions(). 
                // I will use actions() because I previously saw recordActions() in HarvestsTable and thought it was weird.
                // Actually, simply replacing the namespace is safer. I will replace the imports and usages.
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('harvest_date', 'desc');
    }
}
