<?php

declare(strict_types=1);

use App\Contracts\SpreadsheetReader;
use App\Enums\ColumnType;
use App\Enums\SyncStatus;
use App\Exceptions\MissingSourceColumnException;
use App\Exceptions\NothingToSyncException;
use App\Jobs\SyncSheetFileJob;
use App\Models\SheetFile;
use App\Models\SheetTab;
use App\Services\Google\FakeSpreadsheetReader;
use App\Services\Sync\SheetSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('writes a successful sync log and updates last synced time', function (): void {
    $file = SheetFile::factory()->create(['spreadsheet_id' => 'spreadsheet-job-success']);
    $tab = SheetTab::factory()->for($file)->create(['tab_name' => 'Orders']);
    $tab->columnMappings()->create([
        'source_column' => 'Order ID',
        'target_table' => 'job_sales',
        'target_column' => 'order_id',
        'column_type' => ColumnType::Integer,
        'is_unique_key' => true,
    ]);
    $this->app->instance(SpreadsheetReader::class, new FakeSpreadsheetReader([
        'spreadsheet-job-success' => [
            'Orders' => [['Order ID'], ['42']],
        ],
    ]));

    (new SyncSheetFileJob($file))->handle(app(SheetSyncService::class));

    $syncLog = $file->syncLogs()->firstOrFail();
    $file->refresh();

    expect($syncLog->status)->toBe(SyncStatus::Success)
        ->and($syncLog->rows_synced)->toBe(1)
        ->and($syncLog->finished_at)->not->toBeNull()
        ->and($file->last_synced_at)->not->toBeNull();
});

it('writes a failed sync log and error when a source column is missing', function (): void {
    $file = SheetFile::factory()->create(['spreadsheet_id' => 'spreadsheet-job-failure']);
    $tab = SheetTab::factory()->for($file)->create(['tab_name' => 'Orders']);
    $tab->columnMappings()->create([
        'source_column' => 'Order ID',
        'target_table' => 'job_failed_sales',
        'target_column' => 'order_id',
        'column_type' => ColumnType::Integer,
        'is_unique_key' => true,
    ]);
    $this->app->instance(SpreadsheetReader::class, new FakeSpreadsheetReader([
        'spreadsheet-job-failure' => [
            'Orders' => [['Different Header'], ['42']],
        ],
    ]));

    expect(fn () => (new SyncSheetFileJob($file))->handle(app(SheetSyncService::class)))
        ->toThrow(MissingSourceColumnException::class, 'Order ID');

    $syncLog = $file->syncLogs()->firstOrFail();

    expect($syncLog->status)->toBe(SyncStatus::Failed)
        ->and($syncLog->error)->toContain('Order ID')
        ->and($syncLog->finished_at)->not->toBeNull();
});

it('writes a failed sync log when no active tabs have mappings configured', function (): void {
    $file = SheetFile::factory()->create(['spreadsheet_id' => 'spreadsheet-no-mappings']);
    SheetTab::factory()->for($file)->create([
        'tab_name' => 'Empty',
        'is_active' => true,
    ]);
    $inactiveTab = SheetTab::factory()->for($file)->create([
        'tab_name' => 'Inactive',
        'is_active' => false,
    ]);
    $inactiveTab->columnMappings()->create([
        'source_column' => 'Order ID',
        'target_table' => 'sales',
        'target_column' => 'order_id',
        'column_type' => ColumnType::Integer,
        'is_unique_key' => true,
    ]);
    $this->app->instance(SpreadsheetReader::class, new FakeSpreadsheetReader([]));

    expect(fn () => (new SyncSheetFileJob($file))->handle(app(SheetSyncService::class)))
        ->toThrow(NothingToSyncException::class, 'No active tabs with mappings configured');

    $syncLog = $file->syncLogs()->firstOrFail();

    expect($syncLog->status)->toBe(SyncStatus::Failed)
        ->and($syncLog->error)->toBe('No active tabs with mappings configured');
});

it('uses the file id for unique dispatch and configured retries', function (): void {
    $file = SheetFile::factory()->create();
    $job = new SyncSheetFileJob($file);

    expect($job->uniqueId())->toBe((string) $file->id)
        ->and($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([60, 300])
        ->and($job->timeout)->toBe(600);
});

it('marks a running sync log as failed when the queue job permanently fails', function (): void {
    $file = SheetFile::factory()->create();
    $syncLog = $file->syncLogs()->create([
        'status' => SyncStatus::Running,
        'started_at' => now(),
    ]);

    (new SyncSheetFileJob($file))->failed(new RuntimeException('Worker timed out.'));

    expect($syncLog->refresh()->status)->toBe(SyncStatus::Failed)
        ->and($syncLog->error)->toBe('Worker timed out.')
        ->and($syncLog->finished_at)->not->toBeNull();
});
