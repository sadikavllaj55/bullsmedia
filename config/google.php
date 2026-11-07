<?php

declare(strict_types=1);

return [
    'credentials_path' => env('GOOGLE_CREDENTIALS_PATH', storage_path('app/google/credentials.json')),
];
