<?php

namespace App\Filament\Resources\Batches\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PhasesRelationManager extends RelationManager
{
    protected static string $relationship = 'phases';

    protected static ?string $title = 'Historial de Fases';

    // Icono opcional
    protected static \BackedEnum|string|null $icon = 'heroicon-o-clock';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // Read only
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Fase')
                    ->sortable(),

                TextColumn::make('pivot.notes')
                    ->label('Observaciones')
                    ->default('-')
                    ->wrap(),

                TextColumn::make('pivot.started_at')
                    ->label('Inicio')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('pivot.finished_at')
                    ->label('Fin')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('pivot.user_id')
                    ->label('Usuario')
                    ->formatStateUsing(fn($state) => \App\Models\User::find($state)?->name ?? 'Sistema'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // No create
            ])
            ->actions([
                // No edit/delete usually for audit log
            ])
            ->bulkActions([
                // No delete
            ])
            ->defaultSort('pivot_started_at', 'desc');
    }
}
