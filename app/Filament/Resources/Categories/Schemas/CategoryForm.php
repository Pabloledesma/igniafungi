<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use App\Models\Category;
use Illuminate\Support\Str;
use Filament\Forms\Set;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información de Categoría')
                    ->description('Configura los detalles principales de la categoría')
                    ->schema([
                        Grid::make() 
                            ->schema([
                                // 3. Todos los campos van aquí dentro
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                                
                                TextInput::make('slug')
                                    ->disabled()
                                    ->dehydrated()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(Category::class, 'slug', ignoreRecord: true),
                               
                            ]),

                            FileUpload::make('image')
                                    ->image()
                                    ->directory('categories'),
                            Toggle::make('is_active')
                                    ->required()
                                    ->default(true),
                                    
                    ]),
            ]);
    }
}
