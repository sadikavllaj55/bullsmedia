<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\SpreadsheetReader;
use App\Services\Google\GoogleSheetsClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SpreadsheetReader::class, GoogleSheetsClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
