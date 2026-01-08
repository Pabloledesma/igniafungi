<?php

namespace App\Filament\Resources\BatchLosses;

use App\Filament\Resources\BatchLosses\Pages\CreateBatchLoss;
use App\Filament\Resources\BatchLosses\Pages\EditBatchLoss;
use App\Filament\Resources\BatchLosses\Pages\ListBatchLosses;
use App\Filament\Resources\BatchLosses\Schemas\BatchLossForm;
use App\Filament\Resources\BatchLosses\Tables\BatchLossesTable;
use App\Models\BatchLoss;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BatchLossResource extends Resource
{
    protected static ?string $model = BatchLoss::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trash';
    protected static string | UnitEnum | null $navigationGroup = 'Producción';
    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return BatchLossForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BatchLossesTable::configure($table);
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
            'index' => ListBatchLosses::route('/'),
            'create' => CreateBatchLoss::route('/create'),
            'edit' => EditBatchLoss::route('/{record}/edit'),
        ];
    }
}
