<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Repeater;
use App\Models\Product;
use App\Models\User;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\Number;
use Filament\Forms\Components\Hidden;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                    Group::make()->schema([
                        Section::make('Order information')->schema([
                            Select::make('user_id')
                                ->label('Customer')
                                ->relationship('user', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),

                            Select::make('payment_method')
                                ->options([
                                        'stripe' => 'Stripe',
                                        'cod' => 'Cash on delivery',
                                        'paypal' => 'Paypal',
                                        'paystack' => 'Paystack',
                                        'flutterwave' => 'Flutterwave',
                                    ])
                                ->required(),

                            Select::make('payment_status')
                                ->options([
                                        'pending' => 'Pending',
                                        'paid' => 'Paid',
                                        'failed' => 'Failed',
                                    ])
                                ->default('pending')
                                ->required(),

                            ToggleButtons::make('status')
                                ->inline()
                                ->default('new')
                                ->required()
                                ->options([
                                        'new' => 'New',
                                        'processing' => 'Processing',
                                        'shipped' => 'Shipped',
                                        'delivered' => 'Delivered',
                                        'cancelled' => 'Cancelled',
                                    ])
                                ->colors([
                                        'new' => 'info',
                                        'processing' => 'warning',
                                        'shipped' => 'success',
                                        'delivered' => 'success',
                                        'cancelled' => 'danger',
                                    ])
                                ->icons([
                                        'new' => 'heroicon-m-sparkles',
                                        'processing' => 'heroicon-m-arrow-path',
                                        'shipped' => 'heroicon-m-truck',
                                        'delivered' => 'heroicon-m-check-circle',
                                        'cancelled' => 'heroicon-m-x-circle',
                                    ]),

                            Select::make('currency')
                                ->options([
                                        'usd' => 'USD',
                                        'eur' => 'EUR',
                                        'gbp' => 'GBP',
                                        'aud' => 'AUD',
                                        'cad' => 'CAD',
                                        'chf' => 'CHF',
                                        'cny' => 'CNY',
                                        'dkk' => 'DKK',
                                        'hkd' => 'HKD',
                                        'huf' => 'HUF',
                                        'idr' => 'IDR',
                                        'col' => 'COL',
                                        'ils' => 'ILS',
                                        'inr' => 'INR',
                                    ])
                                ->default('col')
                                ->required(),

                            Select::make('shipping_method')
                                ->options([
                                        'pickup' => 'Pick up',
                                        'ups' => 'UPS',
                                        'dhl' => 'DHL',
                                    ]),

                            TextInput::make('shipping_amount')
                                ->numeric()
                                ->required(),

                            Textarea::make('notes')
                                ->columnSpanFull()
                                ->required(),

                            TextInput::make('delivery.delivery_notes')
                                ->label('Notas de Entrega (Para Preventas)')
                                ->placeholder('Ej: Entregar solo en la mañana...')
                                ->visible(function (callable $get) {
                                    // Check if any item in the repeater is a pre-order
                                    $items = $get('items') ?? [];
                                    foreach ($items as $item) {
                                        if (!empty($item['is_preorder'])) {
                                            return true;
                                        }
                                    }
                                    return false;
                                })
                                ->columnSpanFull(),

                            Section::make('Order Items')->schema([
                                Repeater::make('items')
                                    ->relationship()
                                    ->schema([
                                            \Filament\Forms\Components\Checkbox::make('is_preorder')
                                                ->label('¿Es Preventa?')
                                                ->columnSpanFull()
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, callable $set) {
                                                    if (!$state) {
                                                        $set('strain_id', null);
                                                        $set('batch_id', null);
                                                    } else {
                                                        $set('product_id', null);
                                                    }
                                                }),

                                            Select::make('product_id')
                                                ->label('Product')
                                                ->relationship('product', 'name')
                                                ->searchable()
                                                ->preload()
                                                ->required() // Always required now
                                                ->disabled(fn(callable $get) => $get('is_preorder')) // Disabled if pre-order
                                                ->reactive()
                                                ->afterStateUpdated(fn($state, callable $set) => $set('unit_amount', Product::find($state)?->price ?? 0))
                                                ->afterStateUpdated(fn($state, callable $set) => $set('total_amount', Product::find($state)?->price ?? 0))
                                                ->afterStateUpdated(fn($state, callable $set) => $set('batch_id', null))
                                                ->columnSpan(3)
                                                ->dehydrated(true),

                                            Select::make('strain_id')
                                                ->label('Cepa (Para Preventa)')
                                                ->options(function () {
                                                    return \App\Models\Strain::whereHas('batches', function ($q) {
                                                        $q->whereIn('status', ['incubation', 'fruiting']);
                                                    })->pluck('name', 'id');
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->visible(fn(callable $get) => $get('is_preorder'))
                                                ->required(fn(callable $get) => $get('is_preorder'))
                                                ->columnSpan(3)
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                    $set('batch_id', null);
                                                    if ($state) {
                                                        // Try to find a product for this strain to get price
                                                        $product = Product::where('strain_id', $state)->first();

                                                        if ($product) {
                                                            $set('product_id', $product->id);
                                                            $set('unit_amount', $product->price);
                                                            $set('total_amount', $product->price * $get('quantity'));
                                                        }
                                                    }
                                                }),

                                            Select::make('batch_id')
                                                ->label('Lote en Producción')
                                                ->visible(fn(callable $get) => $get('is_preorder'))
                                                ->required(fn(callable $get) => $get('is_preorder'))
                                                ->options(function (callable $get) {
                                                    $strainId = $get('strain_id');
                                                    if (!$strainId)
                                                        return [];

                                                    return \App\Models\Batch::where('strain_id', $strainId)
                                                        ->whereIn('status', ['incubation', 'fruiting'])
                                                        ->get()
                                                        ->pluck('code', 'id');
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->columnSpan(3)
                                                ->reactive()
                                                ->afterStateUpdated(fn($state, callable $set) => $set('quantity', 1)),

                                            Placeholder::make('estimated_harvest_date')
                                                ->label('Fecha Estimada de Cosecha')
                                                ->visible(fn(callable $get) => $get('is_preorder') && $get('batch_id'))
                                                ->content(function (callable $get) {
                                                    $batchId = $get('batch_id');
                                                    if (!$batchId)
                                                        return '---';

                                                    $batch = \App\Models\Batch::find($batchId);
                                                    if (!$batch)
                                                        return '---';

                                                    $currentPhase = $batch->phases->where('pivot.finished_at', null)->first();
                                                    $days = $batch->strain->incubation_days ?? 15;
                                                    if ($currentPhase && $currentPhase->name === 'Fructificación')
                                                        $days = 7;

                                                    return $currentPhase && $currentPhase->pivot->started_at
                                                        ? \Carbon\Carbon::parse($currentPhase->pivot->started_at)->addDays($days)->format('d F, Y')
                                                        : 'Desconocido';
                                                })
                                                ->columnSpan(3),

                                            TextInput::make('quantity')
                                                ->numeric()
                                                ->required()
                                                ->default(1)
                                                ->minValue(1)
                                                ->columnSpan(2)
                                                ->reactive()
                                                ->maxValue(1000)
                                                ->afterStateUpdated(fn($state, callable $set, callable $get) => $set('total_amount', $state * $get('unit_amount'))),

                                            TextInput::make('unit_amount')
                                                ->numeric()
                                                ->dehydrated()
                                                ->columnSpan(2)
                                                ->required(),

                                            TextInput::make('total_amount')
                                                ->numeric()
                                                ->disabled()
                                                ->dehydrated()
                                                ->columnSpan(2)
                                                ->required(),

                                        ])->columns(12),

                                Placeholder::make('grand_total_price')
                                    ->label('Grand Total')
                                    ->numeric()
                                    ->content(function (callable $get, callable $set) {
                                        $total = 0;
                                        if (!$repeaters = $get('items')) {
                                            return $total;
                                        }
                                        foreach ($repeaters as $key => $repeater) {
                                            $total += $get("items.{$key}.total_amount");
                                        }

                                        $set('grand_total', $total);
                                        return Number::currency($total);
                                    }),

                                Hidden::make('grand_total')
                                    ->default(0)

                            ])->columnSpanFull()

                        ])->columns(2)
                    ])->columnSpanFull()

                ]);
    }
}


/*
               TextInput::make('grand_total')
                   ->numeric(),
               TextInput::make('payment_method'),
               TextInput::make('payment_status'),
               TextInput::make('status')
                   ->default('new'),
               TextInput::make('currency'),
               TextInput::make('shipping_amount')
                   ->numeric(),
               TextInput::make('shipping_method'),
               Textarea::make('notes')
                   ->columnSpanFull(),
                   */