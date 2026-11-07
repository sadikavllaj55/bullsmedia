<?php

declare(strict_types=1);

namespace App\Enums;

enum ColumnType: string
{
    case String = 'string';
    case Text = 'text';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Date = 'date';
    case DateTime = 'datetime';

    public function canBeUniqueKey(): bool
    {
        return $this !== self::Text;
    }

    public function blueprintMethod(): string
    {
        return match ($this) {
            self::String => 'string',
            self::Text => 'text',
            self::Integer => 'bigInteger',
            self::Decimal => 'decimal',
            self::Boolean => 'boolean',
            self::Date => 'date',
            self::DateTime => 'dateTime',
        };
    }
}
