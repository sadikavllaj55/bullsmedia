<?php

declare(strict_types=1);

namespace App\Contracts;

interface SpreadsheetReader
{
    /**
     * @return array<int, array<int, mixed>>
     */
    public function getTabValues(string $spreadsheetId, string $tab): array;

    /**
     * @return array<int, array<int, mixed>>
     */
    public function getTabFormattedValues(string $spreadsheetId, string $tab): array;
}
