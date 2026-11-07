<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ColumnType;
use Database\Factories\ColumnMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $target_column
 * @property ColumnType $column_type
 * @property bool $is_unique_key
 */
#[Fillable(['sheet_tab_id', 'source_column', 'target_table', 'target_column', 'column_type', 'is_unique_key'])]
class ColumnMapping extends Model
{
    /** @use HasFactory<ColumnMappingFactory> */
    use HasFactory;

    /** @return BelongsTo<SheetTab, $this> */
    public function sheetTab(): BelongsTo
    {
        return $this->belongsTo(SheetTab::class);
    }

    /**
     * @return array<string, string|class-string<ColumnType>>
     */
    protected function casts(): array
    {
        return [
            'column_type' => ColumnType::class,
            'is_unique_key' => 'boolean',
        ];
    }
}
