<?php

declare(strict_types=1);

use Liberu\Ecommerce\CustomerAccounts\Actions\CommissionParticipant;
use Liberu\Ecommerce\CustomerAccounts\Enums\ParticipantOutcome;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\PrivacyRequests\Pages\ViewPrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\PrivacyRequests\RelationManagers\ParticipantsRelationManager;
use Liberu\Ecommerce\CustomerAccounts\Filament\Tests\Fakes\CompletingParticipant;
use Liberu\Ecommerce\CustomerAccounts\Filament\Tests\Fakes\CrossTenantParticipant;
use Liberu\Ecommerce\CustomerAccounts\Filament\Tests\Fakes\ThrowingParticipant;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequestParticipant;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/** The relation manager, mounted the way Filament mounts one. */
function participantsTable(PrivacyRequest $case): Testable
{
    return Livewire::test(ParticipantsRelationManager::class, [
        'ownerRecord' => $case,
        'pageClass' => ViewPrivacyRequest::class,
    ]);
}

it('lists every participant of the case, including the ones that answered nothing', function (): void {
    registerTheUsualThree();
    $case = openCase(RequestKind::Erasure);

    app(CommissionParticipant::class)($case->reference, 'commerce-customers');
    app(CommissionParticipant::class)($case->reference, 'orders');

    participantsTable($case)
        ->assertCanSeeTableRecords(PrivacyRequestParticipant::query()->get())
        // All three, whatever they said. A table of only the answers is a table
        // in which every case looks complete.
        ->assertSee('commerce-customers')
        ->assertSee('orders')
        ->assertSee('promotions')
        ->assertSee('Answered')
        ->assertSee('Unavailable')
        ->assertSee('Not yet asked');
});

it('shows the reason a registered participant could not answer', function (): void {
    registerTheUsualThree();
    $case = openCase(RequestKind::Erasure);

    app(CommissionParticipant::class)($case->reference, 'orders');

    participantsTable($case)
        // Registered, and nothing bound to answer for it — which is a different
        // fact from a module that threw and from one that publishes no erasure.
        ->assertSee('Registered, but nothing is bound to answer for it.');
});

it('shows a participant that publishes only one half as unavailable with its reason', function (): void {
    registerTheUsualThree();

    // Promotions publishes an erasure and no export. On an access request it is
    // still a participant: registered, commissioned, and answering unavailable,
    // which makes the export honestly partial instead of quietly whole.
    $case = openCase(RequestKind::Access);

    $promotions = $case->participants()->where('participant', 'promotions')->firstOrFail();

    expect($promotions->outcome)->toBe(ParticipantOutcome::Unavailable)
        ->and($promotions->note)->toBe('Publishes no access.');

    participantsTable($case)->assertSee('Publishes no access.');
});

it('shows a participant that threw as failed, with what it said', function (): void {
    registerParticipants([
        'reviews' => ['label' => 'Reviews', 'adapter' => ThrowingParticipant::class, 'handles' => ['erasure']],
    ]);

    $case = openCase(RequestKind::Erasure);
    app(CommissionParticipant::class)($case->reference, 'reviews');

    participantsTable($case)
        ->assertSee('Failed')
        ->assertSee('refused the connection');
});

it('marks an answer that reached further than the case asked as a mismatch', function (): void {
    registerParticipants([
        'commerce-customers' => ['label' => 'Files', 'adapter' => CrossTenantParticipant::class, 'handles' => ['erasure']],
    ]);

    $case = openCase(kind: RequestKind::Erasure, scope: RequestScope::Tenant);
    app(CommissionParticipant::class)($case->reference, 'commerce-customers');

    $participant = $case->participants()->firstOrFail();

    expect($participant->scope_mismatch)->toBeTrue()
        ->and($participant->scope_applied)->toBe(RequestScope::Everywhere);

    Livewire::test(ViewPrivacyRequest::class, ['record' => $case->reference])
        ->assertSee('Answered at a different scope than was asked')
        ->assertSee('commerce-customers');
});

it('asks a participant again through the domain action, writing nothing in its tables', function (): void {
    registerParticipants([
        'orders' => ['label' => 'Orders', 'adapter' => null, 'handles' => ['erasure']],
    ]);

    $case = openCase(RequestKind::Erasure);
    app(CommissionParticipant::class)($case->reference, 'orders');

    $participant = $case->participants()->firstOrFail();
    expect($participant->outcome)->toBe(ParticipantOutcome::Unavailable)
        ->and($participant->attempts)->toBe(1);

    // Bind the adapter the host forgot, then retry from the panel. The retry is
    // a re-commission of our own case, never a write into somebody else's rows.
    registerParticipants([
        'orders' => ['label' => 'Orders', 'adapter' => CompletingParticipant::class, 'handles' => ['erasure']],
    ]);

    participantsTable($case)->callTableAction('commission', $participant);

    $participant->refresh();

    expect($participant->outcome)->toBe(ParticipantOutcome::Completed)
        ->and($participant->attempts)->toBe(2);
});

it('offers no retry on a participant that completed, or on a case that concluded', function (): void {
    registerParticipants([
        'commerce-customers' => ['label' => 'Files', 'adapter' => CompletingParticipant::class, 'handles' => ['erasure']],
    ]);

    $case = openCase(RequestKind::Erasure);
    app(CommissionParticipant::class)($case->reference, 'commerce-customers');

    $participant = $case->participants()->firstOrFail();

    // Completed, and the case concluded itself when it answered.
    participantsTable($case->refresh())->assertTableActionHidden('commission', $participant);
});

it('offers no filter that could hide a participant that did not answer', function (): void {
    registerTheUsualThree();
    $case = openCase(RequestKind::Erasure);

    expect(participantsTable($case)->instance()->getTable()->getFilters())->toBe([]);
});
