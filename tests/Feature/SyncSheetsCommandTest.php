<?php

declare(strict_types=1);

use App\Contracts\SpreadsheetReader;
use App\Enums\ColumnType;
use App\Enums\SyncStatus;
use App\Jobs\SyncSheetFileJob;
use App\Models\SheetFile;
use App\Models\SheetTab;
use App\Models\SyncLog;
use App\Services\Google\FakeSpreadsheetReader;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('dispatches one job per active sheet file and skips inactive files', function (): void {
    Queue::fake();
    $activeFiles = SheetFile::factory()->count(2)->create(['is_active' => true]);
    SheetFile::factory()->create(['is_active' => false]);

    $this->artisan('sheets:sync')->assertSuccessful();

    Queue::assertPushed(SyncSheetFileJob::class, 2);

    foreach ($activeFiles as $activeFile) {
        Queue::assertPushed(SyncSheetFileJob::class, fn (SyncSheetFileJob $job): bool => $job->file->is($activeFile));
    }
});

it('includes sheets:sync in the registered schedule', function (): void {
    $hasSyncCommand = collect(app(Schedule::class)->events())
        ->contains(fn (Event $event): bool => str_contains((string) $event->command, 'sheets:sync'));

    expect($hasSyncCommand)->toBeTrue();
});

it('returns failure when a synchronous file sync fails', function (): void {
    $file = SheetFile::factory()->create(['spreadsheet_id' => 'spreadsheet-command-failure']);
    $tab = SheetTab::factory()->for($file)->create(['tab_name' => 'Orders']);
    $tab->columnMappings()->create([
        'source_column' => 'Order ID',
        'target_table' => 'command_sales',
        'target_column' => 'order_id',
        'column_type' => ColumnType::Integer,
        'is_unique_key' => true,
    ]);
    $this->app->instance(SpreadsheetReader::class, new FakeSpreadsheetReader([
        'spreadsheet-command-failure' => [
            'Orders' => [['Other Header'], ['42']],
        ],
    ]));

    $this->artisan('sheets:sync', ['--file' => $file->id, '--now' => true])
        ->assertExitCode(Command::FAILURE);

    expect($file->syncLogs()->firstOrFail()->status)->toBe(SyncStatus::Failed)
        ->and(SyncLog::query()->where('sheet_file_id', $file->id)->value('error'))->toContain('Order ID');
});
