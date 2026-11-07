<?php

declare(strict_types=1);

use App\Enums\ColumnType;
use App\Exceptions\InvalidMappingException;
use App\Models\ColumnMapping;
use App\Services\Sync\SchemaManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates a sync table with mapped columns and a unique key index', function (): void {
    $emailMapping = (new ColumnMapping)->forceFill([
        'target_column' => 'email',
        'column_type' => ColumnType::String,
        'is_unique_key' => true,
    ]);
    $balanceMapping = (new ColumnMapping)->forceFill([
        'target_column' => 'balance',
        'column_type' => ColumnType::Decimal,
        'is_unique_key' => false,
    ]);

    app(SchemaManager::class)->ensureTable('sync_people', collect([$emailMapping, $balanceMapping]));

    expect(Schema::hasTable('sync_people'))->toBeTrue()
        ->and(Schema::hasColumn('sync_people', 'id'))->toBeTrue()
        ->and(Schema::hasColumn('sync_people', 'sheet_file_id'))->toBeTrue()
        ->and(Schema::hasColumn('sync_people', 'email'))->toBeTrue()
        ->and(Schema::hasColumn('sync_people', 'balance'))->toBeTrue()
        ->and(Schema::hasColumn('sync_people', 'created_at'))->toBeTrue()
        ->and(Schema::hasColumn('sync_people', 'updated_at'))->toBeTrue()
        ->and(Schema::hasIndex('sync_people', ['sheet_file_id']))->toBeTrue()
        ->and(Schema::hasIndex('sync_people', 'sync_people_sync_unique', 'unique'))->toBeTrue();
});

it('adds missing columns as nullable without losing existing data', function (): void {
    Schema::create('sync_existing', function (Blueprint $blueprint): void {
        $blueprint->id();
        $blueprint->foreignId('sheet_file_id')->nullable();
        $blueprint->timestamp('created_at')->nullable();
        $blueprint->timestamp('updated_at')->nullable();
        $blueprint->string('existing_value');
    });
    DB::table('sync_existing')->insert(['existing_value' => 'preserved']);
    $mapping = (new ColumnMapping)->forceFill([
        'target_column' => 'new_value',
        'column_type' => ColumnType::String,
        'is_unique_key' => true,
    ]);

    app(SchemaManager::class)->ensureTable('sync_existing', collect([$mapping]));

    $newColumn = collect(Schema::getColumns('sync_existing'))->firstWhere('name', 'new_value');

    expect(DB::table('sync_existing')->value('existing_value'))->toBe('preserved')
        ->and($newColumn['nullable'])->toBeTrue()
        ->and(Schema::hasColumn('sync_existing', 'sheet_file_id'))->toBeTrue()
        ->and(Schema::hasColumn('sync_existing', 'created_at'))->toBeTrue()
        ->and(Schema::hasColumn('sync_existing', 'updated_at'))->toBeTrue()
        ->and(Schema::hasIndex('sync_existing', 'sync_existing_sync_unique', 'unique'))->toBeTrue();
});

it('rejects a text column as a unique key before changing the table', function (): void {
    Schema::create('sync_text_keys', function (Blueprint $blueprint): void {
        $blueprint->id();
        $blueprint->string('existing_value');
    });
    $mapping = (new ColumnMapping)->forceFill([
        'target_column' => 'description',
        'column_type' => ColumnType::Text,
        'is_unique_key' => true,
    ]);

    expect(fn () => app(SchemaManager::class)->ensureTable('sync_text_keys', collect([$mapping])))
        ->toThrow(
            InvalidMappingException::class,
            'Column [description] of type text cannot be a unique key; use string instead.'
        );

    expect(Schema::hasColumn('sync_text_keys', 'description'))->toBeFalse()
        ->and(Schema::hasColumn('sync_text_keys', 'sheet_file_id'))->toBeFalse();
});

it('removes the sync unique index when mappings no longer have unique keys', function (): void {
    $mapping = (new ColumnMapping)->forceFill([
        'target_column' => 'external_id',
        'column_type' => ColumnType::String,
        'is_unique_key' => true,
    ]);
    $schemaManager = app(SchemaManager::class);

    $schemaManager->ensureTable('sync_reconfigured', collect([$mapping]));

    expect(Schema::hasIndex('sync_reconfigured', 'sync_reconfigured_sync_unique', 'unique'))->toBeTrue();

    $mapping->is_unique_key = false;
    $schemaManager->ensureTable('sync_reconfigured', collect([$mapping]));

    expect(Schema::hasIndex('sync_reconfigured', 'sync_reconfigured_sync_unique', 'unique'))->toBeFalse();
});

it('rejects a mapping type change until the existing column is explicitly migrated', function (): void {
    $mapping = (new ColumnMapping)->forceFill([
        'target_column' => 'external_id',
        'column_type' => ColumnType::String,
        'is_unique_key' => false,
    ]);
    $schemaManager = app(SchemaManager::class);

    $schemaManager->ensureTable('sync_type_change', collect([$mapping]));
    $mapping->column_type = ColumnType::Integer;

    expect(fn () => $schemaManager->ensureTable('sync_type_change', collect([$mapping])))
        ->toThrow(InvalidMappingException::class, 'mapping requests [integer]');

    expect(Schema::getColumnType('sync_type_change', 'external_id'))->toBe('varchar');
});

it('keeps generated unique index names within the database identifier limit', function (): void {
    $table = str_repeat('a', 64);
    $mapping = (new ColumnMapping)->forceFill([
        'target_column' => 'external_id',
        'column_type' => ColumnType::String,
        'is_unique_key' => true,
    ]);

    app(SchemaManager::class)->ensureTable($table, collect([$mapping]));

    $index = collect(Schema::getIndexes($table))->first(fn (array $index): bool => $index['unique']);

    expect($index['name'])->toHaveLength(64);
});

it('rejects composite string keys that exceed the MySQL index size limit', function (): void {
    $mappings = collect(['first_key', 'second_key', 'third_key', 'fourth_key'])
        ->map(fn (string $column): ColumnMapping => (new ColumnMapping)->forceFill([
            'target_column' => $column,
            'column_type' => ColumnType::String,
            'is_unique_key' => true,
        ]));

    app(SchemaManager::class)->ensureTable('sync_large_key', $mappings);
})->throws(InvalidMappingException::class, '3072-byte index limit');

it('rejects protected tables', function (): void {
    app(SchemaManager::class)->ensureTable('users', collect());
})->throws(InvalidMappingException::class);

it('rejects an existing table that is not managed by the sync service', function (): void {
    Schema::create('legacy_imports', function (Blueprint $blueprint): void {
        $blueprint->id();
        $blueprint->string('value');
    });

    app(SchemaManager::class)->ensureTable('legacy_imports', collect());
})->throws(InvalidMappingException::class, 'not a managed sync table');

it('rejects invalid table names', function (): void {
    app(SchemaManager::class)->ensureTable('sync-people', collect());
})->throws(InvalidMappingException::class);

it('rejects invalid or reserved mapped column names', function (string $column): void {
    $mapping = (new ColumnMapping)->forceFill([
        'target_column' => $column,
        'column_type' => ColumnType::String,
        'is_unique_key' => false,
    ]);

    app(SchemaManager::class)->ensureTable('sync_people', collect([$mapping]));
})->with([
    'invalid identifier' => 'BadColumn',
    'id' => 'id',
    'sheet file id' => 'sheet_file_id',
    'created timestamp' => 'created_at',
    'updated timestamp' => 'updated_at',
])->throws(InvalidMappingException::class);
