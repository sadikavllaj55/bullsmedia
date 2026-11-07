<?php

declare(strict_types=1);

namespace App\Filament\Resources\SheetFiles\RelationManagers;

use App\Enums\SyncStatus;
use App\Models\SyncLog;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class SyncLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'syncLogs';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('status')
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
                TextColumn::make('rows_synced'),
                TextColumn::make('started_at')->dateTime(),
                TextColumn::make('duration')
                    ->state(fn (SyncLog $record): string => $record->finished_at === null
                        ? 'In progress'
                        : ((int) $record->started_at->diffInSeconds($record->finished_at)).'s'),
            ])
            ->headerActions([])
            ->recordActions([
                Action::make('viewError')
                    ->label('View error')
                    ->icon(Heroicon::OutlinedEye)
                    ->visible(fn (SyncLog $record): bool => $record->error !== null)
                    ->modalHeading('Sync error')
                    ->modalContent(fn (SyncLog $record): HtmlString => new HtmlString(
                        '<pre class="whitespace-pre-wrap">'.e($record->error ?? '').'</pre>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->action(fn (): null => null),
            ]);
    }
}
