<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Contracts\SpreadsheetReader;
use App\Exceptions\InvalidMappingException;
use App\Exceptions\NothingToSyncException;
use App\Models\ColumnMapping;
use App\Models\SheetFile;
use Illuminate\Support\Facades\DB;

class SheetSyncService
{
    public function __construct(
        private readonly SpreadsheetReader $spreadsheetReader,
        private readonly RowMapper $rowMapper,
        private readonly SchemaManager $schemaManager,
    ) {}

    public function sync(SheetFile $file): int
    {
        $rowsByTable = [];
        $mappingsByTable = [];
        $uniqueKeysByTable = [];
        $hasActiveMappings = false;
        $tabs = $file->sheetTabs()
            ->where('is_active', true)
            ->with('columnMappings')
            ->get();

        foreach ($tabs as $tab) {
            $mappings = $tab->columnMappings;

            if ($mappings->isEmpty()) {
                continue;
            }

            $hasActiveMappings = true;

            $mappedRows = $this->rowMapper->map(
                $this->spreadsheetReader->getTabValues($file->spreadsheet_id, $tab->tab_name),
                $tab->header_row,
                $mappings,
            );

            foreach ($mappedRows as $table => $tableRows) {
                $tableMappings = $mappings->where('target_table', $table)->values();
                $mappedColumns = $tableMappings->pluck('target_column')->unique()->values()->all();
                $uniqueKeys = $tableMappings
                    ->filter(fn (ColumnMapping $mapping): bool => $mapping->is_unique_key)
                    ->pluck('target_column')
                    ->unique()
                    ->values()
                    ->all();
                $normalizedUniqueKeys = $uniqueKeys;
                sort($normalizedUniqueKeys);

                if (array_key_exists($table, $uniqueKeysByTable)
                    && $uniqueKeysByTable[$table] !== $normalizedUniqueKeys) {
                    throw new InvalidMappingException(
                        "All active tabs writing to table [{$table}] must use the same unique key columns."
                    );
                }

                $uniqueKeysByTable[$table] = $normalizedUniqueKeys;

                foreach ($tableMappings as $mapping) {
                    $targetColumn = $mapping->target_column;
                    $currentMapping = $mappingsByTable[$table][$targetColumn] ?? null;

                    if ($currentMapping === null || $mapping->is_unique_key) {
                        $mappingsByTable[$table][$targetColumn] = $mapping;
                    }
                }

                $rowsByTable[$table][] = [
                    'rows' => $tableRows,
                    'mapped_columns' => $mappedColumns,
                ];
            }
        }

        if (! $hasActiveMappings) {
            throw new NothingToSyncException('No active tabs with mappings configured');
        }

        if ($rowsByTable === []) {
            return 0;
        }

        $timestamp = now();
        $writePlans = [];

        foreach ($rowsByTable as $table => $tableRows) {
            $tableMappings = collect(array_values($mappingsByTable[$table]));
            $this->schemaManager->ensureTable($table, $tableMappings);

            $uniqueKeys = $tableMappings
                ->filter(fn (ColumnMapping $mapping): bool => $mapping->is_unique_key)
                ->pluck('target_column')
                ->unique()
                ->values()
                ->all();
            $normalizedRows = [];
            $upsertGroups = [];

            foreach ($tableRows as $tableGroup) {
                $groupRows = [];

                foreach ($tableGroup['rows'] as $rowIndex => $tableRow) {
                    if ($uniqueKeys !== []) {
                        foreach ($uniqueKeys as $uniqueKey) {
                            if (($tableRow[$uniqueKey] ?? null) === null) {
                                $sheetRow = $rowIndex + 1;

                                throw new InvalidMappingException(
                                    "Row [{$sheetRow}] in table [{$table}] has an empty or invalid unique key [{$uniqueKey}]."
                                );
                            }
                        }
                    }

                    $row = [];
                    $columns = $uniqueKeys === []
                        ? $tableMappings->pluck('target_column')->all()
                        : $tableGroup['mapped_columns'];

                    foreach ($columns as $column) {
                        $row[$column] = $tableRow[$column] ?? null;
                    }

                    $row['sheet_file_id'] = $file->getKey();
                    $row['created_at'] = $timestamp;
                    $row['updated_at'] = $timestamp;

                    if ($uniqueKeys === []) {
                        $normalizedRows[] = $row;
                    } else {
                        $groupRows[] = $row;
                    }
                }

                if ($uniqueKeys !== []) {
                    $upsertGroups[] = [
                        'rows' => $groupRows,
                        'mapped_columns' => array_values(array_diff($tableGroup['mapped_columns'], $uniqueKeys)),
                    ];
                }
            }

            $writePlans[$table] = [
                'rows' => $normalizedRows,
                'upsert_groups' => $upsertGroups,
                'unique_keys' => $uniqueKeys,
            ];
        }

        $chunkSize = max(1, (int) config('sync.chunk_size', 500));
        $rowsWritten = 0;

        DB::transaction(function () use ($writePlans, $file, $chunkSize, &$rowsWritten): void {
            foreach ($writePlans as $table => $writePlan) {
                if ($writePlan['unique_keys'] === []) {
                    DB::table($table)
                        ->where('sheet_file_id', $file->getKey())
                        ->delete();

                    foreach (array_chunk($writePlan['rows'], $chunkSize) as $chunk) {
                        DB::table($table)->insert($chunk);
                        $rowsWritten += count($chunk);
                    }

                    continue;
                }

                $conflictColumns = [...$writePlan['unique_keys'], 'sheet_file_id'];
                foreach ($writePlan['upsert_groups'] as $upsertGroup) {
                    $updateColumns = [...$upsertGroup['mapped_columns'], 'updated_at'];

                    foreach (array_chunk($upsertGroup['rows'], $chunkSize) as $chunk) {
                        DB::table($table)->upsert($chunk, $conflictColumns, $updateColumns);
                        $rowsWritten += count($chunk);
                    }
                }
            }
        });

        return $rowsWritten;
    }
}
