<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Contracts\SpreadsheetReader;

final class FakeSpreadsheetReader implements SpreadsheetReader
{
    /**
     * @param  array<string, array<string, array<int, array<int, mixed>>>>  $tabValues
     * @param  array<string, array<string, array<int, array<int, mixed>>>>  $formattedTabValues
     */
    public function __construct(
        private readonly array $tabValues = [],
        private readonly array $formattedTabValues = [],
    ) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function getTabValues(string $spreadsheetId, string $tab): array
    {
        return $this->tabValues[$spreadsheetId][$tab] ?? [];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function getTabFormattedValues(string $spreadsheetId, string $tab): array
    {
        return $this->formattedTabValues[$spreadsheetId][$tab]
            ?? $this->getTabValues($spreadsheetId, $tab);
    }
}
