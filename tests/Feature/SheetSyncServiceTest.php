<?php

declare(strict_types=1);

use App\Contracts\SpreadsheetReader;
use App\Enums\ColumnType;
use App\Exceptions\InvalidMappingException;
use App\Models\SheetFile;
use App\Models\SheetTab;
use App\Services\Google\FakeSpreadsheetReader;
use App\Services\Sync\SheetSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates a sales table and writes renamed columns on the first sync', function (): void {
    $file = SheetFile::factory()->create(['spreadsheet_id' => 'spreadsheet-sales']);
    $tab = SheetTab::factory()->for($file)->create(['tab_name' => 'Orders']);
    $tab->columnMappings()->createMany([
        ['source_column' => 'Order ID', 'target_table' => 'sales', 'target_column' => 'order_id', 'column_type' => ColumnType::Integer, 'is_unique_key' => true],
        ['source_column' => 'Buyer', 'target_table' => 'sales', 'target_column' => 'customer_name', 'column_type' => ColumnType::String, 'is_unique_key' => false],
        ['source_column' => 'Total', 'target_table' => 'sales', 'target_column' => 'total', 'column_type' => ColumnType::Decimal, 'is_unique_key' => false],
    ]);
    SheetTab::factory()->for($file)->create(['tab_name' => 'No mappings']);
    $inactiveTab = SheetTab::factory()->for($file)->create(['tab_name' => 'Inactive', 'is_active' => false]);
    $inactiveTab->columnMappings()->create([
        'source_column' => 'Unavailable',
        'target_table' => 'sales',
        'target_column' => 'unavailable',
        'column_type' => ColumnType::String,
        'is_unique_key' => false,
    ]);
    $this->app->instance(SpreadsheetReader::class, new FakeSpreadsheetReader([
        'spreadsheet-sales' => [
            'Orders' => [
                ['Order ID', 'Buyer', 'Total'],
                ['1001', 'Ada', '$ 20.50'],
            ],
        ],
    ]));

    $rowsWritten = app(SheetSyncService::class)->sync($file);
    $row = DB::table('sales')->first();

    expect($rowsWritten)->toBe(1)
        ->and(Schema::hasColumn('sales', 'customer_name'))->toBeTrue()
        ->and((int) $row->order_id)->toBe(1001)
        ->and($row->customer_name)->toBe('Ada')
        ->and((float) $row->total)->toBe(20.5)
        ->and((int) $row->sheet_file_id)->toBe($file->id)
        ->and($row->created_at)->not->toBeNull()
        ->and($row->updated_at)->not->toBeNull();
});

it('preserves displayed dates and percentages for string mappings during sync', function (): void {
    $file = SheetFile::factory()->create(['spreadsheet_id' => 'spreadsheet-formatted-strings']);
    $tab = SheetTab::factory()->for($file)->create(['tab_name' => 'Metrics']);
    $tab->columnMappings()->createMany([
        ['source_column' => 'Date', 'target_table' => 'metrics', 'target_column' => 'date_label', 'column_type' => ColumnType::String, 'is_unique_key' => false],
        ['source_column' => 'Discount', 'target_table' => 'metrics', 'target_column' => 'discount_label', 'column_type' => ColumnType::String, 'is_unique_key' => false],
        ['source_column' => 'Date', 'target_table' => 'metrics', 'target_column' => 'date_value', 'column_type' => ColumnType::Date, 'is_unique_key' => false],
    ]);
    $this->app->instance(SpreadsheetReader::class, new FakeSpreadsheetReader(
        [
            'spreadsheet-formatted-strings' => [
                'Metrics' => [['Date', 'Discount'], [46086, 0.25]],
            ],
        ],
        [
            'spreadsheet-formatted-strings' => [
                'Metrics' => [['Date', 'Discount'], ['03/05/2026', '25%']],
            ],
        ],
    ));

    expect(app(SheetSyncService::class)->sync($file))->toBe(1)
        ->and(DB::table('metrics')->value('date_label'))->toBe('03/05/2026')
        ->and(DB::table('metrics')->value('discount_label'))->toBe('25%')
        ->and(DB::table('metrics')->value('date_value'))->toBe('2026-03-05');
});

