<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SyncStatus;
use App\Exceptions\InvalidMappingException;
use App\Exceptions\MissingGoogleCredentialsException;
use App\Exceptions\MissingSourceColumnException;
use App\Exceptions\NothingToSyncException;
use App\Models\SheetFile;
use App\Services\Sync\SheetSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Jobs\SyncJob;
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
        $this->file->syncLogs()
            ->where('status', SyncStatus::Running)
            ->where('started_at', '<', now()->subSeconds($this->timeout))
            ->update([
                'status' => SyncStatus::Failed,
                'error' => 'The previous sync attempt did not finish before its timeout.',
                'finished_at' => now(),
            ]);

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

            if ($this->job !== null
                && ! $this->job instanceof SyncJob
                && $this->isPermanentFailure($exception)) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $syncLog = $this->file->syncLogs()
            ->where('status', SyncStatus::Running)
            ->latest('started_at')
            ->first();

        $syncLog?->update([
            'status' => SyncStatus::Failed,
            'error' => $exception?->getMessage() ?? 'The queue job failed.',
            'finished_at' => now(),
        ]);

        Log::error('Sheet file sync job permanently failed.', [
            'sheet_file_id' => $this->file->getKey(),
            'sync_log_id' => $syncLog?->getKey(),
            'exception' => $exception,
        ]);
    }

    private function isPermanentFailure(Throwable $exception): bool
    {
        return $exception instanceof InvalidMappingException
            || $exception instanceof MissingGoogleCredentialsException
            || $exception instanceof MissingSourceColumnException
            || $exception instanceof NothingToSyncException;
    }
}
