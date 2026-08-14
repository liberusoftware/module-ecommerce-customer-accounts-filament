<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\CustomerAccounts\Actions\CommissionParticipant;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestState;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\PrivacyRequests\Pages\ListPrivacyRequests;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\PrivacyRequests\Pages\ViewPrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Filament\Tests\Fakes\CompletingParticipant;
use Liberu\Ecommerce\CustomerAccounts\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CustomerAccounts\Filament\Widgets\PrivacyDeskOverview;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequest;
use Livewire\Livewire;

it('renders a case as a count of participants that answered, never as done', function (): void {
    registerTheUsualThree();
    $case = openCase(RequestKind::Erasure);

    Livewire::test(ListPrivacyRequests::class)
        ->assertCanSeeTableRecords(PrivacyRequest::query()->get())
        // Three registered, none asked. The desk says so rather than showing an
        // empty participant list that reads as nothing left to do.
        ->assertSee('0 of 3 answered')
        ->assertSee($case->reference);
});

it('names the participants that have not completed, on the list and on the case', function (): void {
    registerTheUsualThree();
    $case = openCase(RequestKind::Erasure);

    app(CommissionParticipant::class)($case->reference, 'commerce-customers');

    Livewire::test(ListPrivacyRequests::class)
        ->assertSee('1 of 3 answered')
        // The two that have not completed are named, not counted.
        ->assertSee('orders')
        ->assertSee('promotions');

    Livewire::test(ViewPrivacyRequest::class, ['record' => $case->reference])
        ->assertSee('1 of 3 answered')
        ->assertSee('orders, promotions');
});

it('shows a case whose participants all completed as completed', function (): void {
    registerParticipants([
        'commerce-customers' => ['label' => 'Files', 'adapter' => CompletingParticipant::class, 'handles' => ['erasure']],
    ]);

    $case = openCase(RequestKind::Erasure);
    app(CommissionParticipant::class)($case->reference, 'commerce-customers');

    expect($case->refresh()->state)->toBe(RequestState::Completed);

    Livewire::test(ListPrivacyRequests::class)
        ->assertSee('1 of 1 answered')
        ->assertSee('Completed');
});

it('concludes a case that did not reach everybody as completed in part, naming them', function (): void {
    registerTheUsualThree();
    $case = openCase(RequestKind::Erasure);
    app(CommissionParticipant::class)($case->reference, 'commerce-customers');

    Livewire::test(ViewPrivacyRequest::class, ['record' => $case->reference])
        ->callAction('conclude');

    $case->refresh();

    expect($case->state)->toBe(RequestState::Partial)
        ->and($case->progress()->outstanding)->toBe(['orders', 'promotions'])
        ->and($case->concluded_at)->not->toBeNull();

    Livewire::test(ListPrivacyRequests::class)->assertSee('Completed in part');
});

it('offers no way to conclude a case as completed when a participant did not answer', function (): void {
    registerTheUsualThree();
    $case = openCase(RequestKind::Erasure);

    Livewire::test(ViewPrivacyRequest::class, ['record' => $case->reference])
        ->callAction('conclude');

    // ConcludePrivacyRequest cannot force Completed and this panel publishes no
    // override. There is no button that makes a report look finished.
    expect($case->refresh()->state)->toBe(RequestState::Partial);
});

it('records a withdrawal and stops offering either action afterwards', function (): void {
    registerTheUsualThree();
    $case = openCase(RequestKind::Erasure);

    Livewire::test(ViewPrivacyRequest::class, ['record' => $case->reference])
        ->callAction('withdraw', ['reason' => 'They rang back.']);

    expect($case->refresh()->state)->toBe(RequestState::Withdrawn);

    Livewire::test(ViewPrivacyRequest::class, ['record' => $case->reference])
        ->assertActionHidden('conclude')
        ->assertActionHidden('withdraw');
});

it('says nothing about a cooling-off period, because none exists', function (): void {
    registerTheUsualThree();
    $case = openCase(RequestKind::Erasure);

    $page = Livewire::test(ViewPrivacyRequest::class, ['record' => $case->reference]);

    // Withdrawal is available until the case concludes, which is a different
    // promise from a window in which the request has not started yet. Nothing
    // in this module has a clock, so nothing here may imply one.
    foreach (['cooling', 'still cancel', 'grace period', 'undo'] as $phrase) {
        $page->assertDontSee($phrase);
    }

    // And the withdrawal modal is honest about what it is not: it stops further
    // participants being commissioned, and an erasure that already ran is not
    // coming back.
    $withdraw = $page->instance()->getAction('withdraw');

    expect((string) $withdraw?->getModalDescription())
        ->toContain('does not undo anything a participant already did');
});

it('shows a passed deadline on the case, and says nothing will act on it', function (): void {
    registerTheUsualThree();

    $case = openCase(
        kind: RequestKind::Erasure,
        requestedAt: CarbonImmutable::now()->subDays(60),
        deadlineDays: 30,
    );

    Livewire::test(ViewPrivacyRequest::class, ['record' => $case->reference])
        ->assertSee('Past its deadline')
        ->assertSee('Nothing sweeps deadlines and nothing concludes a case on its own');
});

