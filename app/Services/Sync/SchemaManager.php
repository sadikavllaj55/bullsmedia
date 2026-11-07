<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Enums\ColumnType;
use App\Exceptions\InvalidMappingException;
use App\Models\ColumnMapping;
use App\Support\SyncIdentifier;
use Illuminate\Database\QueryException;
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
        $this->validateColumnNames($mappings);
        $this->validateUniqueKeyTypes($mappings);
        $this->validateTableName($table);
        $this->validateExistingColumnTypes($table, $mappings);

        if (! $this->tableExists($table)) {
            try {
                Schema::create($table, function (Blueprint $blueprint) use ($mappings): void {
                    $blueprint->id();
                    $blueprint->foreignId('sheet_file_id')->nullable()->index();

                    foreach ($mappings as $mapping) {
                        $this->addMappedColumn($blueprint, $mapping, true);
                    }

                    $blueprint->timestamps();
                });
            } catch (QueryException $exception) {
                if (! $this->tableExists($table)) {
                    throw $exception;
                }

                $this->ensureTable($table, $mappings);

                return;
            }

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

        if (Schema::hasTable($table)
            && ! collect(['id', 'sheet_file_id', 'created_at', 'updated_at'])
                ->every(fn (string $column): bool => Schema::hasColumn($table, $column))) {
            throw new InvalidMappingException("Existing table [{$table}] is not a managed sync table.");
        }
    }

    /**
     * @param  Collection<int, ColumnMapping>  $mappings
     */
    private function validateColumnNames(Collection $mappings): void
    {
        foreach ($mappings as $mapping) {
            if (! SyncIdentifier::isValid($mapping->target_column)
                || SyncIdentifier::isReservedColumn($mapping->target_column)) {
                throw new InvalidMappingException("Invalid sync column name [{$mapping->target_column}].");
            }
        }
    }

    /**
     * @param  Collection<int, ColumnMapping>  $mappings
     */
    private function validateUniqueKeyTypes(Collection $mappings): void
    {
        $indexBytes = 8;

        foreach ($mappings as $mapping) {
            if ($mapping->is_unique_key && ! $mapping->column_type->canBeUniqueKey()) {
                throw new InvalidMappingException(
                    "Column [{$mapping->target_column}] of type {$mapping->column_type->value} cannot be a unique key; use string instead."
                );
            }

            if ($mapping->is_unique_key) {
                $indexBytes += match ($mapping->column_type) {
                    ColumnType::String => 1020,
                    ColumnType::Integer, ColumnType::DateTime => 8,
                    ColumnType::Decimal => 9,
                    ColumnType::Boolean => 1,
                    ColumnType::Date => 3,
                    ColumnType::Text => 3073,
                };
            }
        }

        if ($indexBytes > 3072) {
            throw new InvalidMappingException("The configured unique key exceeds MySQL's 3072-byte index limit.");
        }
    }

    /**
     * @param  Collection<int, ColumnMapping>  $mappings
     */
    private function validateExistingColumnTypes(string $table, Collection $mappings): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($mappings as $mapping) {
            if (! Schema::hasColumn($table, $mapping->target_column)) {
                continue;
            }

            $actualType = strtolower(Schema::getColumnType($table, $mapping->target_column));
            $supportedTypes = match ($mapping->column_type) {
                ColumnType::String => ['string', 'varchar', 'character varying'],
                ColumnType::Text => ['text', 'mediumtext', 'longtext'],
                ColumnType::Integer => ['bigint', 'integer'],
                ColumnType::Decimal => ['decimal', 'numeric', 'real', 'float', 'double'],
                ColumnType::Boolean => ['boolean', 'bool', 'tinyint', 'integer'],
                ColumnType::Date => ['date'],
                ColumnType::DateTime => ['datetime', 'timestamp'],
            };

            if (! in_array($actualType, $supportedTypes, true)) {
                throw new InvalidMappingException(
                    "Mapped column [{$mapping->target_column}] in table [{$table}] is [{$actualType}], but the mapping requests [{$mapping->column_type->value}]. Change the mapping back or migrate the column explicitly."
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

        $indexName = $this->uniqueIndexName($table);
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

    private function uniqueIndexName(string $table): string
    {
        $suffix = '_sync_unique';

        if (strlen($table.$suffix) <= 64) {
            return $table.$suffix;
        }

        return substr($table, 0, 43).'_'.substr(sha1($table), 0, 8).$suffix;
    }

    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }
}
