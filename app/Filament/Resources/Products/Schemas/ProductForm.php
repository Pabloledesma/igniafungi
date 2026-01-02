<?php

namespace App\Filament\Resources\Products\Schemas;

use Illuminate\Support\Str;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;


class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                
                Grid::make(3)->schema([
                    Section::make('Product information')->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                        
                        TextInput::make('slug')
                            ->disabled()
                            ->dehydrated()
                            ->required(),
                    
                        MarkdownEditor::make('description')
                            ->columnSpanFull()
                            ->fileAttachmentsDirectory('products'),
                        
                        FileUpload::make('images')
                            ->multiple()
                            ->directory('products')
                            ->disk('public')
                            ->maxFiles(5)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->reorderable()
                            ->columnSpanFull()
                  
                    ])->columnSpan(2)->columns(2),

                    Group::make()->schema([

                        Section::make('Price')->schema([
                            TextInput::make('price')
                                ->required()
                                ->numeric()
                                ->prefix('$'),
                        ]),

                        Section::make('Associations')->schema([
                            Select::make('category_id')
                                ->label('Category')
                                ->relationship('category', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
    
                            Select::make('strain_id')
                                ->label('Cepa')
                                ->relationship('strain', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),
    
                        Section::make('Status')->schema([
                            Toggle::make('is_active')
                                ->required(),
                            Toggle::make('is_featured')
                                ->required(),
                            Toggle::make('in_stock')
                                ->required(),
                            Toggle::make('on_sale')
                                ->required(),
                        ]),
                    ])->columnSpan(1),
                 
                ])->columnSpanFull(),
            ]);
    }
}
