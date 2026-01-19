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
                \Filament\Forms\Components\Toggle::make('is_historical')
                    ->label('Registro Histórico (Cosecha Antigua)')
                    ->live()
                    ->columnSpanFull()
                    ->default(false),
                Select::make('batch_id')
                    ->relationship(
                        name: 'batch',
                        titleAttribute: 'code',
                        // FILTRO: Dinámico según si es histórico
                        modifyQueryUsing: fn(Builder $query, $get) => $query
                            ->where('type', 'bulk')
                            ->when(
                                $get('is_historical'),
                                // Histórico: Cualquier estado EXCEPTO borrados (y quizás filtra por status != active, como pidió el usuario)
                                // Usuario dijo: "Si is_historical es ON: Mostrar lotes donde status !== 'active'"
                                fn($q) => $q->where('status', '!=', 'active'),
                                // Normal: Solo activos (y no finalizados, para evitar errores en flujo normal)
                                // El filtro original era status != finalized.
                                // Usuario dijo: "Si is_historical es OFF: Mostrar lotes donde status === 'active'".
                                fn($q) => $q->where('status', 'active')
                            )
                    )
                    ->label('Lote a Cosechar')
                    ->searchable() // ¡Vital! Permite escribir para buscar
                    ->preload()    // Carga los primeros resultados rápido
                    ->required()
                    ->live(),
                \Filament\Forms\Components\Hidden::make('user_id')
                    ->default(auth()->id()),
                TextInput::make('weight')
                    ->label('Peso Fresco')
                    ->numeric()     // Solo permite números
                    ->suffix('kg')  // Adorno visual
                    ->required()
                    ->live(onBlur: true)
                    ->minValue(0.01)
                    ->helperText(fn($state) => $state ? 'Equivale a: ' . ($state * 1000) . ' gramos' : null)
                    ->rules([
                        fn(): \Closure => function (string $attribute, $value, \Closure $fail) {
                            if ($value > 5) {
                                $fail("¿Estás segura? Registraste {$value} Kg. Si son 500 gramos, debes poner 0.5");
                            }
                        },
                    ]),
                DatePicker::make('harvest_date')
                    ->label('Fecha de Cosecha')
                    ->default(now())
                    ->displayFormat('d/m/Y') // Lo que ve el usuario
                    ->format('Y-m-d')
                    ->required(),
                Textarea::make('notes')
                    ->label('Observaciones')
                    ->autosize()
            ]);
    }
}
