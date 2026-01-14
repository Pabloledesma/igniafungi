<?php

namespace App\Filament\Resources\Transactions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                    Section::make('Transaction Details')
                        ->schema([
                                TextInput::make('description')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                TextInput::make('amount')
                                    ->required()
                                    ->numeric()
                                    ->prefix('$'),
                                Select::make('type')
                                    ->options([
                                            'income' => 'Income',
                                            'expense' => 'Expense',
                                        ])
                                    ->required(),
                                Select::make('category')
                                    ->options([
                                            'services' => 'Services',
                                            'payroll' => 'Payroll',
                                            'supplies' => 'Supplies',
                                            'maintenance' => 'Maintenance',
                                            'sales' => 'Sales',
                                            'other' => 'Other',
                                        ])
                                    ->required(),
                                DatePicker::make('date')
                                    ->required()
                                    ->default(now()),
                            ])->columns(2)
                ]);
    }
}
