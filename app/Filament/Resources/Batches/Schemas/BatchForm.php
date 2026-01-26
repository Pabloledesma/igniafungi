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
                        \Filament\Forms\Components\Toggle::make('is_historical')
                            ->label('¿Es un Registro Histórico?')
                            ->helperText('Activa esto para registrar lotes antiguos sin afectar el inventario actual ni el tablero Kanban.')
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                if ($state) {
                                    // Si se activa histórico, reseteamos el estado si era 'active'
                                    // O simplemente lo seteamos a null para obligar a seleccionar
                                    if ($get('status') === 'active') {
                                        $set('status', null);
                                    }
                                } else {
                                    // Si se desactiva, volvemos a 'active' por defecto
                                    $set('status', 'active');
                                }
                            })
                            ->columnSpanFull(),

                        Select::make('type')
                            ->label('Tipo de Lote')
                            ->options([
                                'grain' => 'Semilla (Grano)',
                                'bulk' => 'Bloque Productivo (Sustrato)',
                            ])
                            ->default('bulk')
                            ->required()
                            ->disabledOn('edit')
                            ->live(),

                        TextInput::make('container_type')
                            ->label('Tipo de Contenedor')
                            ->placeholder('Ej: Bolsa 10kg, Frasco 500cc')
                            ->maxLength(255),

                        Select::make('recipe_id')
                            ->label('Receta de Sustrato')
                            ->relationship('recipe', 'name') // Vincula con el modelo Recipe
                            ->searchable()
                            ->preload()
                            ->required(fn(Get $get) => $get('type') === 'bulk') // Obligatorio solo si es sustrato bulk
                            ->helperText('Seleccione la receta para descontar automáticamente los insumos del inventario.')
                            ->disabledOn('edit')
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $wetWeight = floatval($get('initial_wet_weight'));
                                if ($state && $wetWeight > 0) {
                                    $recipe = \App\Models\Recipe::find($state);
                                    if ($recipe) {
                                        $ratio = $recipe->dry_weight_ratio ?? 0.40;
                                        $yield = $wetWeight * $ratio;
                                        $set('expected_yield', round($yield, 2));
                                    }
                                }
                            }),

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
                                    ->createOptionUsing(fn($data) => $data['name']),
                            ])
                            ->visible(fn(Get $get) => $get('type') === 'grain'),

                        Select::make('phase_id')
                            ->label('Fase Inicial')
                            ->options(function (get $get) {
                                $type = $get('type');

                                // Si es semilla, quizás solo quieres mostrar fases de laboratorio/incubación
                                if ($type === 'grain') {
                                    return Phase::whereIn('slug', ['preparation', 'inoculation', 'incubation'])->pluck('name', 'id');
                                }

                                return Phase::orderBy('order')->pluck('name', 'id');
                            })
                            ->required(fn(Get $get) => !$get('is_historical'))
                            ->visible(fn(Get $get) => !$get('is_historical'))
                            ->dehydrated() // Permitimos que viaje aunque esté oculto? No, solo si es visible o si lo forzamos. Mejor standard behavior.
                            ->live()
                            ->loadStateFromRelationshipsUsing(fn($record, $state) => $record?->current_phase?->id ?? $state)
                            ->rules([
                                fn(Get $get, $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                    $phaseId = $value;
                                    $inoculationPhase = Phase::where('slug', 'inoculation')->first();

                                    if ($inoculationPhase && $phaseId == $inoculationPhase->id) {
                                        // Validamos si hay cepa seleccionada en el formulario (estado actual)
                                        $strainId = $get('strain_id');

                                        if (!$strainId) {
                                            $fail('No es posible pasar a Inoculación sin asignar una Cepa.');
                                        }
                                    }
                                },
                            ]),
                        Select::make('status')
                            ->label(fn(Get $get) => $get('is_historical') ? 'Estado Final' : 'Estado')
                            ->options(function (Get $get) {
                                $options = [
                                    'active' => 'Activo',
                                    'seeded' => 'Sembrado',
                                    'contaminated' => 'Contaminado',
                                    'finalized' => 'Finalizado / Agotado',
                                    'discarded' => 'Descartado',
                                ];

                                // Si es histórico, no permitimos "Activo" para evitar descuento de inventario
                                if ($get('is_historical')) {
                                    unset($options['active']);
                                }

                                return $options;
                            })
                            ->default('active')
                            ->required(fn(Get $get) => $get('is_historical'))
                            ->disabledOn('edit')
                            ->visible(true) // Siempre visible
                            ->live() // Para reaccionar a cambios
                            ->afterStateUpdated(function (Set $set, $state) {
                                // Lógica si se necesita al cambiar status
                            })
                            ->rules([
                                fn(Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if ($get('is_historical') && $value === 'active') {
                                        $fail('Un registro histórico no puede estar Activo (descontaría inventario). Seleccione otro estado.');
                                    }
                                },
                            ]),

                        \Filament\Forms\Components\Placeholder::make('loss_creation_notice')
                            ->hiddenLabel()
                            ->content(function (Get $get) {
                                return new \Illuminate\Support\HtmlString(
                                    '<div class="text-xs text-warning-600 bg-warning-50 p-2 rounded dark:bg-warning-950 dark:text-warning-400">
                                        ⚠️ Se creará automáticamente un registro de pérdida para las estadísticas de rendimiento.
                                     </div>'
                                );
                            })
                            ->visible(
                                fn(Get $get) =>
                                $get('is_historical') && in_array($get('status'), ['contaminated', 'discarded'])
                            )
                            ->columnSpanFull(),

                        Select::make('strain_id') // Cepa / Genética
                            ->label('Cepa / Genética')
                            ->relationship('strain', 'name')
                            ->live() // For Code Preview
                            ->disabled(fn($record) => $record?->strain_id !== null)
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

                        TextInput::make('origin_code')
                            ->label('Código de Origen')
                            ->helperText('Código del proveedor o lote original si es externo.')
                            ->maxLength(255)
                            ->visible(fn(Get $get) => $get('is_historical')),

                        DatePicker::make('inoculation_date') // Fecha Inoculación
                            ->label('Fecha Inoculación')
                            ->default(now())
                            ->required(fn(Get $get) => filled($get('strain_id')))
                            ->validationMessages([
                                'required' => 'La fecha de inoculación es obligatoria para calcular las proyecciones de la cepa seleccionada.',
                            ])
                            ->live() // Smart Code Generation
                            ->extraAttributes([
                                'x-on:change' => 'updateDate($event.target.value)',
                            ])->visible(function (Get $get) {
                                $phaseId = $get('phase_id');
                                if (!$phaseId)
                                    return true;

                                $preparationPhase = Phase::where('slug', 'preparation')->first();
                                return $phaseId != $preparationPhase?->id;
                            }),

                        DatePicker::make('estimated_harvest_date')
                            ->label('Fecha Estimada de Cosecha')
                            ->visible(fn(Get $get) => $get('is_historical')),

                        TextInput::make('code')
                            ->label('Código del Lote')
                            ->disabled() // No editable
                            ->dehydrated(false) // No lo enviamos en el request, el modelo se encarga
                            ->visible(true) // Siempre visible para verificar
                            ->placeholder(function (Get $get) {
                                $strainId = $get('strain_id');
                                $type = $get('type');
                                $date = $get('inoculation_date');

                                $prefix = 'SUB';
                                if ($strainId) {
                                    $strain = Strain::find($strainId);
                                    $prefix = $strain ? strtoupper(substr($strain->name, 0, 3)) : '...';
                                } elseif ($type === 'grain') {
                                    $prefix = 'GRA';
                                }

                                $datePart = $date ? \Carbon\Carbon::parse($date)->format('dmy') : now()->format('dmy');

                                return "{$prefix}-{$datePart}-?";
                            }),

                        Hidden::make('user_id')
                            ->default(auth()->id()),

                        TextInput::make('initial_wet_weight')
                            ->label('Peso Inicial (Húmedo)')
                            ->helperText(fn($state) => $state ? 'Input: ' . $state . 'kg. El sistema calculará la materia seca según la receta.' : 'Ingrese el peso total húmedo del lote (ej: peso del saco).')
                            ->numeric()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                // Auto-calculate Expected Yield
                                $recipeId = $get('recipe_id');
                                if ($state && $recipeId) {
                                    $recipe = \App\Models\Recipe::find($recipeId);
                                    if ($recipe) {
                                        $ratio = $recipe->dry_weight_ratio ?? 0.40;
                                        $yield = floatval($state) * $ratio;
                                        $set('expected_yield', round($yield, 2));
                                    }
                                }

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

                        Section::make('Finanzas y Proyecciones')
                            ->schema([
                                TextInput::make('production_cost')
                                    ->label('Costo de Producción')
                                    ->prefix('$')
                                    ->numeric()
                                    ->default(0),

                                TextInput::make('expected_yield')
                                    ->label('Rendimiento Esperado (100% BE)')
                                    ->helperText('Calculado automáticamente basado en la receta.')
                                    ->numeric()
                                    ->suffix('kg')
                                    ->readOnly(),

                                TextInput::make('biological_efficiency')
                                    ->label('Eficiencia Biológica Real')
                                    ->disabled()
                                    ->suffix('%')
                                    ->formatStateUsing(function ($record) {
                                        if (!$record || !$record->biological_efficiency)
                                            return 'N/A';
                                        return $record->biological_efficiency . '%';
                                    }),
                            ])->columns(3),


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
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $old, Set $set, Get $get, $record) {
                                        // Deterministic Calculation to avoid recursion
                                        $newContaminated = intval($state);

                                        if ($record) {
                                            // Edit Mode: Use DB original text to be safe
                                            $originalTotal = intval($record->quantity) + intval($record->contaminated_quantity);
                                            // New Live = Original Total - New Contaminated
                                            $set('quantity', max(0, $originalTotal - $newContaminated));
                                        } else {
                                            // Create Mode: We rely on the current value + diff
                                            // Or cleaner: We assume 'quantity' field currently holds 'Live + Contaminated' before this change?
                                            // No, 'quantity' is Live.
                                            // If user changes contaminated 0->1.
                                            // We take current Live, add old Contaminated (to get total), subtract new Contaminated.
                            
                                            $currentLive = intval($get('quantity'));
                                            $oldContaminated = intval($old);

                                            // Reconstruct effective Total
                                            $effectiveTotal = $currentLive + $oldContaminated;

                                            // Calculate new Live
                                            $set('quantity', max(0, $effectiveTotal - $newContaminated));
                                        }
                                    }),


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
                                    ->dehydrated(false) // No enviar al servidor
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
