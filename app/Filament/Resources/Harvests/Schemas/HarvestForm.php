<?php

namespace App\Filament\Resources\Harvests\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

class HarvestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('batch_id')
                    ->relationship(
                        name: 'batch', 
                        titleAttribute: 'code',
                        // FILTRO: Solo lotes de sustrato que NO estén finalizados
                        modifyQueryUsing: fn (Builder $query) => $query
                            ->where('type', 'bulk')
                            ->where('status', '!=', 'finalized') 
                    )
                    ->label('Lote a Cosechar')
                    ->searchable() // ¡Vital! Permite escribir para buscar
                    ->preload()    // Carga los primeros resultados rápido
                    ->required(),
                TextInput::make('weight')
                    ->label('Peso Fresco')
                    ->numeric()     // Solo permite números
                    ->suffix('kg')  // Adorno visual
                    ->required()
                    ->minValue(0.01), // Evita registros de 0kg
                DatePicker::make('harvest_date')
                    ->label('Fecha de Cosecha')
                    ->displayFormat('d/m/Y') // Lo que ve el usuario
                    ->format('Y-m-d')
                    ->required(),
                Textarea::make('notes')
                    ->label('Observaciones')
                    ->autosize()
            ]);
    }
}
