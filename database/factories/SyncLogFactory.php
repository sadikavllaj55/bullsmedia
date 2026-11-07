<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SyncStatus;
use App\Models\SheetFile;
use App\Models\SyncLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncLog>
 */
class SyncLogFactory extends Factory
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
            'status' => SyncStatus::Pending,
            'rows_synced' => 0,
            'error' => null,
            'started_at' => now(),
            'finished_at' => null,
        ];
    }
}
