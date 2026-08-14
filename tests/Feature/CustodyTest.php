<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\PrivacyRequests\Pages\ListPrivacyRequests;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\PrivacyRequests\Pages\ViewPrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\PrivacyRequests\RelationManagers\ParticipantsRelationManager;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\SavedLists\Pages\ViewSavedList;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\SavedLists\RelationManagers\SavedListItemsRelationManager;
use Liberu\Ecommerce\CustomerAccounts\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CustomerAccounts\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequestParticipant;
use Liberu\Ecommerce\CustomerAccounts\Models\SavedListItem;
use Livewire\Livewire;
use RuntimeException;

/**
 * Two merchants, and the same references on both.
 *
 * Wave 14 found the previous wave's custody proof was written about queries
 * while the leak was in a relation, so the relations are covered here explicitly
 * — and every fixture deliberately reuses a reference across merchants, because
 * the test that catches a tenant leak is always the one with two of them.
 */
it('shows one merchant’s cases and not the other’s', function (): void {
    registerTheUsualThree();

    $ours = openCase(kind: RequestKind::Erasure, tenantId: TestTenant::PRIMARY, subjectRef: 'person-1');
    $theirs = openCase(kind: RequestKind::Erasure, tenantId: TestTenant::OTHER, subjectRef: 'person-1');

    Livewire::test(ListPrivacyRequests::class)
        ->assertCanSeeTableRecords(PrivacyRequest::query()->whereKey($ours->getKey())->get())
        ->assertCanNotSeeTableRecords(PrivacyRequest::query()->whereKey($theirs->getKey())->get());
});

it('refuses to open another merchant’s case by its reference', function (): void {
    registerTheUsualThree();

    $theirs = openCase(kind: RequestKind::Erasure, tenantId: TestTenant::OTHER, subjectRef: 'person-1');

    // The resource's Eloquent query is the route binding, so a foreign case is
    // a 404 rather than a 403 — the ownership check and the not-an-oracle
    // property in one line, which is the one host behaviour worth keeping.
    expect(fn () => Livewire::test(ViewPrivacyRequest::class, ['record' => $theirs->reference]))
        ->toThrow(ModelNotFoundException::class);
});

it('keeps a case’s participants to that case, across merchants using the same subject', function (): void {
    registerTheUsualThree();

    $ours = openCase(kind: RequestKind::Erasure, tenantId: TestTenant::PRIMARY, subjectRef: 'person-1');
    openCase(kind: RequestKind::Erasure, tenantId: TestTenant::OTHER, subjectRef: 'person-1');

    // Six participant rows exist. This case sees three.
    expect(PrivacyRequestParticipant::query()->count())->toBe(6);

    $records = Livewire::test(ParticipantsRelationManager::class, [
        'ownerRecord' => $ours,
        'pageClass' => ViewPrivacyRequest::class,
    ])->instance()->getTable()->getRecords();

    expect($records)->toHaveCount(3);

    foreach ($records as $record) {
        expect($record->tenant_id)->toBe(TestTenant::PRIMARY);
    }
});

it('keeps a list’s items to that list, across merchants using the same product', function (): void {
    $ours = savedListWithItems(tenantId: TestTenant::PRIMARY, ownerRef: 'person-1', name: 'Christmas');
    savedListWithItems(tenantId: TestTenant::OTHER, ownerRef: 'person-1', name: 'Christmas');

    expect(SavedListItem::query()->count())->toBe(2);

    $records = Livewire::test(SavedListItemsRelationManager::class, [
        'ownerRecord' => $ours,
        'pageClass' => ViewSavedList::class,
    ])->instance()->getTable()->getRecords();

    expect($records)->toHaveCount(1)
        ->and($records->first()->tenant_id)->toBe(TestTenant::PRIMARY);
});

it('a case raised elsewhere with a deployment-wide reach is still not this merchant’s to see', function (): void {
    registerTheUsualThree();

    $theirs = openCase(
        kind: RequestKind::Erasure,
        tenantId: TestTenant::OTHER,
        subjectRef: 'person-1',
        scope: RequestScope::Everywhere,
    );

    // Reach is not visibility. A case raised at another merchant reaching the
    // person everywhere is still that merchant's case, and this desk shows the
    // cases raised here — with the reach on every row, so an operator can see
    // that one of theirs went beyond us.
    Livewire::test(ListPrivacyRequests::class)
        ->assertCanNotSeeTableRecords(PrivacyRequest::query()->whereKey($theirs->getKey())->get());
});

it('throws rather than resolving an empty tenant, which would match orphan rows', function (): void {
    PanelTenant::resolveUsing(fn (): string => '');

    expect(fn (): string => PanelTenant::current())
        ->toThrow(RuntimeException::class, 'would match orphan rows');

    PanelTenant::resolveUsing(fn (): string => TestTenant::current());
});

it('throws rather than resolving no tenant at all', function (): void {
    PanelTenant::resolveUsing(null);

    // No Filament tenant is configured on the test panel, so there is nothing
    // to fall back to — and falling back to null is the `is null` predicate the
    // domain's NOT NULL columns exist to make impossible.
    expect(fn (): string => PanelTenant::current())->toThrow(RuntimeException::class);

    PanelTenant::resolveUsing(fn (): string => TestTenant::current());
});
