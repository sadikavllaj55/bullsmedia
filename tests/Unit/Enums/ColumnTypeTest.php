<?php

declare(strict_types=1);

use App\Enums\ColumnType;

test('maps each column type to its schema blueprint method', function (ColumnType $columnType, string $blueprintMethod): void {
    expect($columnType->blueprintMethod())->toBe($blueprintMethod);
})->with([
    'string' => [ColumnType::String, 'string'],
    'text' => [ColumnType::Text, 'text'],
    'integer' => [ColumnType::Integer, 'bigInteger'],
    'decimal' => [ColumnType::Decimal, 'decimal'],
    'boolean' => [ColumnType::Boolean, 'boolean'],
    'date' => [ColumnType::Date, 'date'],
    'date time' => [ColumnType::DateTime, 'dateTime'],
]);
