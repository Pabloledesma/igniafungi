<?php

namespace App\Filament\Resources\Transactions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                    TextColumn::make('description')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('amount')
                        ->money('USD')
                        ->sortable(),
                    TextColumn::make('type')
                        ->badge()
                        ->colors([
                                'success' => 'income',
                                'danger' => 'expense',
                            ])
                        ->sortable(),
                    TextColumn::make('category')
                        ->searchable()
                        ->sortable()
                        ->badge(),
                    TextColumn::make('date')
                        ->date()
                        ->sortable(),
                    TextColumn::make('created_at')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('updated_at')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ])
            ->filters([
                    SelectFilter::make('type')
                        ->options([
                                'income' => 'Income',
                                'expense' => 'Expense',
                            ]),
                ])
            ->recordActions([
                    EditAction::make(),
                ])
            ->toolbarActions([
                    BulkActionGroup::make([
                        DeleteBulkAction::make(),
                    ]),
                ]);
    }
}
