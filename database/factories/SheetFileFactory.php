<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SheetFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SheetFile>
 */
class SheetFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'spreadsheet_id' => fake()->unique()->bothify('spreadsheet-########'),
            'is_active' => true,
            'last_synced_at' => null,
        ];
    }
}
