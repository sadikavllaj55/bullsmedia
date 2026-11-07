<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SyncStatus;
use Database\Factories\SyncLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property SyncStatus $status
 * @property int $rows_synced
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 */
#[Fillable(['sheet_file_id', 'status', 'rows_synced', 'error', 'started_at', 'finished_at'])]
class SyncLog extends Model
{
    /** @use HasFactory<SyncLogFactory> */
    use HasFactory, Prunable;

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(30));
    }

    /** @return BelongsTo<SheetFile, $this> */
    public function sheetFile(): BelongsTo
    {
        return $this->belongsTo(SheetFile::class);
    }

    /**
     * @return array<string, string|class-string<SyncStatus>>
     */
    protected function casts(): array
    {
        return [
            'status' => SyncStatus::class,
            'rows_synced' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
