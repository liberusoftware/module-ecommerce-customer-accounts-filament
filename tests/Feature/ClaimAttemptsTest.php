<?php

declare(strict_types=1);

use Filament\Tables\Table;
use Liberu\Ecommerce\CustomerAccounts\Enums\ClaimAttemptOutcome;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\ClaimAttempts\ClaimAttemptResource;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\ClaimAttempts\Pages\ListClaimAttempts;
use Liberu\Ecommerce\CustomerAccounts\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CustomerAccounts\Models\ClaimAttempt;
use Liberu\Ecommerce\CustomerAccounts\Support\Fingerprint;
use Livewire\Livewire;

it('lists the failures, because the failures are the security signal', function (): void {
    foreach (range(1, 4) as $ignored) {
        recordAttempt('ord-1001', ClaimAttemptOutcome::Refused);
    }

    recordAttempt('ord-1001', ClaimAttemptOutcome::RateLimited);

    Livewire::test(ListClaimAttempts::class)
        ->assertCanSeeTableRecords(ClaimAttempt::query()->get())
        ->assertSee('ord-1001')
        ->assertSee('Refused')
        ->assertSee('Rate limited');
});

it('defaults to failures and can be widened to every attempt', function (): void {
    $refused = recordAttempt('ord-1001', ClaimAttemptOutcome::Refused);
    $granted = recordAttempt('ord-2002', ClaimAttemptOutcome::Granted, claimReference: 'clm-1');

    Livewire::test(ListClaimAttempts::class)
        ->assertCanSeeTableRecords(ClaimAttempt::query()->whereKey($refused->getKey())->get())
        ->assertCanNotSeeTableRecords(ClaimAttempt::query()->whereKey($granted->getKey())->get())
        ->filterTable('failures', false)
        ->assertCanSeeTableRecords(ClaimAttempt::query()->get());
});

it('never renders an email fingerprint, a claim token or anything derived from one', function (): void {
    $attempt = recordAttempt('ord-1001', ClaimAttemptOutcome::Refused, email: 'victim@example.test');

    Livewire::test(ListClaimAttempts::class)
        // The fingerprint of somebody's address does not stop being about them
        // because it is a hash.
        ->assertDontSee($attempt->email_fingerprint)
        ->assertDontSee(Fingerprint::of('victim@example.test'))
        ->assertDontSee('victim@example.test');

    $names = array_map(
        fn (object $column): string => (string) $column->getName(),
        ClaimAttemptResource::table(
            Table::make(Livewire::test(ListClaimAttempts::class)->instance())
        )->getColumns(),
    );

    expect($names)->not->toContain('email_fingerprint')
        ->and($names)->not->toContain('token_fingerprint');
});

it('makes nothing about an attempt searchable, so no order number lands in a URL', function (): void {
    recordAttempt('ord-1001');

    $table = ClaimAttemptResource::table(
        Table::make(Livewire::test(ListClaimAttempts::class)->instance())
    );

    foreach ($table->getColumns() as $column) {
        expect($column->isSearchable())->toBeFalse($column->getName());
    }
});

it('shows one merchant only, even when both are hammering an order of the same number', function (): void {
    // Order references are unique within a merchant, not across the deployment.
    // Two merchants both numbering an order 1001 is the ordinary case.
    $ours = recordAttempt('ord-1001', tenantId: TestTenant::PRIMARY);
    $theirs = recordAttempt('ord-1001', tenantId: TestTenant::OTHER);

    Livewire::test(ListClaimAttempts::class)
        ->assertCanSeeTableRecords(ClaimAttempt::query()->whereKey($ours->getKey())->get())
        ->assertCanNotSeeTableRecords(ClaimAttempt::query()->whereKey($theirs->getKey())->get());

    TestTenant::use(TestTenant::OTHER);

    Livewire::test(ListClaimAttempts::class)
        ->assertCanSeeTableRecords(ClaimAttempt::query()->whereKey($theirs->getKey())->get())
        ->assertCanNotSeeTableRecords(ClaimAttempt::query()->whereKey($ours->getKey())->get());
});

it('offers no way to grant a claim that failed to prove itself', function (): void {
    recordAttempt('ord-1001');

    $page = Livewire::test(ListClaimAttempts::class);

    expect($page->instance()->getCachedHeaderActions())->toBe([])
        ->and($page->instance()->getTable()->getRecordActions())->toBe([])
        ->and($page->instance()->getTable()->getToolbarActions())->toBe([]);
});

it('groups by the order claimed, which puts a column name in the URL and never a value', function (): void {
    recordAttempt('ord-1001');

    $groups = ClaimAttemptResource::table(
        Table::make(Livewire::test(ListClaimAttempts::class)->instance())
    )->getGroups();

    expect(array_keys($groups))->toBe(['order_reference']);
});
