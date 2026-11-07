<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncSheetFileJob;
use App\Models\SheetFile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('sheets:sync {--file= : SheetFile id} {--now : run synchronously without the queue}')]
#[Description('Sync active spreadsheet files')]
class SyncSheetsCommand extends Command
{
    public function handle(): int
    {
        $fileId = $this->option('file');
        $files = $fileId !== null
            ? SheetFile::query()->whereKey($fileId)->get()
            : SheetFile::query()->where('is_active', true)->get();
        $results = [];
        $failed = false;
        $runSynchronously = (bool) $this->option('now');

        if ($files->isEmpty() && $fileId !== null) {
            $this->error("Sheet file [{$fileId}] was not found.");
            $results[] = [$fileId, 'Failed', 0];
            $failed = true;
        }

        foreach ($files as $file) {
            try {
                if ($runSynchronously) {
                    SyncSheetFileJob::dispatchSync($file);
                    $syncLog = $file->syncLogs()->latest('id')->first();

                    if ($syncLog === null) {
                        $results[] = [$file->name, 'Success', 0];
                    } else {
                        $results[] = [$file->name, $syncLog->status->value, $syncLog->rows_synced];
                    }
                } else {
                    SyncSheetFileJob::dispatch($file);
                    $results[] = [$file->name, 'Queued', '-'];
                }
            } catch (Throwable $exception) {
                $this->error("Failed to sync [{$file->name}]: {$exception->getMessage()}");
                $results[] = [$file->name, 'Failed', 0];
                $failed = true;
            }
        }

        $this->table(['file', 'status', 'rows'], $results);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
