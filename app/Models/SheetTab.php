<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SheetTabFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sheet_file_id', 'tab_name', 'header_row', 'is_active'])]
class SheetTab extends Model
{
    /** @use HasFactory<SheetTabFactory> */
    use HasFactory;

    /** @return BelongsTo<SheetFile, $this> */
    public function sheetFile(): BelongsTo
    {
        return $this->belongsTo(SheetFile::class);
    }

    /** @return HasMany<ColumnMapping, $this> */
    public function columnMappings(): HasMany
    {
        return $this->hasMany(ColumnMapping::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'header_row' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
