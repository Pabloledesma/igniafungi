<?php

namespace App\Filament\Resources\Manuals\Schemas;

use Filament\Schemas\Schema;

class ManualForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Grid::make()
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('title')
                            ->required()
                            ->label('Título')
                            ->live(onBlur: true)
                            // Use Filament\Forms\Set because normally TextInput actions use Forms\Set unless this project has overriden ALL utilities.
                            // But BatchForm used Filament\Schemas\Components\Utilities\Set.
                            // Let's try fully qualified standard Filament\Forms\Set first for the closure if possible, OR check BatchForm again.
                            // BatchForm line 17: use Filament\Schemas\Components\Utilities\Set;
                            // I will use THAT one.
                            ->afterStateUpdated(fn(string $operation, $state, \Filament\Schemas\Components\Utilities\Set $set) => $operation === 'create' ? $set('slug', \Illuminate\Support\Str::slug($state)) : null),

                        \Filament\Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->disabled()
                            ->dehydrated()
                            ->label('URL Amigable'),

                        \Filament\Forms\Components\TextInput::make('icon')
                            ->label('Icono (Emoji/Texto)')
                            ->placeholder('🍄')
                            ->maxLength(5),

                        \Filament\Forms\Components\Select::make('category')
                            ->options([
                                'Usuario' => 'Usuario',
                                'Negocio' => 'Negocio',
                                'Técnico' => 'Técnico',
                            ])
                            ->required()
                            ->label('Categoría'),

                        \Filament\Forms\Components\Toggle::make('is_published')
                            ->label('Publicado')
                            ->default(true),
                    ]),

                \Filament\Forms\Components\RichEditor::make('content')
                    ->label('Contenido')
                    ->required()
                    ->columnSpanFull()
                    ->fileAttachmentsDirectory('manuals/images')
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsVisibility('public')
                    ->fileAttachmentsMaxSize(2048) // 2MB
                    ->fileAttachmentsAcceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                    ->toolbarButtons([
                        'attachFiles',
                        'blockquote',
                        'bold',
                        'bulletList',
                        'codeBlock',
                        'h2',
                        'h3',
                        'italic',
                        'link',
                        'orderedList',
                        'redo',
                        'strike',
                        'table',
                        'undo',
                    ]),

                \Filament\Forms\Components\Hidden::make('user_id')
                    ->default(fn() => auth()->id()),
            ]);
    }
}
