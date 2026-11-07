<?php

declare(strict_types=1);

use App\Exceptions\MissingGoogleCredentialsException;
use App\Services\Google\GoogleSheetsClient;
use App\Services\Google\GoogleSheetsFactory;
use Tests\TestCase;

uses(TestCase::class);

it('throws a setup error when the credentials file is missing', function (): void {
    $credentialsPath = 'storage/app/google/missing-test-credentials.json';
    config()->set('google.credentials_path', $credentialsPath);

    expect(fn () => (new GoogleSheetsFactory)->make())
        ->toThrow(
            MissingGoogleCredentialsException::class,
            'Google credentials not found at ['.base_path($credentialsPath).']. See README → Google API setup.'
        );
});

it('loads the Sheets service only when the first tab read is requested', function (): void {
    config()->set('google.credentials_path', 'storage/app/google/missing-test-credentials.json');
    $reader = new GoogleSheetsClient(new GoogleSheetsFactory);

    expect($reader)->toBeInstanceOf(GoogleSheetsClient::class);
    expect(fn () => $reader->getTabValues('spreadsheet-id', 'People'))
        ->toThrow(MissingGoogleCredentialsException::class);
});
