# Adopting the privacy desk

## 1. Composer

Neither this package nor the domain package is on Packagist, so the host's own
`composer.json` needs both VCS entries. Composer honours `repositories` **only
from the root manifest** — the entry in this package's manifest works for its own
CI, where it is root, and does nothing for you.

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-customer-accounts" },
    { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-customer-accounts-filament" }
  ]
}
```

```bash
composer require liberusoftware/ecommerce-customer-accounts-filament:^0.1
```

Installing boots nothing. The package ships no `extra.laravel.providers`; the
host's `ModuleManagerServiceProvider` globs `config('modules.paths')` for
`*/module.json` and registers only the modules named in `MODULES_ENABLED`:

```dotenv
MODULES_ENABLED=ecommerce-customer-accounts,ecommerce-customer-accounts-filament
```

## 2. Attach the plugin to the panels you mean

```php
use Filament\Facades\Filament;
use Filament\Panel;
use Liberu\Ecommerce\CustomerAccounts\Filament\CustomerAccountsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin')
        ->plugin(
            CustomerAccountsPlugin::make()
                ->tenantUsing(fn (): string => (string) Filament::getTenant()?->getKey()),
        );
}
```

Everything here is an operator surface. **Do not attach this to a shopper-facing
panel** — a screen listing other people's cases is the worst accident this
package could have. The shopper's side is the `-livewire` package.

`tenantUsing()` is optional and defaults to the panel's own Filament tenant. It
exists because every query and action in the domain takes its merchant as an
argument and never reads one from ambient state. An unresolvable or empty tenant
**throws**: `where('tenant_id', null)` compiles to `is null` and lists exactly
the orphan rows a scope was meant to hide.

## 3. Register your participants — this is the install step that matters

The panel is only as honest as this list. Publish the domain's config and fill it
in:

```bash
php artisan vendor:publish --tag=customer-accounts-config
```

```php
// config/customer-accounts.php
'participants' => [
    'commerce-customers' => [
        'label'   => 'Customer files',
        'adapter' => \App\Privacy\CommerceCustomersParticipant::class,
        'handles' => ['access', 'erasure'],
    ],
    'promotions' => [
        'label'   => 'Offers and redemptions',
        'adapter' => \App\Privacy\PromotionsParticipant::class,
        // Promotions publishes an erasure and no export. Say so: it is then a
        // full participant of an access request that answers "unavailable" with
        // a stated reason, and the export is honestly partial.
        'handles' => ['erasure'],
    ],
    'orders' => [
        'label'   => 'Orders',
        // Registered with nothing bound yet. Visible on the registry screen as
        // silent, which is the whole point — an unregistered module is not a
        // gap, it is an absence nobody can see.
        'adapter' => null,
        'handles' => ['access', 'erasure'],
    ],
],
```

Then open **Privacy desk → Participant registry** and read it. Anything marked
`Silent` will make every case it is on completed in part.

**Customer Accounts is not a participant of its own registry.** Do not add it.
Its own rows are cleared directly on every erasure conclusion, partial ones
included.

## 4. What this package does not do

- **It does not schedule anything.** There is no deadline sweeper, nothing
  auto-concludes and no queue is watching. Commissioning a participant is a
  standalone idempotent action; which queue and what backoff are yours. If you
  want cases progressed without an operator pressing a button, dispatch
  `CommissionParticipant` from your own job.
- **It does not send mail.** The claim token and the case's progress notices are
  the host's to deliver; the domain publishes the lifecycle events.
- **It does not download an export.** The panel shows whether an assembled export
  is whole and who contributed nothing. The artefact is delivered to the subject
  through the `-api` package.
- **It does not offer a cooling-off window.** None exists.
- **It does not build saved carts.** A saved *list* is this module's; a saved
  cart is a cart in a state and belongs to Cart. Gift registries stay in the
  host — a registry is a shared, third-party-purchasable list with shipping and
  an access code, which is a different aggregate, and no epic owns it yet.

## 5. Version notes

`filament/filament: ^5.6` is a **choice**, not a requirement. Reading each tag's
own `packages/support/composer.json`: `v5.3.0` caps at
`illuminate/contracts: ^11.28|^12.0` and `v5.4.0` is the first to add `^13.0`,
which `filament/filament` inherits through `self.version`. `^5.4` is the true
Laravel-13 floor; we pin higher because `^5.6` is the range this package's suite
is exercised against.