it('upserts changed values without duplicating and scopes keys to the sheet file', function (): void {
    $firstFile = SheetFile::factory()->create(['spreadsheet_id' => 'spreadsheet-first']);
    $secondFile = SheetFile::factory()->create(['spreadsheet_id' => 'spreadsheet-second']);
    $firstTab = SheetTab::factory()->for($firstFile)->create(['tab_name' => 'Orders']);
    $secondTab = SheetTab::factory()->for($secondFile)->create(['tab_name' => 'Orders']);

    foreach ([$firstTab, $secondTab] as $tab) {
        $tab->columnMappings()->createMany([
            ['source_column' => 'Order ID', 'target_table' => 'sales', 'target_column' => 'order_id', 'column_type' => ColumnType::Integer, 'is_unique_key' => true],
            ['source_column' => 'Total', 'target_table' => 'sales', 'target_column' => 'total', 'column_type' => ColumnType::Decimal, 'is_unique_key' => false],
        ]);
    }

    $this->app->instance(SpreadsheetReader::class, new FakeSpreadsheetReader([
        'spreadsheet-first' => ['Orders' => [['Order ID', 'Total'], ['7', '10.00']]],
        'spreadsheet-second' => ['Orders' => [['Order ID', 'Total'], ['7', '30.00']]],
    ]));
    app(SheetSyncService::class)->sync($firstFile);

    $this->app->instance(SpreadsheetReader::class, new FakeSpreadsheetReader([
        'spreadsheet-first' => ['Orders' => [['Order ID', 'Total'], ['7', '25.00']]],
        'spreadsheet-second' => ['Orders' => [['Order ID', 'Total'], ['7', '30.00']]],
    ]));
    $updatedCount = app(SheetSyncService::class)->sync($firstFile);
    $secondCount = app(SheetSyncService::class)->sync($secondFile);
    $rows = DB::table('sales')->orderBy('sheet_file_id')->get();

    expect($updatedCount)->toBe(1)
        ->and($secondCount)->toBe(1)
        ->and($rows)->toHaveCount(2)
        ->and((float) $rows[0]->total)->toBe(25.0)
        ->and((float) $rows[1]->total)->toBe(30.0)
        ->and((int) $rows[0]->sheet_file_id)->toBe($firstFile->id)
        ->and((int) $rows[1]->sheet_file_id)->toBe($secondFile->id);
});

it('maps shuffled headers and ignores extra columns during sync', function (): void {
    $file = SheetFile::factory()->create(['spreadsheet_id' => 'spreadsheet-shuffled']);
    $tab = SheetTab::factory()->for($file)->create(['tab_name' => 'Orders']);
    $tab->columnMappings()->createMany([
        ['source_column' => 'Order ID', 'target_table' => 'sales', 'target_column' => 'order_id', 'column_type' => ColumnType::Integer, 'is_unique_key' => true],
        ['source_column' => 'Buyer', 'target_table' => 'sales', 'target_column' => 'customer_name', 'column_type' => ColumnType::String, 'is_unique_key' => false],
    ]);
    $this->app->instance(SpreadsheetReader::class, new FakeSpreadsheetReader([
        'spreadsheet-shuffled' => [
            'Orders' => [
                ['Notes', ' Buyer ', 'Order   ID'],
                ['ignore me', 'Ada', '55'],
            ],
        ],
    ]));

    expect(app(SheetSyncService::class)->sync($file))->toBe(1)
        ->and(DB::table('sales')->first()->customer_name)->toBe('Ada')
        ->and((int) DB::table('sales')->first()->order_id)->toBe(55)
        ->and(Schema::hasColumn('sales', 'notes'))->toBeFalse();
});

it('writes one tab to multiple target tables', function (): void {
    $file = SheetFile::factory()->create(['spreadsheet_id' => 'spreadsheet-multiple-tables']);
    $tab = SheetTab::factory()->for($file)->create(['tab_name' => 'Orders']);
    $tab->columnMappings()->createMany([
        ['source_column' => 'Order ID', 'target_table' => 'sales', 'target_column' => 'order_id', 'column_type' => ColumnType::Integer, 'is_unique_key' => true],
        ['source_column' => 'Buyer', 'target_table' => 'customers', 'target_column' => 'name', 'column_type' => ColumnType::String, 'is_unique_key' => false],
        ['source_column' => 'Email', 'target_table' => 'customers', 'target_column' => 'email', 'column_type' => ColumnType::String, 'is_unique_key' => true],
    ]);
    $this->app->instance(SpreadsheetReader::class, new FakeSpreadsheetReader([
        'spreadsheet-multiple-tables' => [
            'Orders' => [['Order ID', 'Buyer', 'Email'], ['22', 'Grace', 'grace@example.com']],
        ],
    ]));

    expect(app(SheetSyncService::class)->sync($file))->toBe(2)
        ->and(DB::table('sales')->value('order_id'))->toBe(22)
        ->and(DB::table('customers')->value('name'))->toBe('Grace')
        ->and(DB::table('customers')->value('email'))->toBe('grace@example.com');
});

