<?php

namespace App\Filament\Resources\BatchLosses\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class BatchLossForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('user_id')
                    ->default(auth()->id())
                    ->required(),
                Select::make('batch_id')
                    ->relationship('batch', 'code')
                    ->label('Lote afectado')
                    ->searchable()
                    ->required(),
                Select::make('phase_id')
                    ->relationship('phase', 'name')
                    ->label('Fase donde ocurrió')
                    ->required(),
                TextInput::make('quantity')
                    ->label('Cantidad de unidades perdidas')
                    ->required()
                    ->numeric(),
                Select::make('reason')
                    ->label('Motivo principal')
                    ->options([
                        'contamination' => 'Contaminación',
                        'temperature' => 'Exceso de Temperatura',
                        'humidity' => 'Falta de Humedad',
                        'pest' => 'Plagas (Mosquitos/Ácaros)',
                        'management' => 'Error de Manejo',
                    ])
                    ->required(),
                Textarea::make('details')
                    ->label('Comentarios detallados')
                    ->placeholder('Describe qué se observó (ej: Trichoderma verde en la base)...')
                    ->columnSpanFull(),
            ]);
    }
}
