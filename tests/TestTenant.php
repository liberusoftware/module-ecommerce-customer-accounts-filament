<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament\Tests;

/**
 * The merchant the test panel is currently showing.
 *
 * A mutable holder rather than a constant, because the custody proof is about
 * two merchants and the interesting failures are the ones a single-merchant test
 * passes either way. Order references are unique only within a merchant, and two
 * merchants both numbering an order 1001 is the ordinary case.
 */
final class TestTenant
{
    public const PRIMARY = 'tenant-a';

    public const OTHER = 'tenant-b';

    private static string $current = self::PRIMARY;

    public static function current(): string
    {
        return self::$current;
    }

    public static function use(string $tenantId): void
    {
        self::$current = $tenantId;
    }

    public static function reset(): void
    {
        self::$current = self::PRIMARY;
    }
}
