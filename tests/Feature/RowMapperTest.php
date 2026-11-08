<?php

declare(strict_types=1);

use App\Enums\ColumnType;
use App\Exceptions\MissingSourceColumnException;
use App\Models\ColumnMapping;
use App\Services\Sync\RowMapper;

it('maps shuffled columns using normalized header names', function (): void {
    $rows = [
        [' AGE ', ' First   Name '],
        ['37', 'Ada'],
    ];
    $mappings = collect([
        ColumnMapping::factory()->make([
            'sheet_tab_id' => 1,
            'source_column' => 'first name',
            'target_table' => 'people',
            'target_column' => 'name',
            'column_type' => ColumnType::String,
        ]),
        ColumnMapping::factory()->make([
            'sheet_tab_id' => 1,
            'source_column' => 'Age',
            'target_table' => 'people',
            'target_column' => 'age',
            'column_type' => ColumnType::Integer,
        ]),
    ]);

    expect(app(RowMapper::class)->map($rows, 1, $mappings))
        ->toBe(['people' => [['name' => 'Ada', 'age' => 37]]]);
});

it('ignores extra sheet columns that are not mapped', function (): void {
    $rows = [
        ['Name', 'Unused'],
        ['Ada', 'extra value'],
    ];
    $mappings = collect([
        ColumnMapping::factory()->make([
            'sheet_tab_id' => 1,
            'source_column' => 'Name',
            'target_table' => 'people',
            'target_column' => 'name',
            'column_type' => ColumnType::String,
        ]),
    ]);

    expect(app(RowMapper::class)->map($rows, 1, $mappings))
        ->toBe(['people' => [['name' => 'Ada']]]);
});

it('groups mapped values for multiple target tables', function (): void {
    $rows = [
        ['Email', 'Name'],
        ['ada@example.com', 'Ada'],
    ];
    $mappings = collect([
        ColumnMapping::factory()->make([
            'sheet_tab_id' => 1,
            'source_column' => 'Email',
            'target_table' => 'contacts',
            'target_column' => 'email',
            'column_type' => ColumnType::String,
        ]),
        ColumnMapping::factory()->make([
            'sheet_tab_id' => 1,
            'source_column' => 'Name',
            'target_table' => 'people',
            'target_column' => 'name',
            'column_type' => ColumnType::String,
        ]),
    ]);

    expect(app(RowMapper::class)->map($rows, 1, $mappings))
        ->toBe([
            'contacts' => [['email' => 'ada@example.com']],
            'people' => [['name' => 'Ada']],
        ]);
});

it('casts values, pads short rows, and skips fully empty rows', function (): void {
    $rows = [
        ['Amount', 'Count', 'Enabled', 'Date', 'Occurred At', 'Blank', 'Invalid Date'],
        ['$ 1,234.50', '€ 2,000', 'YES', '2026-03-04', '2026-03-04T05:06:07Z', '', 'not-a-date'],
        ['2.75', '3', 'false'],
        [null, null, 'true'],
        [null, null, '1'],
        [null, null, 'y'],
        [null, null, 'no'],
        ['', '  ', null],
    ];
    $mappings = collect([
        ColumnMapping::factory()->make(['sheet_tab_id' => 1, 'source_column' => 'Amount', 'target_table' => 'records', 'target_column' => 'amount', 'column_type' => ColumnType::Decimal]),
        ColumnMapping::factory()->make(['sheet_tab_id' => 1, 'source_column' => 'Count', 'target_table' => 'records', 'target_column' => 'count', 'column_type' => ColumnType::Integer]),
        ColumnMapping::factory()->make(['sheet_tab_id' => 1, 'source_column' => 'Enabled', 'target_table' => 'records', 'target_column' => 'enabled', 'column_type' => ColumnType::Boolean]),
        ColumnMapping::factory()->make(['sheet_tab_id' => 1, 'source_column' => 'Date', 'target_table' => 'records', 'target_column' => 'date', 'column_type' => ColumnType::Date]),
        ColumnMapping::factory()->make(['sheet_tab_id' => 1, 'source_column' => 'Occurred At', 'target_table' => 'records', 'target_column' => 'occurred_at', 'column_type' => ColumnType::DateTime]),
        ColumnMapping::factory()->make(['sheet_tab_id' => 1, 'source_column' => 'Blank', 'target_table' => 'records', 'target_column' => 'blank', 'column_type' => ColumnType::Text]),
        ColumnMapping::factory()->make(['sheet_tab_id' => 1, 'source_column' => 'Invalid Date', 'target_table' => 'records', 'target_column' => 'invalid_date', 'column_type' => ColumnType::Date]),
    ]);

    $mappedRows = app(RowMapper::class)->map($rows, 1, $mappings);

    expect($mappedRows['records'][0])->toBe([
        'amount' => 1234.5,
        'count' => 2000,
        'enabled' => true,
        'date' => '2026-03-04',
        'occurred_at' => '2026-03-04 05:06:07',
        'blank' => null,
        'invalid_date' => null,
    ])->and($mappedRows['records'][1])->toBe([
        'amount' => 2.75,
        'count' => 3,
        'enabled' => false,
        'date' => null,
        'occurred_at' => null,
        'blank' => null,
        'invalid_date' => null,
    ])->and(array_column($mappedRows['records'], 'enabled'))
        ->toBe([true, false, true, true, true, false]);
});

