<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament\Resources\ClaimAttempts;

use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\CustomerAccounts\Enums\ClaimAttemptOutcome;
use Liberu\Ecommerce\CustomerAccounts\Filament\Concerns\DeniesUnpublishedResourceAbilities;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\ClaimAttempts\Pages\ListClaimAttempts;
use Liberu\Ecommerce\CustomerAccounts\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CustomerAccounts\Filament\Support\Render;
use Liberu\Ecommerce\CustomerAccounts\Models\ClaimAttempt;
use UnitEnum;

/**
 * Attempts to claim a guest order, and above all the ones that failed.
 *
 * A guest order claim is proof-of-possession: an order reference plus the email
 * it was placed with, completed by a token sent to the address *on the order*.
 * Both of the first two appear on a receipt that may have been forwarded, so the
 * claim path is the one place in this module where somebody is guessing — and an
 * unlimited claim endpoint is an order-reference oracle. The failures are the
 * security signal, and a module that stored only successful claims could not
 * show an operator that somebody tried forty.
 *
 * **Two things are deliberately not on this screen and never will be.**
 *
 * The **claim token** does not appear anywhere. It exists in readable form
 * exactly once, in the `ClaimProof` handed back at the moment a claim is opened,
 * and is stored only as a fingerprint. A token an operator can read out of a
 * panel is a token an operator can use, which would make the whole
 * proof-of-possession theatre.
 *
 * The **email fingerprint** is not a column, not a search and not a filter.
 * Search terms and filter values persist into the query string and from there
 * into browser history and pasted links; a hash of somebody's address does not
 * stop being about them because it is a hash. Attempts against one order are
 * found by grouping on the order reference, which puts a column *name* in the
 * URL and never a value.
 *
 * Note what the outcomes do not distinguish. `Refused` covers a reference that
 * does not exist and an address that does not match it, as **one** outcome: the
 * verification seam answers matched / not matched / unavailable and is never
 * told which of the two failed, so neither this module nor this panel is able to
 * leak the difference even by accident. Do not add a friendlier message here.
 */
final class ClaimAttemptResource extends Resource
{
    use DeniesUnpublishedResourceAbilities;

    protected static ?string $model = ClaimAttempt::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $modelLabel = 'claim attempt';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationLabel = 'Claim attempts';

    protected static UnitEnum|string|null $navigationGroup = 'Privacy desk';

    protected static ?int $navigationSort = 20;

    /** An attempt is a record of something somebody did. There is no create path. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** Append-only in the domain — an `updating` hook refuses it — and closed here too. */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /** @return Builder<ClaimAttempt> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', PanelTenant::current());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('attempted_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('order_reference')
                    ->label('Order claimed')
                    // Sortable and groupable, never searchable: sorting and
                    // grouping put a column name in the query string, a search
                    // puts somebody's order number in it.
                    ->sortable(),
                TextColumn::make('outcome')
                    ->label('Outcome')
                    ->badge()
                    ->color(fn (ClaimAttemptOutcome $state): string => Render::attemptColour($state))
                    ->sortable(),
                TextColumn::make('channel')->label('From'),
                TextColumn::make('claim_reference')
                    ->label('Claim')
                    ->placeholder('No claim was opened')
                    ->limit(20),
            ])
            ->filters([
                // Failures first, because that is what this screen is for. The
                // filter is a toggle rather than the default query, so an
                // operator can see the successful claims in the same list and
                // judge a run of failures against them.
                Filter::make('failures')
                    ->label('Failed attempts only')
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->whereNotIn('outcome', [
                        ClaimAttemptOutcome::Issued->value,
                        ClaimAttemptOutcome::Granted->value,
                    ])),
                SelectFilter::make('outcome')
                    ->label('Outcome')
                    ->options(fn (): array => self::outcomes()),
            ])
            ->groups([
                Group::make('order_reference')->label('Order claimed'),
            ])
            ->defaultSort('attempted_at', 'desc')
            ->recordUrl(null)
            ->recordAction(null)
            ->recordActions([])
            ->toolbarActions([]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListClaimAttempts::route('/'),
        ];
    }

    /** @return array<string, string> */
    private static function outcomes(): array
    {
        $options = [];

        foreach (ClaimAttemptOutcome::cases() as $outcome) {
            $options[$outcome->value] = ucfirst(str_replace('_', ' ', $outcome->value));
        }

        return $options;
    }
}
