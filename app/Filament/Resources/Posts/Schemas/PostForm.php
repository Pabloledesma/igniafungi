<?php

namespace App\Filament\Resources\Posts\Schemas;

use Illuminate\Support\Str;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('user_id')
                    ->default(auth()->id())
                    ->required(),
                TextInput::make('title')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state)))
                    ->required(),
                TextInput::make('slug')->required()->unique(ignoreRecord: true),
                Textarea::make('summary')
                    ->label('Resumen del post')
                    ->placeholder('Escribe una breve descripción para atraer lectores...')
                    ->required() // Para que coincida con la base de datos
                    ->maxLength(255)
                    ->columnSpanFull(),
                FileUpload::make('image')->image()->directory('blog'),
                RichEditor::make('content')
                    ->columnSpanFull()
                    ->fileAttachmentsDirectory('blog-content'),
                Section::make('Promoción de Producto')
                    ->description('Selecciona un producto para mostrarlo junto al artículo')
                    ->schema([
                        Select::make('product_id')
                            ->label('Producto Relacionado')
                            ->relationship('product', 'name') // Usa la relación definida en el modelo
                            ->searchable() // Permite buscar por nombre si tienes muchos productos
                            ->preload()    // Carga los primeros para que sea más rápido
                            ->placeholder('Selecciona un producto (opcional)'),
                    ])->collapsible(),
                Toggle::make('is_published')->label('¿Publicar ahora?'),
            ]);
    }
}
