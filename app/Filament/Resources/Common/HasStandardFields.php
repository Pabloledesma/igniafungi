<?php
namespace App\Filament\Resources\Common;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Str;

trait HasStandardFields {
    public static function getStandardSchema($modelClass): array {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
            
            TextInput::make('slug')
                ->disabled()
                ->dehydrated()
                ->required()
                ->unique($modelClass, 'slug', ignoreRecord: true),
                
            FileUpload::make('image')
                ->image()
                ->directory('uploads'),
                
            Toggle::make('is_active')
                ->required()
                ->default(true),
        ];
    }
}