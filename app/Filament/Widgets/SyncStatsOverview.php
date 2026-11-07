<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\SyncStatus;
use App\Models\SheetFile;
use App\Models\SyncLog;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SyncStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $latestSync = SyncLog::query()
            ->where('status', SyncStatus::Success->value)
            ->whereNotNull('finished_at')
            ->max('finished_at');

        return [
            Stat::make('Active files', SheetFile::query()->where('is_active', true)->count()),
            Stat::make(
                'Rows synced today',
                SyncLog::query()
                    ->where('status', SyncStatus::Success->value)
                    ->whereDate('finished_at', today())
                    ->sum('rows_synced'),
            ),
            Stat::make(
                'Failed syncs (24h)',
                SyncLog::query()
                    ->where('status', SyncStatus::Failed->value)
                    ->where('finished_at', '>=', now()->subDay())
                    ->count(),
            ),
            Stat::make('Last sync', $latestSync === null ? 'Never' : Carbon::parse($latestSync)->diffForHumans()),
        ];
    }
}
