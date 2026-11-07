<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Contracts\SpreadsheetReader;

final class FakeSpreadsheetReader implements SpreadsheetReader
{
    /**
     * @param  array<string, array<string, array<int, array<int, string|null>>>>  $tabValues
     */
    public function __construct(private readonly array $tabValues = []) {}

    /**
     * @return array<int, array<int, string|null>>
     */
    public function getTabValues(string $spreadsheetId, string $tab): array
    {
        return $this->tabValues[$spreadsheetId][$tab] ?? [];
    }
}
