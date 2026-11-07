<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Exceptions\InvalidMappingException;
use App\Models\ColumnMapping;
use App\Support\SyncIdentifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SchemaManager
{
    /**
     * @param  Collection<int, ColumnMapping>  $mappings
     */
    public function ensureTable(string $table, Collection $mappings): void
    {
        $this->validateTableName($table);
        $this->validateColumnNames($mappings);
        $this->validateUniqueKeyTypes($mappings);

        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $blueprint) use ($mappings): void {
                $blueprint->id();
                $blueprint->foreignId('sheet_file_id')->nullable()->index();

                foreach ($mappings as $mapping) {
                    $this->addMappedColumn($blueprint, $mapping, true);
                }

                $blueprint->timestamps();
            });

            Log::info('Created sync table.', ['table' => $table]);

            foreach ($mappings as $mapping) {
                Log::info('Added sync table column.', [
                    'table' => $table,
                    'column' => $mapping->target_column,
                ]);
            }
        } else {
            $this->ensureSystemColumns($table);

            foreach ($mappings as $mapping) {
                $column = $mapping->target_column;

                if (Schema::hasColumn($table, $column)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($mapping): void {
                    $this->addMappedColumn($blueprint, $mapping, true);
                });

                Log::info('Added sync table column.', [
                    'table' => $table,
                    'column' => $column,
                ]);
            }
        }

        $this->ensureUniqueIndex($table, $mappings);
    }

    private function ensureSystemColumns(string $table): void
    {
        $missingSheetFileId = ! Schema::hasColumn($table, 'sheet_file_id');
        $missingCreatedAt = ! Schema::hasColumn($table, 'created_at');
        $missingUpdatedAt = ! Schema::hasColumn($table, 'updated_at');

        if (! $missingSheetFileId && ! $missingCreatedAt && ! $missingUpdatedAt) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($missingSheetFileId, $missingCreatedAt, $missingUpdatedAt): void {
            if ($missingSheetFileId) {
                $blueprint->foreignId('sheet_file_id')->nullable()->index();
            }

            if ($missingCreatedAt) {
                $blueprint->timestamp('created_at')->nullable();
            }

            if ($missingUpdatedAt) {
                $blueprint->timestamp('updated_at')->nullable();
            }
        });

        foreach (['sheet_file_id' => $missingSheetFileId, 'created_at' => $missingCreatedAt, 'updated_at' => $missingUpdatedAt] as $column => $missing) {
            if ($missing) {
                Log::info('Added sync table column.', ['table' => $table, 'column' => $column]);
            }
        }
    }

    private function validateTableName(string $table): void
    {
        if (! SyncIdentifier::isValid($table)) {
            throw new InvalidMappingException("Invalid sync table name [{$table}].");
        }

        if (SyncIdentifier::isProtected($table)) {
            throw new InvalidMappingException("Sync table [{$table}] is protected.");
        }
    }

    /**
     * @param  Collection<int, ColumnMapping>  $mappings
     */
    private function validateColumnNames(Collection $mappings): void
    {
        foreach ($mappings as $mapping) {
            if (! SyncIdentifier::isValid($mapping->target_column)) {
                throw new InvalidMappingException("Invalid sync column name [{$mapping->target_column}].");
            }
        }
    }

    /**
     * @param  Collection<int, ColumnMapping>  $mappings
     */
    private function validateUniqueKeyTypes(Collection $mappings): void
    {
        foreach ($mappings as $mapping) {
            if ($mapping->is_unique_key && ! $mapping->column_type->canBeUniqueKey()) {
                throw new InvalidMappingException(
                    "Column [{$mapping->target_column}] of type {$mapping->column_type->value} cannot be a unique key; use string instead."
                );
            }
        }
    }

    private function addMappedColumn(Blueprint $blueprint, ColumnMapping $mapping, bool $nullable): void
    {
        $columnName = $mapping->target_column;
        $blueprintMethod = $mapping->column_type->blueprintMethod();

        $column = match ($blueprintMethod) {
            'string' => $blueprint->string($columnName),
            'text' => $blueprint->text($columnName),
            'bigInteger' => $blueprint->bigInteger($columnName),
            'decimal' => $blueprint->decimal($columnName, 15, 4),
            'boolean' => $blueprint->boolean($columnName),
            'date' => $blueprint->date($columnName),
            'dateTime' => $blueprint->dateTime($columnName),
            default => throw new InvalidMappingException("Unsupported sync column type [{$blueprintMethod}]."),
        };

        if ($nullable) {
            $column->nullable();
        }
    }

    /**
     * @param  Collection<int, ColumnMapping>  $mappings
     */
    private function ensureUniqueIndex(string $table, Collection $mappings): void
    {
        $uniqueColumns = [];

        foreach ($mappings as $mapping) {
            if ($mapping->is_unique_key && ! in_array($mapping->target_column, $uniqueColumns, true)) {
                $uniqueColumns[] = $mapping->target_column;
            }
        }

        $indexName = "{$table}_sync_unique";
        $existingIndex = collect(Schema::getIndexes($table))->firstWhere('name', $indexName);

        if ($uniqueColumns === []) {
            if ($existingIndex !== null) {
                Schema::table($table, function (Blueprint $blueprint) use ($existingIndex): void {
                    if ($existingIndex['unique']) {
                        $blueprint->dropUnique($existingIndex['name']);
                    } else {
                        $blueprint->dropIndex($existingIndex['name']);
                    }
                });
            }

            return;
        }

        $uniqueColumns[] = 'sheet_file_id';

        foreach ($uniqueColumns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        if ($existingIndex !== null
            && $existingIndex['unique']
            && $existingIndex['columns'] === $uniqueColumns) {
            return;
        }

        if ($existingIndex !== null) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
                $blueprint->dropUnique($indexName);
            });
        }

        Schema::table($table, function (Blueprint $blueprint) use ($uniqueColumns, $indexName): void {
            $blueprint->unique($uniqueColumns, $indexName);
        });
    }
}
