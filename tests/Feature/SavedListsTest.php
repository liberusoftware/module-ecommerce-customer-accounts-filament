<?php

declare(strict_types=1);

use Liberu\Ecommerce\CustomerAccounts\Actions\RevokeShare;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\SavedLists\Pages\ListSavedLists;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\SavedLists\Pages\ViewSavedList;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\SavedLists\RelationManagers\SavedListItemsRelationManager;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\SavedLists\RelationManagers\SavedListSharesRelationManager;
use Liberu\Ecommerce\CustomerAccounts\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CustomerAccounts\Models\SavedList;
use Livewire\Livewire;

it('lists a merchant’s saved lists', function (): void {
    savedListWithItems(name: 'Christmas');

    Livewire::test(ListSavedLists::class)
        ->assertCanSeeTableRecords(SavedList::query()->get())
        ->assertSee('Christmas');
});

it('shows one merchant’s lists only', function (): void {
    $ours = savedListWithItems(tenantId: TestTenant::PRIMARY, name: 'Ours');
    $theirs = savedListWithItems(tenantId: TestTenant::OTHER, name: 'Theirs');

    Livewire::test(ListSavedLists::class)
        ->assertCanSeeTableRecords(SavedList::query()->whereKey($ours->getKey())->get())
        ->assertCanNotSeeTableRecords(SavedList::query()->whereKey($theirs->getKey())->get());
});

it('shows what is on a list, and never what it cost', function (): void {
    $list = savedListWithItems();

    Livewire::test(SavedListItemsRelationManager::class, [
        'ownerRecord' => $list,
        'pageClass' => ViewSavedList::class,
    ])
        ->assertSee('prod-1')
        ->assertSee('the blue one')
        // A list holds references and survives a repricing. There is no price
        // column on the table behind this and none is computed here.
        ->assertDontSee('Price')
        ->assertDontSee('Total');
});

it('shows a link that stopped working as revoked, not as missing', function (): void {
    $list = savedListWithItems();
    $share = $list->shares()->firstOrFail();

    app(RevokeShare::class)($share->reference, 'owner_revoked');

    Livewire::test(SavedListSharesRelationManager::class, [
        'ownerRecord' => $list->refresh(),
        'pageClass' => ViewSavedList::class,
    ])
        // The host re-minted one token per user on every share, killing every
        // published link with no record it had existed. The row survives here.
        ->assertSee($share->reference)
        ->assertSee('owner_revoked');
});

it('never renders a share token or its fingerprint', function (): void {
    $list = savedListWithItems();
    $share = $list->shares()->firstOrFail();

    Livewire::test(SavedListSharesRelationManager::class, [
        'ownerRecord' => $list,
        'pageClass' => ViewSavedList::class,
    ])->assertDontSee($share->token_fingerprint);
});

it('offers no way to revoke, share, rename or empty somebody’s list', function (): void {
    $list = savedListWithItems();

    $shares = Livewire::test(SavedListSharesRelationManager::class, [
        'ownerRecord' => $list,
        'pageClass' => ViewSavedList::class,
    ])->instance()->getTable();

    $items = Livewire::test(SavedListItemsRelationManager::class, [
        'ownerRecord' => $list,
        'pageClass' => ViewSavedList::class,
    ])->instance()->getTable();

    expect($shares->getRecordActions())->toBe([])
        ->and($shares->getHeaderActions())->toBe([])
        ->and($shares->getToolbarActions())->toBe([])
        ->and($items->getRecordActions())->toBe([])
        ->and($items->getHeaderActions())->toBe([])
        ->and($items->getToolbarActions())->toBe([])
        ->and(Livewire::test(ViewSavedList::class, ['record' => $list->reference])->instance()->getCachedHeaderActions())->toBe([])
        ->and(Livewire::test(ListSavedLists::class)->instance()->getCachedHeaderActions())->toBe([]);
});

it('counts a list’s live shares where the record is loaded', function (): void {
    $list = savedListWithItems();

    // Not in the table: `withCount()` builds the relation from a fresh model
    // instance, so the domain's deliberate cast of the parent's tenant id makes
    // the predicate an empty string and counts zero for every row. It fails
    // closed, and it fails, so the count lives on the record's own page.
    Livewire::test(ViewSavedList::class, ['record' => $list->reference])
        ->assertSee('Live shares')
        ->assertSee('Christmas');
});
