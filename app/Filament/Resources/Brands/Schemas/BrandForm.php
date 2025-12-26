<?php

namespace App\Filament\Resources\Brands\Schemas;

use App\Filament\Resources\Common\HasStandardFields;
use App\Models\Brand;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use App\Models\Category;
use Illuminate\Support\Str;
use Filament\Forms\Set;

class BrandForm
{
    use HasStandardFields;

    public static function configure(Schema $schema): Schema
    {
        return $schema
             ->components([
                Section::make('Información de marca')
                    ->description('Configura los detalles principales de la marca')
                    ->schema(static::getStandardSchema(Brand::class))
                ]);
    }
}
