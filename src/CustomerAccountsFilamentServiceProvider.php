<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament;

use Illuminate\Support\ServiceProvider;

/**
 * The module's provider. It registers no resource, no page and no widget.
 *
 * A panel is composed by the host. Registering the privacy desk from a provider
 * would put it into every panel the application has — including a shopper-facing
 * one, where a screen listing other people's cases is the worst possible
 * accident. Everything here arrives through {@see CustomerAccountsPlugin}, which
 * the host attaches to exactly the panels it means to.
 *
 * The one thing it does register is a view namespace, because the participant
 * registry is a custom page and a custom page needs a view to render its table
 * into. A view namespace is not a surface: nothing is reachable through it until
 * the plugin puts the page on a panel.
 */
class CustomerAccountsFilamentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ecommerce-customer-accounts-filament');
    }
}
