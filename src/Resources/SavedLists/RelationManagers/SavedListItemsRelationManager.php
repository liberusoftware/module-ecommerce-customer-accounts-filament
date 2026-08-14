<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament\Resources\SavedLists\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Liberu\Ecommerce\CustomerAccounts\Filament\Concerns\DeniesUnpublishedRelationAbilities;
use Liberu\Ecommerce\CustomerAccounts\Filament\Support\Render;

/**
 * What is on the list: a product reference, a quantity and when it was added.
 *
 * **There is no price column here and there is no price column in the table
 * behind it.** Not the price now and especially not the price when it was saved:
 * a list holds references and survives a repricing, a cart holds priced lines
 * and does not, and asserting what something cost when somebody bookmarked it
 * would fork the definition of that figure away from the module that owns it.
 *
 * The relation restates the tenant in the domain model, so this manager does not
 * add a second predicate. It is joined on `saved_list_id`, which is a global key
 * rather than a per-merchant reference, so there is no cross-merchant collision
 * to defend against here — unlike the claim attempts, which join on an order
 * reference that two merchants can both be using.
 */
final class SavedListItemsRelationManager extends RelationManager
{
    use DeniesUnpublishedRelationAbilities;

    protected static string $relationship = 'items';

    protected static ?string $title = 'On this list';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product_ref')->label('Product')->sortable(),
                TextColumn::make('quantity')->label('Quantity'),
                TextColumn::make('note')->label('Note')->placeholder(Render::NONE)->wrap(),
                TextColumn::make('added_at')->label('Added')->dateTime()->sortable(),
            ])
            ->filters([])
            ->defaultSort('added_at')
            ->recordUrl(null)
            ->recordAction(null)
            ->recordActions([])
            ->headerActions([])
            ->toolbarActions([]);
    }
}
