<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Contracts\SpreadsheetReader;
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

            foreach ($mappings as $mapping) {
                $targetTable = $mapping->target_table;
                $targetColumn = $mapping->target_column;
                $currentMapping = $mappingsByTable[$targetTable][$targetColumn] ?? null;

                if ($currentMapping === null || $mapping->is_unique_key) {
                    $mappingsByTable[$targetTable][$targetColumn] = $mapping;
                }
            }

            $mappedRows = $this->rowMapper->map(
                $this->spreadsheetReader->getTabValues($file->spreadsheet_id, $tab->tab_name),
                $tab->header_row,
                $mappings,
            );

            foreach ($mappedRows as $table => $tableRows) {
                $rowsByTable[$table] = array_merge($rowsByTable[$table] ?? [], $tableRows);
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

            $mappedColumns = $tableMappings->pluck('target_column')->all();
            $uniqueKeys = $tableMappings
                ->filter(fn (ColumnMapping $mapping): bool => $mapping->is_unique_key)
                ->pluck('target_column')
                ->all();
            $normalizedRows = [];

            foreach ($tableRows as $tableRow) {
                $row = [];

                foreach ($mappedColumns as $column) {
                    $row[$column] = $tableRow[$column] ?? null;
                }

                $row['sheet_file_id'] = $file->getKey();
                $row['created_at'] = $timestamp;
                $row['updated_at'] = $timestamp;
                $normalizedRows[] = $row;
            }

            $writePlans[$table] = [
                'rows' => $normalizedRows,
                'mapped_columns' => $mappedColumns,
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
                $updateColumns = [...$writePlan['mapped_columns'], 'updated_at'];

                foreach (array_chunk($writePlan['rows'], $chunkSize) as $chunk) {
                    DB::table($table)->upsert($chunk, $conflictColumns, $updateColumns);
                    $rowsWritten += count($chunk);
                }
            }
        });

        return $rowsWritten;
    }
}
