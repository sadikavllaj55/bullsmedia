<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sheets:sync')
    ->dailyAt(config('sync.daily_at'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('model:prune')->daily();
