<?php

declare(strict_types=1);

namespace App\Filament\Resources\SyncLogs\Pages;

use App\Filament\Resources\SyncLogs\SyncLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSyncLog extends ViewRecord
{
    protected static string $resource = SyncLogResource::class;
}
