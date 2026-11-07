<?php

declare(strict_types=1);

namespace App\Filament\Resources\SyncLogs\Schemas;

use App\Enums\SyncStatus;
use App\Models\SyncLog;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class SyncLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('sheetFile.name')->label('File'),
                TextEntry::make('status')
                    ->formatStateUsing(fn (SyncStatus $state): string => Str::headline($state->value))
                    ->badge()
                    ->color(fn (SyncStatus $state): string => match ($state) {
                        SyncStatus::Success => 'success',
                        SyncStatus::Failed => 'danger',
                        SyncStatus::Running => 'warning',
                        SyncStatus::Pending => 'gray',
                    }),
                TextEntry::make('rows_synced'),
                TextEntry::make('started_at')->dateTime(),
                TextEntry::make('finished_at')->dateTime(),
                TextEntry::make('duration')
                    ->state(fn (SyncLog $record): string => $record->finished_at === null
                        ? 'In progress'
                        : ((int) $record->started_at->diffInSeconds($record->finished_at)).'s'),
                TextEntry::make('error')->columnSpanFull(),
            ]);
    }
}
