<?php

namespace App\Filament\Resources\Strains;

use App\Filament\Resources\Strains\Pages\CreateStrain;
use App\Filament\Resources\Strains\Pages\EditStrain;
use App\Filament\Resources\Strains\Pages\ListStrains;
use App\Filament\Resources\Strains\Schemas\StrainForm;
use App\Filament\Resources\Strains\Tables\StrainsTable;
use App\Models\Strain;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StrainResource extends Resource
{
    protected static ?string $model = Strain::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Name';

    public static function form(Schema $schema): Schema
    {
        return StrainForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StrainsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStrains::route('/'),
            'create' => CreateStrain::route('/create'),
            'edit' => EditStrain::route('/{record}/edit'),
        ];
    }
}
