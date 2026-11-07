<?php

declare(strict_types=1);

namespace App\Filament\Resources\SyncLogs\Tables;

use App\Enums\SyncStatus;
use App\Models\SyncLog;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SyncLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->formatStateUsing(fn (SyncStatus $state): string => Str::headline($state->value))
                    ->badge()
                    ->color(fn (SyncStatus $state): string => match ($state) {
                        SyncStatus::Success => 'success',
                        SyncStatus::Failed => 'danger',
                        SyncStatus::Running => 'warning',
                        SyncStatus::Pending => 'gray',
                    }),
                TextColumn::make('sheetFile.name')
                    ->label('File')
                    ->searchable(),
                TextColumn::make('rows_synced'),
                TextColumn::make('started_at')->dateTime(),
                TextColumn::make('duration')
                    ->state(fn (SyncLog $record): string => $record->finished_at === null
                        ? 'In progress'
                        : ((int) $record->started_at->diffInSeconds($record->finished_at)).'s'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        SyncStatus::Pending->value => 'Pending',
                        SyncStatus::Running->value => 'Running',
                        SyncStatus::Success->value => 'Success',
                        SyncStatus::Failed->value => 'Failed',
                    ]),
                SelectFilter::make('sheet_file_id')
                    ->label('File')
                    ->relationship('sheetFile', 'name'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
