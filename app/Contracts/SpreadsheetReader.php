<?php

declare(strict_types=1);

namespace App\Contracts;

interface SpreadsheetReader
{
    /**
     * @return array<int, array<int, string|null>>
     */
    public function getTabValues(string $spreadsheetId, string $tab): array;
}
