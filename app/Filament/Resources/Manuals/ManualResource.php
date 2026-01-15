<?php

namespace App\Filament\Resources\Manuals;

use App\Filament\Resources\Manuals\Pages\CreateManual;
use App\Filament\Resources\Manuals\Pages\EditManual;
use App\Filament\Resources\Manuals\Pages\ListManuals;
use App\Filament\Resources\Manuals\Schemas\ManualForm;
use App\Filament\Resources\Manuals\Tables\ManualsTable;
use App\Models\Manual;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ManualResource extends Resource
{
    protected static ?string $model = Manual::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return ManualForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ManualsTable::configure($table);
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
            'index' => ListManuals::route('/'),
            'create' => CreateManual::route('/create'),
            'view' => \App\Filament\Resources\Manuals\Pages\ViewManual::route('/{record}'),
            'edit' => EditManual::route('/{record}/edit'),
        ];
    }
}
