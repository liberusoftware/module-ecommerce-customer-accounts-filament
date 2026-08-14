<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament\Resources\SavedLists\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\SavedLists\SavedListResource;

/**
 * One list, its items and its shares.
 *
 * No header action, deliberately. Revoking somebody's share for them is a
 * decision about their property; the shopper revokes their own from the
 * `-livewire` surface, and an erasure revokes every one of them without anybody
 * pressing anything.
 */
final class ViewSavedList extends ViewRecord
{
    protected static string $resource = SavedListResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
