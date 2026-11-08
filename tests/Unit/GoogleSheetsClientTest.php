<?php

declare(strict_types=1);

use App\Services\Google\GoogleSheetsClient;
use App\Services\Google\GoogleSheetsFactory;
use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\Resource\SpreadsheetsValues;
use Google\Service\Sheets\ValueRange;

it('quotes a tab name and returns the fetched values', function (): void {
    $values = Mockery::mock(SpreadsheetsValues::class);
    $response = Mockery::mock(ValueRange::class);

    $values->shouldReceive('get')
        ->once()
        ->with('spreadsheet-id', "'Quarter''s data'", [
            'valueRenderOption' => 'UNFORMATTED_VALUE',
            'dateTimeRenderOption' => 'SERIAL_NUMBER',
        ])
        ->andReturn($response);
    $response->shouldReceive('getValues')
        ->once()
        ->andReturn([['Name'], ['Ada']]);

    $sheets = new Sheets(new Client);
    $sheets->spreadsheets_values = $values;

    $reader = new GoogleSheetsClient(new GoogleSheetsFactory);
    (new ReflectionProperty($reader, 'sheets'))->setValue($reader, $sheets);

    expect($reader->getTabValues('spreadsheet-id', "Quarter's data"))
        ->toBe([['Name'], ['Ada']]);
});

it('returns an empty array when the tab has no values', function (): void {
    $values = Mockery::mock(SpreadsheetsValues::class);
    $response = Mockery::mock(ValueRange::class);

    $values->shouldReceive('get')
        ->once()
        ->with('spreadsheet-id', "'Empty tab'", [
            'valueRenderOption' => 'UNFORMATTED_VALUE',
            'dateTimeRenderOption' => 'SERIAL_NUMBER',
        ])
        ->andReturn($response);
    $response->shouldReceive('getValues')
        ->once()
        ->andReturn(null);

    $sheets = new Sheets(new Client);
    $sheets->spreadsheets_values = $values;

    $reader = new GoogleSheetsClient(new GoogleSheetsFactory);
    (new ReflectionProperty($reader, 'sheets'))->setValue($reader, $sheets);

    expect($reader->getTabValues('spreadsheet-id', 'Empty tab'))->toBe([]);
});

it('returns display-formatted values for string mappings', function (): void {
    $values = Mockery::mock(SpreadsheetsValues::class);
    $response = Mockery::mock(ValueRange::class);

    $values->shouldReceive('get')
        ->once()
        ->with('spreadsheet-id', "'Dates and rates'", [
            'valueRenderOption' => 'FORMATTED_VALUE',
        ])
        ->andReturn($response);
    $response->shouldReceive('getValues')
        ->once()
        ->andReturn([['Date', 'Rate'], ['03/05/2026', '25%']]);

    $sheets = new Sheets(new Client);
    $sheets->spreadsheets_values = $values;

    $reader = new GoogleSheetsClient(new GoogleSheetsFactory);
    (new ReflectionProperty($reader, 'sheets'))->setValue($reader, $sheets);

    expect($reader->getTabFormattedValues('spreadsheet-id', 'Dates and rates'))
        ->toBe([['Date', 'Rate'], ['03/05/2026', '25%']]);
});
