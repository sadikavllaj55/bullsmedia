<?php

declare(strict_types=1);

namespace App\Support;

final class SyncIdentifier
{
    private const IDENTIFIER_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';

    private const PROTECTED_TABLES = [
        'users',
        'migrations',
        'jobs',
        'failed_jobs',
        'cache',
        'sessions',
        'password_reset_tokens',
        'sheet_files',
        'sheet_tabs',
        'column_mappings',
        'sync_logs',
    ];

    public static function isValid(string $identifier): bool
    {
        return preg_match(self::IDENTIFIER_PATTERN, $identifier) === 1;
    }

    public static function isProtected(string $identifier): bool
    {
        return in_array($identifier, self::PROTECTED_TABLES, true);
    }
}
