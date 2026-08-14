<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\CustomerAccounts\Actions\CommissionParticipant;
use Liberu\Ecommerce\CustomerAccounts\Actions\ConcludePrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Filament\Tests\Fakes\CompletingParticipant;
use Liberu\Ecommerce\CustomerAccounts\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CustomerAccounts\Filament\Widgets\PrivacyDeskOverview;
use Livewire\Livewire;

it('counts the silent participants, which is knowable before any case exists', function (): void {
    registerTheUsualThree();

    Livewire::test(PrivacyDeskOverview::class)
        ->assertSee('Silent participants')
        // One of three is silent: `orders` is registered with nothing bound to
        // it. `promotions` publishes an erasure and no export, which is not
        // silence — it can answer an erasure, and it answers an access request
        // with a stated reason. The distinction is the point of the column.
        ->assertSee('1 of 3')
        ->assertSee('orders');
});

it('says every participant can answer when every one of them can', function (): void {
    registerParticipants([
        'commerce-customers' => ['label' => 'Files', 'adapter' => CompletingParticipant::class, 'handles' => ['access', 'erasure']],
    ]);

    Livewire::test(PrivacyDeskOverview::class)
        ->assertSee('0 of 1')
        ->assertSee('Every registered participant can answer');
});

it('counts cases past their deadline without promising anything acts on them', function (): void {
    registerTheUsualThree();

    openCase(kind: RequestKind::Erasure, requestedAt: CarbonImmutable::now()->subDays(60), deadlineDays: 30);
    openCase(kind: RequestKind::Erasure, subjectRef: 'person-2');

    Livewire::test(PrivacyDeskOverview::class)
        ->assertSee('Past their deadline')
        ->assertSee('Nothing concludes these on its own')
        ->assertSee('Cases running');
});

it('counts cases completed in part separately from cases completed', function (): void {
    registerTheUsualThree();

    $partial = openCase(RequestKind::Erasure);
    app(CommissionParticipant::class)($partial->reference, 'commerce-customers');
    app(ConcludePrivacyRequest::class)($partial->reference);

    Livewire::test(PrivacyDeskOverview::class)
        ->assertSee('Completed in part')
        ->assertSee('Concluded with a participant that did not complete');
});

it('counts for the panel’s own merchant and no other', function (): void {
    registerTheUsualThree();

    openCase(kind: RequestKind::Erasure, tenantId: TestTenant::OTHER, subjectRef: 'person-elsewhere');

    $stats = (new ReflectionMethod(PrivacyDeskOverview::class, 'getStats'))
        ->invoke(Livewire::test(PrivacyDeskOverview::class)->instance());

    expect((string) $stats[0]->getValue())->toBe('0');
});
