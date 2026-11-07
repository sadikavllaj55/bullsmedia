<?php

declare(strict_types=1);

use App\Filament\Resources\SyncLogs\SyncLogResource;

test('the sync log resource does not register create or edit pages', function (): void {
    expect(array_keys(SyncLogResource::getPages()))->toBe(['index', 'view']);
});