it('puts the desk overview on the list page rather than leaving the gap unmeasured', function (): void {
    expect(Livewire::test(ListPrivacyRequests::class)->instance()->getVisibleHeaderWidgets())
        ->toContain(PrivacyDeskOverview::class);
});

it('finds a cross-tenant case by its scope and never by a null tenant', function (): void {
    registerTheUsualThree();

    $everywhere = openCase(kind: RequestKind::Erasure, subjectRef: 'person-1', scope: RequestScope::Everywhere);
    $here = openCase(kind: RequestKind::Erasure, subjectRef: 'person-2', scope: RequestScope::Tenant);

    // The deployment-wide case still carries the merchant it was raised at.
    expect($everywhere->tenant_id)->toBe(TestTenant::PRIMARY)
        ->and(PrivacyRequest::query()->whereNull('tenant_id')->count())->toBe(0);

    Livewire::test(ListPrivacyRequests::class)
        ->filterTable('scope', RequestScope::Everywhere->value)
        ->assertCanSeeTableRecords(PrivacyRequest::query()->where('reference', $everywhere->reference)->get())
        ->assertCanNotSeeTableRecords(PrivacyRequest::query()->where('reference', $here->reference)->get());
});

it('records a request that arrived at the desk, and erases nothing doing it', function (): void {
    registerTheUsualThree();

    Livewire::test(ListPrivacyRequests::class)
        ->callAction('openCase', [
            'subject_ref' => 'person-9',
            'kind' => RequestKind::Erasure->value,
            'scope' => RequestScope::Tenant->value,
            'lawful_basis' => 'legal_obligation',
            'channel' => 'desk',
            'requested_at' => CarbonImmutable::now()->toDateTimeString(),
            'reauthenticated_via' => 'identity documents at the desk',
            'reason' => 'Asked at the counter.',
        ]);

    $case = PrivacyRequest::query()->where('subject_ref', 'person-9')->firstOrFail();

    expect($case->state)->toBe(RequestState::Open)
        ->and($case->progress()->participants)->toBe(3)
        // Every participant has a row and none of them has been asked. Nothing
        // was erased inside the request that opened the case.
        ->and($case->progress()->completed)->toBe(0);
});

it('refuses to open a destructive case with no recorded re-authentication', function (): void {
    registerTheUsualThree();

    Livewire::test(ListPrivacyRequests::class)
        ->callAction('openCase', [
            'subject_ref' => 'person-10',
            'kind' => RequestKind::Erasure->value,
            'scope' => RequestScope::Tenant->value,
            'lawful_basis' => 'legal_obligation',
            'channel' => 'desk',
            'requested_at' => CarbonImmutable::now()->toDateTimeString(),
            'reauthenticated_via' => '',
        ])
        ->assertHasActionErrors(['reauthenticated_via']);

    expect(PrivacyRequest::query()->where('subject_ref', 'person-10')->exists())->toBeFalse();
});

it('warns when a case is opened into an empty registry', function (): void {
    registerParticipants([]);

    Livewire::test(ListPrivacyRequests::class)
        ->callAction('openCase', [
            'subject_ref' => 'person-11',
            'kind' => RequestKind::Access->value,
            'scope' => RequestScope::Tenant->value,
            'lawful_basis' => 'legal_obligation',
            'channel' => 'post',
            'requested_at' => CarbonImmutable::now()->toDateTimeString(),
        ]);

    $case = PrivacyRequest::query()->where('subject_ref', 'person-11')->firstOrFail();

    expect($case->progress()->participants)->toBe(0)
        ->and($case->progress()->isSatisfied())->toBeFalse();

    Livewire::test(ListPrivacyRequests::class)->assertSee('No participants registered');
});

it('shows an access export as partial while any participant has contributed nothing', function (): void {
    registerTheUsualThree();
    $case = openCase(RequestKind::Access);

    app(CommissionParticipant::class)($case->reference, 'commerce-customers');

    Livewire::test(ViewPrivacyRequest::class, ['record' => $case->reference])
        ->assertSee('Partial')
        // Promotions publishes no export at all and is named rather than left
        // out — leaving it out is what makes an export look whole.
        ->assertSee('promotions');
});

it('shows an access export as whole only when every participant contributed', function (): void {
    registerParticipants([
        'commerce-customers' => ['label' => 'Files', 'adapter' => CompletingParticipant::class, 'handles' => ['access']],
    ]);

    $case = openCase(RequestKind::Access);
    app(CommissionParticipant::class)($case->reference, 'commerce-customers');

    Livewire::test(ViewPrivacyRequest::class, ['record' => $case->reference])
        ->assertSee('Every participant contributed');
});

it('offers no export section on an erasure, which has none to assemble', function (): void {
    registerTheUsualThree();
    $case = openCase(RequestKind::Erasure);

    Livewire::test(ViewPrivacyRequest::class, ['record' => $case->reference])
        ->assertDontSee('Every participant contributed')
        ->assertDontSee('Contributed nothing');
});
