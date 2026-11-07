<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Enums\ColumnType;
use App\Exceptions\MissingSourceColumnException;
use App\Models\ColumnMapping;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class RowMapper
{
    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  Collection<int, ColumnMapping>  $mappings
     * @return array<string, list<array<string, mixed>>>
     */
    public function map(array $rows, int $headerRow, Collection $mappings): array
    {
        $headerIndex = $headerRow - 1;
        $headers = $headerRow > 0 ? array_values($rows[$headerIndex] ?? []) : [];
        $headerPositions = [];

        foreach ($headers as $index => $header) {
            $normalizedHeader = $this->normalizeHeader($header);
            $headerPositions[$normalizedHeader] ??= $index;
        }

        $availableHeaders = array_map(fn (mixed $header): string => $this->normalizeHeader($header), $headers);
        $missingColumns = [];
        $mappingsByTable = [];

        foreach ($mappings as $mapping) {
            $normalizedSourceColumn = $this->normalizeHeader($mapping->source_column);

            if (! array_key_exists($normalizedSourceColumn, $headerPositions)
                && ! in_array($mapping->source_column, $missingColumns, true)) {
                $missingColumns[] = $mapping->source_column;
            }

            $mappingsByTable[$mapping->target_table][] = $mapping;
        }

        if ($missingColumns !== []) {
            throw new MissingSourceColumnException(
                'Missing source columns ['.implode(', ', $missingColumns).']. Available headers ['.implode(', ', $availableHeaders).'].'
            );
        }

        $mappedRows = array_fill_keys(array_keys($mappingsByTable), []);

        foreach (array_slice($rows, $headerRow) as $row) {
            $row = array_values($row);

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $row = array_pad($row, count($headers), null);

            foreach ($mappingsByTable as $table => $tableMappings) {
                $mappedRow = [];

                foreach ($tableMappings as $mapping) {
                    $sourceColumn = $this->normalizeHeader($mapping->source_column);
                    $sourcePosition = $headerPositions[$sourceColumn];

                    $mappedRow[$mapping->target_column] = $this->castValue(
                        $mapping->column_type,
                        $row[$sourcePosition] ?? null
                    );
                }

                $mappedRows[$table][] = $mappedRow;
            }
        }

        return $mappedRows;
    }

    private function normalizeHeader(mixed $header): string
    {
        if (! is_scalar($header)) {
            return '';
        }

        $lowercaseHeader = mb_strtolower(trim((string) $header));

        return preg_replace('/\s+/u', ' ', $lowercaseHeader) ?? $lowercaseHeader;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && (! is_string($value) || trim($value) !== '')) {
                return false;
            }
        }

        return true;
    }

    private function castValue(ColumnType $type, mixed $value): mixed
    {
        if ($value === null || (is_string($value) && trim($value) === '') || ! is_scalar($value)) {
            return null;
        }

        $stringValue = (string) $value;

        return match ($type) {
            ColumnType::String, ColumnType::Text => $stringValue,
            ColumnType::Integer => $this->castNumber($stringValue, true),
            ColumnType::Decimal => $this->castNumber($stringValue, false),
            ColumnType::Boolean => $this->castBoolean($value, $stringValue),
            ColumnType::Date => $this->castDate($stringValue, 'Y-m-d'),
            ColumnType::DateTime => $this->castDate($stringValue, 'Y-m-d H:i:s'),
        };
    }

    private function castNumber(string $value, bool $integer): int|float|null
    {
        $normalizedValue = preg_replace('/[\s\p{Z}\p{Sc},]/u', '', trim($value));

        if ($normalizedValue === null || ! is_numeric($normalizedValue)) {
            return null;
        }

        return $integer ? (int) $normalizedValue : (float) $normalizedValue;
    }

    private function castBoolean(mixed $value, string $stringValue): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(mb_strtolower(trim($stringValue)), ['true', 'yes', '1', 'y'], true);
    }

    private function castDate(string $value, string $format): ?string
    {
        try {
            return Carbon::parse($value)->format($format);
        } catch (Throwable) {
            return null;
        }
    }
}
