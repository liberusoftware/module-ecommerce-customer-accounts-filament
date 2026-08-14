<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament\Resources\SavedLists\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\SavedLists\SavedListResource;

/** Read-only. A list belongs to the person who made it, and this is a window on it. */
final class ListSavedLists extends ListRecords
{
    protected static string $resource = SavedListResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
