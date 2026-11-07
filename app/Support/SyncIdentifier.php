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
        'job_batches',
        'failed_jobs',
        'cache',
        'cache_locks',
        'sessions',
        'password_reset_tokens',
        'personal_access_tokens',
        'sheet_files',
        'sheet_tabs',
        'column_mappings',
        'sync_logs',
    ];

    private const RESERVED_COLUMNS = [
        'id',
        'sheet_file_id',
        'created_at',
        'updated_at',
    ];

    public static function isValid(string $identifier): bool
    {
        return preg_match(self::IDENTIFIER_PATTERN, $identifier) === 1;
    }

    public static function isProtected(string $identifier): bool
    {
        return in_array($identifier, self::PROTECTED_TABLES, true);
    }

    public static function isReservedColumn(string $identifier): bool
    {
        return in_array($identifier, self::RESERVED_COLUMNS, true);
    }
}
