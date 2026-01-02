<?php

namespace App\Filament\Resources\Strains\Schemas;

use App\Models\Strain;
use Illuminate\Support\Str;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;

class StrainForm
{
    
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información de la cepa')
                    ->description('Configura los detalles principales de la cepa.')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                        
                        TextInput::make('slug')
                            ->disabled()
                            ->dehydrated()
                            ->required()
                            ->unique(Strain::class, 'slug', ignoreRecord: true),

                        TextInput::make('incubation_days')
                                ->required()
                                ->numeric()
                                ->default(15),
                            
                        FileUpload::make('image')
                            ->image()
                            ->directory('uploads')
                            ->disk('public'),
                            
                        Toggle::make('is_active')
                            ->required()
                            ->default(true),
                                
                    ]),
                
            ]);
    }
}
