<?php

declare(strict_types=1);

use Liberu\Ecommerce\CustomerAccounts\Filament\Pages\ParticipantRegistry;
use Liberu\Ecommerce\CustomerAccounts\Filament\Tests\Fakes\CompletingParticipant;
use Livewire\Livewire;

it('names every registered module and which of them can answer', function (): void {
    registerTheUsualThree();

    Livewire::test(ParticipantRegistry::class)
        ->assertSee('commerce-customers')
        ->assertSee('orders')
        ->assertSee('promotions')
        // Two of the three are silent: one has nothing bound and one publishes
        // only an erasure. Both are visible before any case exists, which is
        // when somebody can still fix them.
        ->assertSee('Silent');
});

it('shows a registered module with nothing bound as silent', function (): void {
    registerParticipants([
        'orders' => ['label' => 'Orders', 'adapter' => null, 'handles' => ['access', 'erasure']],
    ]);

    // `assertCountTableRecords()` cannot see a custom-data table: it counts the
    // Eloquent query, which does not exist here. Assert on the records.
    $records = Livewire::test(ParticipantRegistry::class)->instance()->getTable()->getRecords();

    expect($records)->toHaveCount(1)
        ->and($records->first()['bound'])->toBeFalse()
        ->and($records->first()['silent'])->toBeTrue();
});

it('shows a bound module that publishes both halves as able to answer', function (): void {
    registerParticipants([
        'commerce-customers' => ['label' => 'Files', 'adapter' => CompletingParticipant::class, 'handles' => ['access', 'erasure']],
    ]);

    $record = Livewire::test(ParticipantRegistry::class)->instance()->getTable()->getRecords()->first();

    expect($record['bound'])->toBeTrue()
        ->and($record['silent'])->toBeFalse()
        ->and($record['handles'])->toBe(['Subject access', 'Erasure']);
});

it('shows a bound module that publishes neither half as silent', function (): void {
    registerParticipants([
        'gift-cards' => ['label' => 'Gift cards', 'adapter' => CompletingParticipant::class, 'handles' => []],
    ]);

    $record = Livewire::test(ParticipantRegistry::class)->instance()->getTable()->getRecords()->first();

    // Bound and useless. A participant that declares nothing publishes nothing,
    // and reading the empty list as "handles everything" would leave cases
    // waiting forever on a module with no erasure to run.
    expect($record['bound'])->toBeTrue()
        ->and($record['silent'])->toBeTrue()
        ->and($record['handles'])->toBe([]);
});

it('renders an empty registry as the defect it is', function (): void {
    registerParticipants([]);

    Livewire::test(ParticipantRegistry::class)
        ->assertSee('No participants are registered')
        ->assertSee('will be unsatisfiable');
});

it('does not list this module as a participant of its own registry', function (): void {
    registerTheUsualThree();

    $names = Livewire::test(ParticipantRegistry::class)
        ->instance()
        ->getTable()
        ->getRecords()
        ->map(fn (array $record): string => $record['name'])
        ->all();

    // A coordinator that appears in its own registry reports its own erasure as
    // one more green tick, and stops erasing itself the moment somebody edits
    // the entry out.
    expect($names)->not->toContain('customer-accounts')
        ->and($names)->not->toContain('ecommerce-customer-accounts');
});

it('offers nothing to press, search, filter or sort on the registry', function (): void {
    registerTheUsualThree();

    $table = Livewire::test(ParticipantRegistry::class)->instance()->getTable();

    expect($table->getFilters())->toBe([])
        ->and($table->getHeaderActions())->toBe([])
        ->and($table->getToolbarActions())->toBe([]);
});
