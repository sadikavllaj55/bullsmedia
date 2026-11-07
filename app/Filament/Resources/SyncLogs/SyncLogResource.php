<?php

declare(strict_types=1);

namespace App\Filament\Resources\SyncLogs;

use App\Filament\Resources\SyncLogs\Pages\ListSyncLogs;
use App\Filament\Resources\SyncLogs\Pages\ViewSyncLog;
use App\Filament\Resources\SyncLogs\Schemas\SyncLogInfolist;
use App\Filament\Resources\SyncLogs\Tables\SyncLogsTable;
use App\Models\SyncLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SyncLogResource extends Resource
{
    protected static ?string $model = SyncLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Sync';

    protected static ?string $navigationLabel = 'Sync Logs';

    public static function infolist(Schema $schema): Schema
    {
        return SyncLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SyncLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSyncLogs::route('/'),
            'view' => ViewSyncLog::route('/{record}'),
        ];
    }
}
