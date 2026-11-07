<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SheetFile;
use App\Models\SheetTab;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SheetTab>
 */
class SheetTabFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sheet_file_id' => SheetFile::factory(),
            'tab_name' => fake()->unique()->word(),
            'header_row' => 1,
            'is_active' => true,
        ];
    }
}
