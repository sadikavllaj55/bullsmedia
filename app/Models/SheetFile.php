<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SheetFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'spreadsheet_id', 'is_active', 'last_synced_at'])]
class SheetFile extends Model
{
    /** @use HasFactory<SheetFileFactory> */
    use HasFactory;

    /** @return HasMany<SheetTab, $this> */
    public function sheetTabs(): HasMany
    {
        return $this->hasMany(SheetTab::class);
    }

    /** @return HasMany<SyncLog, $this> */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    /** @return HasOne<SyncLog, $this> */
    public function latestSyncLog(): HasOne
    {
        return $this->hasOne(SyncLog::class)->latestOfMany();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }
}
