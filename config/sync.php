<?php

declare(strict_types=1);

return [
    'chunk_size' => env('SYNC_CHUNK_SIZE', 500),
    'daily_at' => env('SYNC_DAILY_AT', '02:00'),
];
