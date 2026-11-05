# Ecommerce Customer Accounts — Filament panel

[![Tests](https://github.com/liberusoftware/module-ecommerce-customer-accounts-filament/actions/workflows/tests.yml/badge.svg)](https://github.com/liberusoftware/module-ecommerce-customer-accounts-filament/actions/workflows/tests.yml)

The privacy desk over
[`liberusoftware/ecommerce-customer-accounts`](https://github.com/liberusoftware/module-ecommerce-customer-accounts):
privacy requests with their reach, deadline and **every participant's answer
including the ones that answered nothing**; the participant registry showing
which modules are registered and which of them are silent; failed guest-order
claim attempts; and saved lists, read-only.

Everything here is an operator surface. The shopper's side — claiming a guest
order, keeping a list, opening a case and watching it progress honestly — is the
`-livewire` package's.

## What this panel refuses to say

The module exists because the host answered an erasure request with
`{"success": true}` from a function that ran inside an HTTP request, wrote ten
tables belonging to six modules, and had never heard of the others. There was no
case, no participant, no record of who asked, and nobody to quote a number at
when it half-failed.

So this panel never renders an unanswered question as a finished one:

| The state | What you see | What you never see |
| --- | --- | --- |
| Three of five participants completed | **3 of 5 answered**, the other two named | a tick |
| A participant with nothing bound to it | **Unavailable** — "nothing is bound to answer for it" | the participant omitted from the case |
| A module that publishes an erasure and no export | **Unavailable** — "Publishes no access." | an export that looks whole |
| A case concluded with anyone outstanding | **Completed in part**, naming them | **Completed** |
| A registry with nobody in it | **every case opened will be unsatisfiable** | an empty table |
| A deadline that has passed | **Past its deadline**, and nothing acts on it | a promise that something will |

Those are the differences a person's rights are made of. "Your data has been
erased" is a claim about all of it.

## Attaching it

The package ships **no** `extra.laravel.providers` and its service provider
registers no surface. A panel is composed by the host, so everything arrives
through a plugin attached to exactly the panels it means to:

```php
use Filament\Facades\Filament;
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

**Do not attach this to a shopper-facing panel.**

`tenantUsing()` is optional and defaults to the panel's own Filament tenant. An
unresolvable or empty tenant throws rather than returning null, because
`where('tenant_id', null)` compiles to `is null` and lists exactly the orphan
rows a scope was meant to hide.

See [`docs/adoption.md`](docs/adoption.md) for the Composer entries and — the
step that actually matters — registering your participants.
[`docs/panel.md`](docs/panel.md) is what is on each screen and why;
[`docs/runbook.md`](docs/runbook.md) is how to work a case.

## What is in the panel

Three resources, one page and one widget, all under **Privacy desk**:

- **Privacy requests** — the cases, with `ParticipantsRelationManager` on each
- **Participant registry** — who takes part, and which of them is silent
- **Claim attempts** — the failures, which are the security signal
- **Saved lists** — with items and share links, read-only
- **Desk overview** — cases running, cases late, cases partial, silent participants

## Constraints worth knowing before you read the code

- **A cross-tenant case is not a null tenant.** `tenant_id` is NOT NULL on every
  table and means where the case was raised; reach is `RequestScope`. The panel
  filters on scope and never on an absent merchant.
- **Nothing sensitive is searchable or filterable.** Search terms and filter
  state both persist into the query string. The case reference is searchable
  because it is our own case number; the subject reference, the order reference
  and the email fingerprint are not, and the fingerprint is not even a column.
- **No claim token and no share token is ever rendered or logged.** Each exists
  readable exactly once, at minting, and is stored only as a fingerprint.
- **This module is not a participant of its own registry** and is never drawn as
  one.
- **Nothing schedules anything**, nothing auto-concludes, and there is no
  cooling-off period. All three would need a clock this module does not have.
- **Nothing here writes into another module's tables.** A retry re-commissions
  our own case through the one contract this package publishes.
- **Every unpublished Filament ability is forced false by name**, per resource
  and per relation manager, `canAssociate` and `canDissociate` included. A
  missing policy is permissive, and a policy missing the method asked about is
  *also* permissive.

## Requirements

- PHP `^8.5`
- `filament/filament` `^5.6`
- `liberusoftware/ecommerce-customer-accounts` `^0.1`

**`^5.6` is a choice, not a requirement.** Filament's true Laravel-13 floor is
`^5.4`: reading each tag's own `packages/support/composer.json`, `v5.3.0` caps at
`illuminate/contracts: ^11.28|^12.0` and `v5.4.0` is the first to add `^13.0`,
which `filament/filament` inherits through `self.version`. We pin higher because
that is the range this package's suite is exercised against, not because anything
below it is known to break.

## Related Liberu Projects

| Project | Repository | Purpose |
| --- | --- | --- |
| **Boilerplate** | [liberusoftware/boilerplate-laravel](https://github.com/liberusoftware/project-boilerplate-laravel-filament) | Shared Laravel application foundation and reference composition |
| **CMS** | [liberu-cms/cms-laravel](https://github.com/liberu-cms/cms-laravel) | Structured content, publishing, media, multisite, and headless delivery |
| **CRM** | [liberu-crm/crm-laravel](https://github.com/liberu-crm/crm-laravel) | Customer data, sales, marketing, service, and customer success |
| **Billing** | [liberu-billing/billing-laravel](https://github.com/liberu-billing/billing-laravel) | Products, subscriptions, invoicing, payments, and provisioning |
| **Accounting** | [liberu-accounting/accounting-laravel](https://github.com/liberu-accounting/accounting-laravel) | Ledgers, banking, tax, expenses, close, and financial reporting |
| **Ecommerce** | [liberu-ecommerce/ecommerce-laravel](https://github.com/liberu-ecommerce/ecommerce-laravel) | Catalog, checkout, orders, fulfillment, returns, B2B, and omnichannel commerce |
| **Control Panel** | [liberu-control-panel/control-panel-laravel](https://github.com/liberu-control-panel/control-panel-laravel) | Hosting, infrastructure, DNS, mail, databases, backups, and security operations |
| **Automation** | [liberu-automation/automation-laravel](https://github.com/liberu-automation/automation-laravel) | Governed workflows, provider-neutral AI, approvals, and connectors |

## Security

Please do not report security vulnerabilities through public GitHub issues.
Follow our [Security Policy](https://github.com/liberusoftware/documentation/blob/main/architecture/SECURITY.md) for private reporting and supported versions.

## License

This project is open-source software. You may use, modify, and distribute it
under the terms described in [LICENSE.md](LICENSE.md).

The linked license text is authoritative; this summary is not legal advice.

## Feedback and contributing

Feedback and contributions are welcome. You can help by reporting reproducible
bugs, proposing focused enhancements, improving documentation or translations,
and submitting tested code changes.

Before contributing, please read [CONTRIBUTING.md](https://github.com/liberusoftware/documentation/blob/main/standards/CONTRIBUTING.md) and our
[Code of Conduct](https://github.com/liberusoftware/documentation/blob/main/architecture/CODE_OF_CONDUCT.md). Search existing issues first, then use
the appropriate issue template. Pull requests should explain the problem and
approach, remain focused, include or update tests, pass the required workflows,
and document user-visible or breaking changes.

## Contributors

Thank you to everyone who helps improve Liberu.

<a href="https://github.com/liberusoftware/module-ecommerce-customer-accounts-filament/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=liberusoftware/module-ecommerce-customer-accounts-filament" alt="Contributors to liberusoftware/module-ecommerce-customer-accounts-filament">
</a>

[View the full contributors graph](https://github.com/liberusoftware/module-ecommerce-customer-accounts-filament/graphs/contributors).

<!-- module-presentation-guide:begin -->
## Presentation architecture and project hosts

- Capability/source key: `planned:ecommerce:customer-accounts`.
- Actual Composer identity: `liberusoftware/ecommerce-customer-accounts-filament`.
- [Filament capability plan](https://github.com/liberusoftware/documentation/blob/main/projects/ecommerce/filament/customer-accounts.md) · [Filament standard](https://github.com/liberusoftware/documentation/blob/main/standards/FILAMENT.md) · [Module architecture](https://github.com/liberusoftware/documentation/blob/main/architecture/MODULES.md).

The application owns panel composition, routing, session integration and deployment. This module owns its focused presentation and delegates domain behavior to reviewed public owner contracts. Keep host App aliases, duplicate records and client-only authorization outside reusable package boundaries.

Documented consumer project families:

- [Ecommerce](https://github.com/liberusoftware/project-ecommerce-laravel-filament): Catalogs, checkout, orders and fulfillment.

Other web, mobile and desktop presentations are listed in the [project catalog](https://github.com/liberusoftware/documentation/blob/main/projects/REPOSITORIES.md). Those skeletons provide starting structures; links do not establish feature parity, compatible releases or completed device/API integrations. Independent presentation packages keep their own Composer, npm or pub metadata.
<!-- module-presentation-guide:end -->
