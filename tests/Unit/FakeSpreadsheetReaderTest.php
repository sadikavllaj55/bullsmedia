<?php

declare(strict_types=1);

use App\Services\Google\FakeSpreadsheetReader;

it('returns predefined values for the requested spreadsheet tab', function (): void {
    $rows = [['Name', 'Email'], ['Ada', null]];
    $reader = new FakeSpreadsheetReader([
        'spreadsheet-id' => [
            'People' => $rows,
        ],
    ]);

    expect($reader->getTabValues('spreadsheet-id', 'People'))->toBe($rows)
        ->and($reader->getTabValues('spreadsheet-id', 'Missing'))->toBe([]);
});
