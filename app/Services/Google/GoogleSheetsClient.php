<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Contracts\SpreadsheetReader;
use Google\Service\Sheets;

final class GoogleSheetsClient implements SpreadsheetReader
{
    private ?Sheets $sheets = null;

    public function __construct(private readonly GoogleSheetsFactory $factory) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function getTabValues(string $spreadsheetId, string $tab): array
    {
        $range = "'".str_replace("'", "''", $tab)."'";
        $sheets = $this->sheets ??= $this->factory->make();

        return $sheets->spreadsheets_values
            ->get($spreadsheetId, $range, [
                'valueRenderOption' => 'UNFORMATTED_VALUE',
                'dateTimeRenderOption' => 'SERIAL_NUMBER',
            ])
            ->getValues() ?? [];
    }
}
