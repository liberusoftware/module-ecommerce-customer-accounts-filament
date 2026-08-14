<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Liberu\Ecommerce\CustomerAccounts\Filament\Pages\ParticipantRegistry;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\ClaimAttempts\ClaimAttemptResource;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\PrivacyRequests\PrivacyRequestResource;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\SavedLists\SavedListResource;
use Liberu\Ecommerce\CustomerAccounts\Filament\Support\PanelTenant;

/**
 * The privacy desk, as the host attaches it.
 *
 * Everything in this package is an operator surface. The shopper's side of this
 * module — claiming a guest order, keeping a list, opening a case and watching
 * it progress honestly — is the `-livewire` package's, and a host with a
 * shopper-facing panel attaches this plugin to the merchant one and not to that.
 *
 *     use Filament\Facades\Filament;
 *     use Liberu\Ecommerce\CustomerAccounts\Filament\CustomerAccountsPlugin;
 *
 *     $panel->plugin(
 *         CustomerAccountsPlugin::make()
 *             ->tenantUsing(fn (): string => (string) Filament::getTenant()?->getKey()),
 *     );
 */
final class CustomerAccountsPlugin implements Plugin
{
    public static function make(): self
    {
        return new self();
    }

    public function getId(): string
    {
        return 'ecommerce-customer-accounts';
    }

    /**
     * How this panel names the merchant whose desk it is.
     *
     * Optional: without it the panel's own Filament tenant is used. A host whose
     * tenancy is not Filament's says so here rather than leaving three resources
     * to guess separately.
     */
    public function tenantUsing(?Closure $resolver): self
    {
        PanelTenant::resolveUsing($resolver);

        return $this;
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            PrivacyRequestResource::class,
            ClaimAttemptResource::class,
            SavedListResource::class,
        ]);

        $panel->pages([
            ParticipantRegistry::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
