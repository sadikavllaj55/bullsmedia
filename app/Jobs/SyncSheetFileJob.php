<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SyncStatus;
use App\Models\SheetFile;
use App\Services\Sync\SheetSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SyncSheetFileJob implements ShouldBeUnique, ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 600;

    use Queueable;

    public function __construct(public readonly SheetFile $file) {}

    public function uniqueId(): string
    {
        return (string) $this->file->getKey();
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(SheetSyncService $service): void
    {
        $syncLog = $this->file->syncLogs()->create([
            'status' => SyncStatus::Running,
            'started_at' => now(),
        ]);

        try {
            $rowsSynced = $service->sync($this->file);
            $finishedAt = now();

            $syncLog->update([
                'status' => SyncStatus::Success,
                'rows_synced' => $rowsSynced,
                'finished_at' => $finishedAt,
            ]);

            $this->file->forceFill(['last_synced_at' => $finishedAt])->save();
        } catch (Throwable $exception) {
            $syncLog->update([
                'status' => SyncStatus::Failed,
                'error' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            Log::error('Sheet file sync attempt failed.', [
                'sheet_file_id' => $this->file->getKey(),
                'sync_log_id' => $syncLog->getKey(),
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Sheet file sync job permanently failed.', [
            'sheet_file_id' => $this->file->getKey(),
            'exception' => $exception,
        ]);
    }
}