it('does not truncate fractional integers or coerce unknown booleans to false', function (): void {
    $rows = [
        ['Count', 'Enabled'],
        ['12.7', 'N/A'],
    ];
    $mappings = collect([
        ColumnMapping::factory()->make([
            'sheet_tab_id' => 1,
            'source_column' => 'Count',
            'target_table' => 'records',
            'target_column' => 'count',
            'column_type' => ColumnType::Integer,
        ]),
        ColumnMapping::factory()->make([
            'sheet_tab_id' => 1,
            'source_column' => 'Enabled',
            'target_table' => 'records',
            'target_column' => 'enabled',
            'column_type' => ColumnType::Boolean,
        ]),
    ]);

    expect(app(RowMapper::class)->map($rows, 1, $mappings))
        ->toBe(['records' => [['count' => null, 'enabled' => null]]]);
});

it('parses decimal values using either comma or dot decimal separators', function (): void {
    $rows = [
        ['European amount', 'US amount'],
        ['1.234,56', '$1,234.56'],
    ];
    $mappings = collect([
        ColumnMapping::factory()->make([
            'sheet_tab_id' => 1,
            'source_column' => 'European amount',
            'target_table' => 'records',
            'target_column' => 'european_amount',
            'column_type' => ColumnType::Decimal,
        ]),
        ColumnMapping::factory()->make([
            'sheet_tab_id' => 1,
            'source_column' => 'US amount',
            'target_table' => 'records',
            'target_column' => 'us_amount',
            'column_type' => ColumnType::Decimal,
        ]),
    ]);

    expect(app(RowMapper::class)->map($rows, 1, $mappings))
        ->toBe(['records' => [['european_amount' => 1234.56, 'us_amount' => 1234.56]]]);
});

it('converts Google Sheets date serials to date and datetime values', function (): void {
    $rows = [
        ['Date', 'Occurred At'],
        [46086, 46086.5],
    ];
    $mappings = collect([
        ColumnMapping::factory()->make([
            'sheet_tab_id' => 1,
            'source_column' => 'Date',
            'target_table' => 'records',
            'target_column' => 'date',
            'column_type' => ColumnType::Date,
        ]),
        ColumnMapping::factory()->make([
            'sheet_tab_id' => 1,
            'source_column' => 'Occurred At',
            'target_table' => 'records',
            'target_column' => 'occurred_at',
            'column_type' => ColumnType::DateTime,
        ]),
    ]);

    expect(app(RowMapper::class)->map($rows, 1, $mappings))
        ->toBe(['records' => [['date' => '2026-03-05', 'occurred_at' => '2026-03-05 12:00:00']]]);
});

it('uses formatted cells for string mappings and raw cells for typed mappings', function (): void {
    $rawRows = [
        ['Start Date', 'Discount', 'Parsed Date'],
        [46086, 0.25, 46086],
    ];
    $formattedRows = [
        ['Start Date', 'Discount', 'Parsed Date'],
        ['03/05/2026', '25%', 'Thursday, March 5, 2026'],
    ];
    $mappings = collect([
        ColumnMapping::factory()->make([
            'sheet_tab_id' => 1,
            'source_column' => 'Start Date',
            'target_table' => 'records',
            'target_column' => 'display_date',
            'column_type' => ColumnType::String,
        ]),
        ColumnMapping::factory()->make([
            'sheet_tab_id' => 1,
            'source_column' => 'Discount',
            'target_table' => 'records',
            'target_column' => 'discount_label',
            'column_type' => ColumnType::String,
        ]),
        ColumnMapping::factory()->make([
            'sheet_tab_id' => 1,
            'source_column' => 'Parsed Date',
            'target_table' => 'records',
            'target_column' => 'parsed_date',
            'column_type' => ColumnType::Date,
        ]),
    ]);

    expect(app(RowMapper::class)->map($rawRows, 1, $mappings, $formattedRows))
        ->toBe(['records' => [[
            'display_date' => '03/05/2026',
            'discount_label' => '25%',
            'parsed_date' => '2026-03-05',
        ]]]);
});

it('reports missing source columns and the available headers', function (): void {
    $rows = [
        ['Full Name', 'Email'],
        ['Ada', 'ada@example.com'],
    ];
    $mappings = collect([
        ColumnMapping::factory()->make([
            'sheet_tab_id' => 1,
            'source_column' => 'Age',
            'target_table' => 'people',
            'target_column' => 'age',
            'column_type' => ColumnType::Integer,
        ]),
    ]);

    expect(fn () => app(RowMapper::class)->map($rows, 1, $mappings))
        ->toThrow(MissingSourceColumnException::class, 'Age', 'full name, email');
});
