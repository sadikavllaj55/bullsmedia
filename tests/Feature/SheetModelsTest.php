<?php

declare(strict_types=1);

use App\Enums\ColumnType;
use App\Enums\SyncStatus;
use App\Models\ColumnMapping;
use App\Models\SheetFile;
use App\Models\SheetTab;
use App\Models\SyncLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('casts enum attributes and resolves the sheet relationships', function (): void {
    $sheetFile = SheetFile::factory()->create();
    $sheetTab = SheetTab::factory()->for($sheetFile)->create();
    $columnMapping = ColumnMapping::factory()->for($sheetTab)->create([
        'column_type' => ColumnType::Integer,
    ]);
    $syncLog = SyncLog::factory()->for($sheetFile)->create([
        'status' => SyncStatus::Success,
    ]);

    expect($columnMapping->column_type)->toBe(ColumnType::Integer)
        ->and($syncLog->status)->toBe(SyncStatus::Success)
        ->and($sheetFile->sheetTabs)->toHaveCount(1)
        ->and($sheetFile->syncLogs)->toHaveCount(1)
        ->and($sheetTab->sheetFile->is($sheetFile))->toBeTrue()
        ->and($sheetTab->columnMappings)->toHaveCount(1)
        ->and($columnMapping->sheetTab->is($sheetTab))->toBeTrue()
        ->and($syncLog->sheetFile->is($sheetFile))->toBeTrue();
});

it('cascades sheet file deletion to tabs, mappings, and sync logs', function (): void {
    $sheetFile = SheetFile::factory()
        ->has(SheetTab::factory()->has(ColumnMapping::factory()))
        ->has(SyncLog::factory())
        ->create();
    $sheetTab = $sheetFile->sheetTabs()->firstOrFail();
    $columnMapping = $sheetTab->columnMappings()->firstOrFail();
    $syncLog = $sheetFile->syncLogs()->firstOrFail();

    $sheetFile->delete();

    $this->assertDatabaseMissing('sheet_tabs', ['id' => $sheetTab->id]);
    $this->assertDatabaseMissing('column_mappings', ['id' => $columnMapping->id]);
    $this->assertDatabaseMissing('sync_logs', ['id' => $syncLog->id]);
});
