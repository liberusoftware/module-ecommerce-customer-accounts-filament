<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament\Resources\SavedLists\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Liberu\Ecommerce\CustomerAccounts\Filament\Concerns\DeniesUnpublishedRelationAbilities;
use Liberu\Ecommerce\CustomerAccounts\Filament\Support\Render;
use Liberu\Ecommerce\CustomerAccounts\Models\SavedListShare;

/**
 * The links that point at this list, live and revoked, with the moment and the
 * reason each one stopped working.
 *
 * A share is its own aggregate with its own revocable token, and that shape is a
 * direct correction of the host: it put a single `wishlist_share_token` on
 * `users`, so one link answered differently depending on which storefront the
 * visitor landed on — silently, with nothing on the page saying anything was
 * missing — and `WishlistController::share()` re-minted that token on every
 * call, killing every previously published link with no record that it had ever
 * existed. Here the row survives revocation, so "this link stopped working on
 * Tuesday because the owner revoked it" and "this link never existed" stay
 * different answers.
 *
 * **The token itself is not on this screen and cannot be.** Only a fingerprint
 * is stored, and it is not rendered either: a fingerprint of a live share is
 * still the identifier of a URL somebody published.
 *
 * Read-only, including the revocation. Revoking a person's share for them is a
 * decision about their property; the shopper does it from their own surface, and
 * concluding an erasure revokes every one of them without anybody pressing
 * anything.
 */
final class SavedListSharesRelationManager extends RelationManager
{
    use DeniesUnpublishedRelationAbilities;

    protected static string $relationship = 'shares';

    protected static ?string $title = 'Links to this list';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->label('Share')->copyable(),
                IconColumn::make('live')
                    ->label('Still works')
                    ->boolean()
                    ->state(fn (SavedListShare $record): bool => $record->isLive())
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('created_at')->label('Shared')->dateTime()->sortable(),
                TextColumn::make('revoked_at')->label('Revoked')->dateTime()->placeholder('Not revoked'),
                TextColumn::make('revoked_reason')->label('Why')->placeholder(Render::NONE),
            ])
            ->filters([])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(null)
            ->recordAction(null)
            ->recordActions([])
            ->headerActions([])
            ->toolbarActions([]);
    }
}
