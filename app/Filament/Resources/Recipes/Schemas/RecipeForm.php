<?php

namespace App\Filament\Resources\Recipes\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

class RecipeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre de la Receta')
                    ->required(),

                TextInput::make('dry_weight_ratio')
                    ->label('Ratio de Peso Seco')
                    ->helperText('Representa el porcentaje de sólidos. Ej: 0.40 para 40%.')
                    ->numeric()
                    ->default(0.40)
                    ->step(0.01)
                    ->required(),

                Repeater::make('recipeSupplies') // Debe coincidir con la relación en el modelo Recipe
                    ->relationship('recipeSupplies') // Filament detecta automáticamente la tabla pivote
                    ->schema([
                        Select::make('supply_id')
                            ->label('Insumo / Suministro')
                            ->relationship('supply', 'name') // Relación desde el pivote al insumo
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('calculation_mode')
                            ->label('Tipo de Cálculo')
                            ->options([
                                'percentage' => 'Porcentaje (%) sobre peso seco',
                                'fixed_per_unit' => 'Unidades fijas por cada bolsa/frasco',
                            ])
                            ->required()
                            ->live(),

                        TextInput::make('value')
                            ->label(fn(Get $get) => $get('calculation_mode') === 'percentage' ? 'Porcentaje' : 'Cantidad por Unidad')
                            ->numeric()
                            ->required()
                            ->suffix(fn(Get $get) => $get('calculation_mode') === 'percentage' ? '%' : 'u.'),
                    ])
                    ->columns(3)
                    ->addActionLabel('Agregar Insumo a la Receta')
                    ->defaultItems(1)
            ]);
    }
}
