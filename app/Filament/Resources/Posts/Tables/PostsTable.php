<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Models\Post;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // 1. Miniatura del Post
                ImageColumn::make('image')
                    ->label('Portada')
                    ->circular() // Se ve muy bien en forma circular
                    ->disk('public') // Asegúrate de que apunte a tu disco public
                    ->defaultImageUrl(asset('images/placeholder-fungi.jpg')),

                // 2. Título y Autor (Usamos description para ahorrar espacio)
                TextColumn::make('title')
                    ->label('Título del Artículo')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Post $record): string => ($record->user?->name ?? 'Autor desconocido')),

                // 3. Producto Relacionado (Para ver qué estamos vendiendo)
                TextColumn::make('product.name')
                    ->label('Producto')
                    ->badge()
                    ->color('warning')
                    ->placeholder('Sin producto'),

                // 4. Estado de Publicación (Toggle permite cambiarlo sin entrar a Editar)
                ToggleColumn::make('is_published')
                    ->label('Publicado')
                    ->onColor('success')
                    ->offColor('danger'),

                // 5. Fecha de creación
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ])
                ->filters([
                    // Filtro rápido para ver solo lo publicado o borradores
                TernaryFilter::make('is_published')
                    ->label('Estado de Publicación')
                    ->boolean()
                    ->trueLabel('Solo Publicados')
                    ->falseLabel('Solo Borradores')
                    ->native(false),
                ])
                ->recordActions([
                    EditAction::make(),
                    // Acción para ir directo al blog y ver cómo quedó
                    Action::make('view')
                        ->label('Ver')
                        ->icon('heroicon-o-eye')
                        ->url(fn (Post $record): string => route('blog.show', $record->slug))
                        ->openUrlInNewTab(),
                ])
                ->toolbarActions([
                    BulkActionGroup::make([
                        DeleteBulkAction::make(),
                    ]),
            ]);
    }
}
