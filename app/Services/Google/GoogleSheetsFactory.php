<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Exceptions\MissingGoogleCredentialsException;
use Google\Client;
use Google\Service\Sheets;

final class GoogleSheetsFactory
{
    public function make(): Sheets
    {
        $path = (string) config('google.credentials_path');

        if (! str_starts_with($path, '/')
            && ! str_starts_with($path, '\\')
            && preg_match('/^[A-Za-z]:[\/\\\\]/', $path) !== 1) {
            $path = base_path($path);
        }

        if (! is_file($path)) {
            throw new MissingGoogleCredentialsException($path);
        }

        $client = new Client;
        $client->setApplicationName((string) config('app.name'));
        $client->setScopes(Sheets::SPREADSHEETS_READONLY);
        $client->setAuthConfig($path);

        return new Sheets($client);
    }
}
