<?php

namespace App\Filament\Resources\Supplies\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class SupplyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                    Section::make('Detalles del Insumo')
                        ->columns(2)
                        ->schema([
                    TextInput::make('name')
                        ->label('Nombre del Insumo')
                        ->placeholder('Ej: Bolsa de Polipropileno')
                        ->required(),

                    Select::make('category')
                        ->label('Categoría')
                        ->options([
                            'substrate' => 'Sustrato (Aserrín, Paja)',
                            'grain' => 'Grano / Semilla',
                            'liquid' => 'Líquidos (Alcohol, Agua destilada)',
                            'packaging' => 'Empaque (Bolsas, Cajas)',
                            'lab' => 'Material de Laboratorio',
                            'other' => 'Otros',
                        ])
                        ->required(),

                    Select::make('unit')
                        ->label('Unidad de Medida')
                         ->options([
                            'kg' => 'kg',
                            'unidades' => 'unidades',
                            'litros' => 'litros',
                        ])
                        ->required(),
                ]),

            Section::make('Inventario y Costos')
                ->columns(3)
                ->schema([
                    TextInput::make('quantity')
                        ->label('Cantidad Actual')
                        ->numeric()
                        ->default(0)
                        ->required(),

                    TextInput::make('min_stock')
                        ->label('Alerta de Stock Mínimo')
                        ->helperText('Te avisaremos cuando baje de esta cantidad')
                        ->numeric()
                        ->default(10)
                        ->required(),

                    TextInput::make('cost_per_unit')
                        ->label('Costo por Unidad')
                        ->prefix('$')
                        ->numeric(),
                ]),
            ]);
    }
}
