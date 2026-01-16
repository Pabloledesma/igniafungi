<?php

namespace App\Filament\Resources\Manuals\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class ManualsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('icon')
                    ->label('')
                    ->size('lg'),

                \Filament\Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('category')
                    ->label('Categoría')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Usuario' => 'success',
                        'Negocio' => 'info',
                        'Técnico' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                \Filament\Tables\Columns\IconColumn::make('is_published')
                    ->label('Publicado')
                    ->boolean(),

                \Filament\Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última Edición')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'Usuario' => 'Usuario',
                        'Negocio' => 'Negocio',
                        'Técnico' => 'Técnico',
                    ]),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('view_public')
                    ->label('Ver Manual')
                    ->icon('heroicon-o-book-open')
                    ->url(fn(\App\Models\Manual $record): string => route('wiki.show', $record->slug))
                    ->openUrlInNewTab(),
                EditAction::make(),
                \Filament\Actions\ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
