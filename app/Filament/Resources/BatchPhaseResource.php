<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BatchPhaseResource\Pages;
use App\Models\BatchPhase;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use App\Filament\Resources\Batches\BatchResource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class BatchPhaseResource extends Resource
{
    protected static ?string $model = BatchPhase::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static \UnitEnum|string|null $navigationGroup = 'Producción';

    // Deshabilitar creación manual
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // Campos de solo lectura si alguien entra a "ver"
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch.code')
                    ->label('Lote')
                    ->searchable()
                    ->sortable()
                    ->url(fn($record) => BatchResource::getUrl('edit', ['record' => $record->batch_id])),

                TextColumn::make('phase.name')
                    ->label('Fase')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('notes')
                    ->label('Observaciones')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('phase_id')
                    ->relationship('phase', 'name')
                    ->label('Fase'),

                Tables\Filters\SelectFilter::make('batch_id')
                    ->relationship('batch', 'code')
                    ->label('Lote')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                // View action maybe?
            ])
            ->bulkActions([
                // No delete
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBatchPhases::route('/'),
        ];
    }
}
