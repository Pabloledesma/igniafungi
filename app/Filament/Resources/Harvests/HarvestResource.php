<?php

namespace App\Filament\Resources\Harvests;

use App\Filament\Resources\Harvests\Pages\CreateHarvest;
use App\Filament\Resources\Harvests\Pages\EditHarvest;
use App\Filament\Resources\Harvests\Pages\ListHarvests;
use App\Filament\Resources\Harvests\Schemas\HarvestForm;
use App\Filament\Resources\Harvests\Tables\HarvestsTable;
use App\Models\Harvest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class HarvestResource extends Resource
{
    protected static ?string $model = Harvest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string | UnitEnum | null $navigationGroup = 'Producción';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return HarvestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HarvestsTable::configure($table);
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
            'index' => ListHarvests::route('/'),
            'create' => CreateHarvest::route('/create'),
            'edit' => EditHarvest::route('/{record}/edit'),
        ];
    }
}
