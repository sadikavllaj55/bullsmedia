<?php

declare(strict_types=1);

namespace App\Filament\Resources\SheetFiles\Tables;

use App\Enums\SyncStatus;
use App\Jobs\SyncSheetFileJob;
use App\Models\SheetFile;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class SheetFilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('latestSyncLog'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('spreadsheet_id')
                    ->label('Spreadsheet ID')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('sheet_tabs_count')
                    ->label('Tabs')
                    ->counts('sheetTabs'),
                ToggleColumn::make('is_active')
                    ->label('Active'),
                TextColumn::make('last_synced_at')
                    ->label('Last synced')
                    ->since()
                    ->placeholder('Never'),
                TextColumn::make('latestSyncLog.status')
                    ->label('Latest sync')
                    ->state(fn (SheetFile $record): ?string => $record->latestSyncLog?->status?->value)
                    ->formatStateUsing(fn (?string $state): string => $state === null ? 'Never' : Str::headline($state))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        SyncStatus::Success->value => 'success',
                        SyncStatus::Failed->value => 'danger',
                        SyncStatus::Running->value => 'warning',
                        SyncStatus::Pending->value => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('syncNow')
                    ->label('Sync now')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->action(function (SheetFile $record): void {
                        SyncSheetFileJob::dispatch($record);

                        Notification::make()
                            ->title('Sync queued')
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('syncSelected')
                    ->label('Sync selected')
                    ->action(function (Collection $records): void {
                        foreach ($records as $record) {
                            if ($record instanceof SheetFile) {
                                SyncSheetFileJob::dispatch($record);
                            }
                        }

                        Notification::make()
                            ->title('Syncs queued')
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Action::make('syncAllActive')
                    ->label('Sync all active')
                    ->action(function (): void {
                        foreach (SheetFile::query()->where('is_active', true)->get() as $file) {
                            SyncSheetFileJob::dispatch($file);
                        }

                        Notification::make()
                            ->title('Active file syncs queued')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
