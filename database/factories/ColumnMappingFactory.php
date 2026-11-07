<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ColumnType;
use App\Models\ColumnMapping;
use App\Models\SheetTab;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColumnMapping>
 */
class ColumnMappingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sheet_tab_id' => SheetTab::factory(),
            'source_column' => fake()->word(),
            'target_table' => fake()->word(),
            'target_column' => fake()->word(),
            'column_type' => ColumnType::String,
            'is_unique_key' => false,
        ];
    }
}
