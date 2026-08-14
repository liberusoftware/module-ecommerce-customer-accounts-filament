<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament\Support;

use Closure;
use Filament\Facades\Filament;
use RuntimeException;

/**
 * Where the panel gets its merchant id from.
 *
 * Every query and every action in the domain package takes the tenant as an
 * argument and never reads one from ambient state, so the argument has to come
 * from somewhere at the edge. This is that somewhere: one place, stated out
 * loud, rather than `Filament::getTenant()` sprinkled through three resources
 * where one of them can quietly forget.
 *
 * **There is no fallback to "no tenant", and in this module that matters twice
 * over.** `where('tenant_id', null)` compiles to `is null`, which lists exactly
 * the orphan rows a scope was meant to hide — and every table in the domain
 * carries a NOT NULL `tenant_id` specifically so that a cross-tenant privacy
 * request could never be represented as one. A panel that fell back to null
 * would be searching for a row shape the schema forbids while quietly matching
 * anything a bad migration ever left behind.
 *
 * A case that reaches beyond this merchant is found by its `RequestScope`, never
 * by the absence of a tenant. See `docs/panel.md`.
 */
final class PanelTenant
{
    /**
     * ponytail: one process-global resolver, because one deployment has one way
     * of naming its merchants. If a host ever needs a different rule per panel,
     * move this onto the plugin and read it from the current panel.
     */
    private static ?Closure $resolver = null;

    public static function resolveUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    public static function current(): string
    {
        $tenant = self::$resolver !== null
            ? (self::$resolver)()
            : Filament::getTenant()?->getKey();

        if (! is_string($tenant) && ! is_int($tenant)) {
            throw new RuntimeException(
                'Customer Accounts could not resolve a tenant for this panel. '
                .'Set one with CustomerAccountsPlugin::make()->tenantUsing(...) in the panel provider.'
            );
        }

        $tenant = (string) $tenant;

        if ($tenant === '') {
            throw new RuntimeException('Customer Accounts resolved an empty tenant id, which would match orphan rows.');
        }

        return $tenant;
    }
}
