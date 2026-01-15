<?php

namespace App\Filament\Resources\Batches\Schemas;

use App\Models\Phase;
use App\Models\Strain;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class BatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Envolvemos todo en un Group o Section con x-data
                Section::make('Información del Lote')
                    ->extraAttributes(['x-data' => 'batchAutomation'])
                    ->columnSpanFull()
                    ->schema([
                        Select::make('type')
                            ->label('Tipo de Lote')
                            ->options([
                                'grain' => 'Semilla (Grano)',
                                'bulk' => 'Bloque Productivo (Sustrato)',
                            ])
                            ->default('bulk')
                            ->required()
                            ->live(),

                        Select::make('recipe_id')
                            ->label('Receta de Sustrato')
                            ->relationship('recipe', 'name') // Vincula con el modelo Recipe
                            ->searchable()
                            ->preload()
                            ->required(fn(Get $get) => $get('type') === 'bulk') // Obligatorio solo si es sustrato bulk
                            ->helperText('Seleccione la receta para descontar automáticamente los insumos del inventario.'),

                        // GRUPO DE CAMPOS EXCLUSIVOS PARA SEMILLA 🌾
                        Grid::make(2)
                            ->schema([
                                Select::make('grain_type')
                                    ->label('Tipo de Grano')
                                    ->options([
                                        'Mijo' => 'Mijo',
                                        'Sorgo' => 'Sorgo',
                                        'Trigo' => 'Trigo',
                                        'Maíz' => 'Maíz',
                                        'Avena' => 'Avena',
                                    ])
                                    ->default('Trigo')
                                    ->required()
                                    ->searchable()
                                    ->createOptionForm([
                                        TextInput::make('name')->required(),
                                    ])
                                    ->createOptionUsing(fn($data) => $data['name']), // Solo si quieres guardar en una tabla aparte, si no, usa TextInput simple.
                                // Para simplificar ahora, usemos TextInput o Select simple:
                                // ->options(['Mijo' => 'Mijo', ...]) 

                            ])
                            ->visible(fn(Get $get) => $get('type') === 'grain'),
                        Select::make('phase_id')
                            ->label('Fase')
                            ->options(function (get $get) {
                                $type = $get('type');

                                // Si es semilla, quizás solo quieres mostrar fases de laboratorio/incubación
                                if ($type === 'grain') {
                                    return Phase::whereIn('slug', ['preparation', 'inoculation', 'incubation'])->pluck('name', 'id');
                                }

                                return Phase::orderBy('order')->pluck('name', 'id');
                            })
                            ->required()
                            ->dehydrated()
                            ->live()
                            ->loadStateFromRelationshipsUsing(fn($record, $state) => $record?->current_phase?->id ?? $state)
                            ->rules([
                                fn(Get $get, $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                    $phaseId = $value;
                                    $inoculationPhase = Phase::where('slug', 'inoculation')->first();

                                    if ($inoculationPhase && $phaseId == $inoculationPhase->id) {
                                        // Validamos si hay cepa seleccionada en el formulario (estado actual)
                                        $strainId = $get('strain_id');

                                        // Logica centralizada:
                                        // Problema: canTransitionToInoculation usa $this->strain_id (DB).
                                        // Aquí necesitamos validar lo que está en el FORMULARIO antes de guardar.
                                        // Entonces replicamos la logica simple: !is_null($strainId)
                                        // O instanciamos un modelo dummy? No, validación directa es más segura.
                        
                                        if (!$strainId) {
                                            $fail('No es posible pasar a Inoculación sin asignar una Cepa.');
                                        }
                                    }
                                },
                            ]),
                        Select::make('status')
                            ->options([
                                'active' => 'Activo',
                                'contaminated' => 'Contaminado',
                                'finalized' => 'Finalizado',
                            ])
                            ->default('active')
                            ->required(),

                        Select::make('strain_id') // Cepa / Genética
                            ->label('Cepa / Genética')
                            ->relationship('strain', 'name')
                            ->extraAttributes([
                                'x-on:change' => 'updatePrefix($event.target.options[$event.target.selectedIndex].text)',
                            ])->visible(function (Get $get) {
                                $phaseId = $get('phase_id');
                                if (!$phaseId)
                                    return true; // Visible por defecto si no hay selección
                    
                                $preparationPhase = Phase::where('slug', 'preparation')->first();
                                // Solo es visible si el ID seleccionado NO es el de preparación
                                return $phaseId != $preparationPhase?->id;
                            }),

                        DatePicker::make('inoculation_date') // Fecha Inoculación
                            ->label('Fecha Inoculación')
                            ->default(now())
                            ->required(fn(Get $get) => filled($get('strain_id')))
                            ->validationMessages([
                                'required' => 'La fecha de inoculación es obligatoria para calcular las proyecciones de la cepa seleccionada.',
                            ])
                            ->extraAttributes([
                                'x-on:change' => 'updateDate($event.target.value)',
                            ])->visible(function (Get $get) {
                                $phaseId = $get('phase_id');
                                if (!$phaseId)
                                    return true;

                                $preparationPhase = Phase::where('slug', 'preparation')->first();
                                return $phaseId != $preparationPhase?->id;
                            }),

                        TextInput::make('code')
                            ->label('Código del Lote')
                            ->disabled() // No editable
                            ->dehydrated(false) // No lo enviamos en el request, el modelo se encarga
                            ->visible(fn($record) => $record !== null), // Se muestra solo al editar

                        Hidden::make('user_id')
                            ->default(auth()->id()),

                        TextInput::make('weigth_dry')
                            ->label('Peso en SECO (kg)')
                            ->helperText(fn($state) => $state ? 'Procesando ' . number_format($state * 1000) . ' gramos de sustrato.' : 'Vital para calcular la eficiencia. Solo materia seca.')
                            ->numeric()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state) {
                                if ($state > 35 && $state <= 50) {
                                    \Filament\Notifications\Notification::make()
                                        ->warning()
                                        ->title('Atención: Límite operativo')
                                        ->body('Este lote está cerca del límite de capacidad operativa de la planta (35kg).')
                                        ->send();
                                }
                            })
                            ->rules([
                                fn(): \Closure => function (string $attribute, $value, \Closure $fail) {
                                    if ($value > 50) {
                                        $fail('Error de capacidad: El sistema no permite lotes mayores a 50kg. Por favor, verifica si estás ingresando gramos en lugar de kilos.');
                                    }
                                },
                            ])
                            ->required(),


                        Section::make('Dimensiones del Lote')
                            ->columnSpanFull()
                            ->columns(4)
                            ->schema([
                                // 1. CANTIDAD ACTUAL (VIVAS)
                                TextInput::make('quantity')
                                    ->label('Cantidad Disponible (Vivas)')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->live() // Escucha cambios
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state) {
                                        // Opcional: Si quisieras recalcular algo al cambiar esto manualmente
                                    }),

                                // 2. CANTIDAD CONTAMINADA (MUERTAS)
                                TextInput::make('contaminated_quantity')
                                    ->label('Unidades Contaminadas')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated() // Permitimos que viaje si el observer lo actualiza, o false si confiamos 100% en observer. User asked for disabled.
                                    ->formatStateUsing(fn($record) => $record ? $record->losses()->where('reason', 'Contaminación')->sum('quantity') : 0),


                                // 3. PESO POR UNIDAD
                                TextInput::make('bag_weight')
                                    ->label('Peso por Unidad')
                                    ->suffix('kg')
                                    ->numeric()
                                    ->step(0.01)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->helperText(fn($state) => $state ? 'Equivale a: ' . ($state * 1000) . ' gramos' : null),

                                // 4. PESO TOTAL ESTIMADO (Vivas + Contaminadas o Solo Vivas?)
                                // Usualmente quieres saber el peso de lo que tienes VIVO.
                                TextInput::make('total_weight_display')
                                    ->label('Peso Total (Vivas)')
                                    ->suffix('kg')
                                    ->readonly() // Evita edición manual
                                    ->extraInputAttributes(['class' => 'font-bold text-success-600']) // Estilo para resaltar
                                    ->placeholder(function (Get $get) {
                                        $qty = (float) $get('quantity');
                                        $weight = (float) $get('bag_weight');
                                        return number_format($qty * $weight, 2) . ' kg';
                                    })
                                    ->live(),
                            ]),

                        Section::make('Bitácora')
                            ->columnSpanFull()
                            ->schema([
                                MarkdownEditor::make('observations')
                                    ->label('Observaciones / Eventos')
                                    ->helperText('Registra aquí bajas por autoconsumo, regalias o notas técnicas.')

                            ]),

                    ])->columns(2),
            ]);
    }
}