it('merges mapped columns from multiple tabs sharing a unique key', function (): void {
    $file = SheetFile::factory()->create(['spreadsheet_id' => 'spreadsheet-two-tabs']);
    $northTab = SheetTab::factory()->for($file)->create(['tab_name' => 'North']);
    $southTab = SheetTab::factory()->for($file)->create(['tab_name' => 'South']);

    $northTab->columnMappings()->createMany([
        ['source_column' => 'Order ID', 'target_table' => 'sales', 'target_column' => 'order_id', 'column_type' => ColumnType::Integer, 'is_unique_key' => true],
        ['source_column' => 'North Amount', 'target_table' => 'sales', 'target_column' => 'north_amount', 'column_type' => ColumnType::Decimal, 'is_unique_key' => false],
    ]);
    $southTab->columnMappings()->createMany([
        ['source_column' => 'Order ID', 'target_table' => 'sales', 'target_column' => 'order_id', 'column_type' => ColumnType::Integer, 'is_unique_key' => true],
        ['source_column' => 'South Amount', 'target_table' => 'sales', 'target_column' => 'south_amount', 'column_type' => ColumnType::Decimal, 'is_unique_key' => false],
    ]);
    $this->app->instance(SpreadsheetReader::class, new FakeSpreadsheetReader([
        'spreadsheet-two-tabs' => [
            'North' => [['Order ID', 'North Amount'], ['1', '10.00']],
            'South' => [['Order ID', 'South Amount'], ['1', '20.00']],
        ],
    ]));

    expect(app(SheetSyncService::class)->sync($file))->toBe(2)
        ->and(DB::table('sales')->count())->toBe(1)
        ->and((float) DB::table('sales')->value('north_amount'))->toBe(10.0)
        ->and((float) DB::table('sales')->value('south_amount'))->toBe(20.0);
});

it('rejects rows with an empty or invalid unique key before writing', function (): void {
    $file = SheetFile::factory()->create(['spreadsheet_id' => 'spreadsheet-invalid-key']);
    $tab = SheetTab::factory()->for($file)->create(['tab_name' => 'Orders']);
    $tab->columnMappings()->create([
        'source_column' => 'Order ID',
        'target_table' => 'sales',
        'target_column' => 'order_id',
        'column_type' => ColumnType::Integer,
        'is_unique_key' => true,
    ]);
    $this->app->instance(SpreadsheetReader::class, new FakeSpreadsheetReader([
        'spreadsheet-invalid-key' => ['Orders' => [['Order ID'], ['A-17']]],
    ]));

    expect(fn () => app(SheetSyncService::class)->sync($file))
        ->toThrow(InvalidMappingException::class, 'empty or invalid unique key [order_id]');

    expect(DB::table('sales')->count())->toBe(0);
});

it('rejects tabs that use different unique keys for the same table', function (): void {
    $file = SheetFile::factory()->create(['spreadsheet_id' => 'spreadsheet-mixed-keys']);
    $keyedTab = SheetTab::factory()->for($file)->create(['tab_name' => 'Keyed']);
    $unkeyedTab = SheetTab::factory()->for($file)->create(['tab_name' => 'Unkeyed']);
    $keyedTab->columnMappings()->create([
        'source_column' => 'Order ID',
        'target_table' => 'sales',
        'target_column' => 'order_id',
        'column_type' => ColumnType::Integer,
        'is_unique_key' => true,
    ]);
    $unkeyedTab->columnMappings()->create([
        'source_column' => 'Buyer',
        'target_table' => 'sales',
        'target_column' => 'buyer',
        'column_type' => ColumnType::String,
        'is_unique_key' => false,
    ]);
    $this->app->instance(SpreadsheetReader::class, new FakeSpreadsheetReader([
        'spreadsheet-mixed-keys' => [
            'Keyed' => [['Order ID'], ['1']],
            'Unkeyed' => [['Buyer'], ['Ada']],
        ],
    ]));

    expect(fn () => app(SheetSyncService::class)->sync($file))
        ->toThrow(InvalidMappingException::class, 'must use the same unique key columns');
});

