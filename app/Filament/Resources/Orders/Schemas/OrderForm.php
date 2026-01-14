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

                            Section::make('Order Items')->schema([
                                Repeater::make('items')
                                    ->relationship()
                                    ->schema([
                                            Select::make('product_id')
                                                ->label('Product')
                                                ->relationship('product', 'name')
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->distinct()
                                                ->columnSpan(3)
                                                ->reactive()
                                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                                ->afterStateUpdated(fn($state, callable $set) => $set('unit_amount', Product::find($state)?->price ?? 0))
                                                ->afterStateUpdated(fn($state, callable $set) => $set('total_amount', Product::find($state)?->price ?? 0))
                                                ->afterStateUpdated(fn($state, callable $set) => $set('batch_id', null)),

                                            Select::make('batch_id')
                                                ->label('Batch / Lote')
                                                ->options(function (callable $get) {
                                                    $productId = $get('product_id');
                                                    if (!$productId) {
                                                        return [];
                                                    }
                                                    $product = Product::find($productId);
                                                    if (!$product || !$product->strain_id) {
                                                        return [];
                                                    }
                                                    return \App\Models\Batch::where('strain_id', $product->strain_id)
                                                        ->pluck('code', 'id');
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->columnSpan(3),

                                            TextInput::make('quantity')
                                                ->numeric()
                                                ->required()
                                                ->default(1)
                                                ->minValue(1)
                                                ->columnSpan(2)
                                                ->reactive()
                                                ->maxValue(10)
                                                ->afterStateUpdated(fn($state, callable $set, callable $get) => $set('total_amount', $state * $get('unit_amount'))),

                                            TextInput::make('unit_amount')
                                                ->numeric()
                                                ->disabled()
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