it('adds a newly mapped column and fills it on the next sync', function (): void {
    $file = SheetFile::factory()->create(['spreadsheet_id' => 'spreadsheet-new-column']);
    $tab = SheetTab::factory()->for($file)->create(['tab_name' => 'Orders']);
    $tab->columnMappings()->createMany([
        ['source_column' => 'Order ID', 'target_table' => 'sales', 'target_column' => 'order_id', 'column_type' => ColumnType::Integer, 'is_unique_key' => true],
        ['source_column' => 'Product', 'target_table' => 'sales', 'target_column' => 'product', 'column_type' => ColumnType::String, 'is_unique_key' => false],
    ]);
    $this->app->instance(SpreadsheetReader::class, new FakeSpreadsheetReader([
        'spreadsheet-new-column' => ['Orders' => [['Order ID', 'Product'], ['8', 'Book']]],
    ]));
    app(SheetSyncService::class)->sync($file);

    $tab->columnMappings()->create([
        'source_column' => 'Quantity',
        'target_table' => 'sales',
        'target_column' => 'quantity',
        'column_type' => ColumnType::Integer,
        'is_unique_key' => false,
    ]);
    $this->app->instance(SpreadsheetReader::class, new FakeSpreadsheetReader([
        'spreadsheet-new-column' => ['Orders' => [['Order ID', 'Product', 'Quantity'], ['8', 'Book', '3']]],
    ]));

    expect(app(SheetSyncService::class)->sync($file))->toBe(1)
        ->and(Schema::hasColumn('sales', 'quantity'))->toBeTrue()
        ->and(DB::table('sales')->count())->toBe(1)
        ->and(DB::table('sales')->value('quantity'))->toBe(3);
});

it('replaces only this file’s rows when no mappings are unique keys', function (): void {
    $firstFile = SheetFile::factory()->create(['spreadsheet_id' => 'spreadsheet-refresh-first']);
    $secondFile = SheetFile::factory()->create(['spreadsheet_id' => 'spreadsheet-refresh-second']);
    $firstTab = SheetTab::factory()->for($firstFile)->create(['tab_name' => 'Rows']);
    $secondTab = SheetTab::factory()->for($secondFile)->create(['tab_name' => 'Rows']);

    foreach ([$firstTab, $secondTab] as $tab) {
        $tab->columnMappings()->createMany([
            ['source_column' => 'Code', 'target_table' => 'imports', 'target_column' => 'code', 'column_type' => ColumnType::String, 'is_unique_key' => false],
            ['source_column' => 'Value', 'target_table' => 'imports', 'target_column' => 'value', 'column_type' => ColumnType::String, 'is_unique_key' => false],
        ]);
    }
    $this->app->instance(SpreadsheetReader::class, new FakeSpreadsheetReader([
        'spreadsheet-refresh-first' => ['Rows' => [['Code', 'Value'], ['A', 'old-a'], ['B', 'old-b']]],
        'spreadsheet-refresh-second' => ['Rows' => [['Code', 'Value'], ['X', 'other-file']]],
    ]));
    app(SheetSyncService::class)->sync($firstFile);
    app(SheetSyncService::class)->sync($secondFile);

    $this->app->instance(SpreadsheetReader::class, new FakeSpreadsheetReader([
        'spreadsheet-refresh-first' => ['Rows' => [['Code', 'Value'], ['B', 'new-b']]],
        'spreadsheet-refresh-second' => ['Rows' => [['Code', 'Value'], ['X', 'other-file']]],
    ]));
    $rowsWritten = app(SheetSyncService::class)->sync($firstFile);
    $rows = DB::table('imports')->orderBy('sheet_file_id')->orderBy('code')->get();

    expect($rowsWritten)->toBe(1)
        ->and($rows)->toHaveCount(2)
        ->and($rows[0]->code)->toBe('B')
        ->and($rows[0]->value)->toBe('new-b')
        ->and($rows[1]->code)->toBe('X')
        ->and($rows[1]->value)->toBe('other-file')
        ->and((int) $rows[1]->sheet_file_id)->toBe($secondFile->id);
});
