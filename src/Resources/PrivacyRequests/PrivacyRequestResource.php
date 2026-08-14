<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament\Resources\PrivacyRequests;

use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\CustomerAccounts\Enums\LawfulBasis;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestState;
use Liberu\Ecommerce\CustomerAccounts\Filament\Concerns\DeniesUnpublishedResourceAbilities;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\PrivacyRequests\Pages\ListPrivacyRequests;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\PrivacyRequests\Pages\ViewPrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\PrivacyRequests\RelationManagers\ParticipantsRelationManager;
use Liberu\Ecommerce\CustomerAccounts\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CustomerAccounts\Filament\Support\Render;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Queries\AssembleExport;
use UnitEnum;

/**
 * The case, with its participants.
 *
 * The host had no such row. Its erasure ran inside an HTTP request, wrote ten
 * tables belonging to six modules, and returned `{"success": true}` from a
 * function that could not have known — and a person whose request failed halfway
 * had no case number to quote at anybody. This screen is the correction, and
 * three of its rules follow from that directly.
 *
 * **A case is never rendered as done unless every participant completed.** The
 * `Answered` column is "3 of 5", counted from the participant rows on every
 * read, and `Partial` is coloured as a warning wherever it appears. There is no
 * green tick anywhere on this resource that does not mean all of them.
 *
 * **A cross-tenant case is found by its scope, never by a null tenant.**
 * `tenant_id` is NOT NULL on every table in this module and means the merchant
 * the case was *raised at*; how far it reaches is `RequestScope`. So the tenant
 * predicate below is unconditional, and "did this case go beyond us" is a column
 * and a filter rather than a hole in a `where`.
 *
 * **Nothing here is a sweeper.** A deadline that has passed is shown in red and
 * that is all that happens to it: no job concludes a case, nothing expires, and
 * a case sits late until a person opens it. Concluding is
 * `ConcludePrivacyRequest` pressed by somebody, and it cannot force `Completed`.
 *
 * **The subject reference is neither searchable nor filterable.** A table search
 * term and a filter's state both persist into the query string, and from there
 * into browser history, bookmarks and any URL pasted into a ticket. The case
 * reference is searchable because it is our own minted case number — the string
 * the person is told to quote — and identifies a case rather than a person.
 */
final class PrivacyRequestResource extends Resource
{
    use DeniesUnpublishedResourceAbilities;

    protected static ?string $model = PrivacyRequest::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $recordTitleAttribute = 'reference';

    protected static ?string $modelLabel = 'privacy request';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Privacy requests';

    protected static UnitEnum|string|null $navigationGroup = 'Privacy desk';

    protected static ?int $navigationSort = 10;

    /**
     * There is no Filament create page.
     *
     * A case *can* be opened from this panel — a request that arrives by post or
     * at a desk has nowhere else to be recorded — but it is opened by the
     * `openCase` header action calling `OpenPrivacyRequest`, so the domain's
     * refusal to open a destructive case with no recorded re-authentication runs
     * on the only path there is. A Filament create page would write the model.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * A case is evidence and is never edited.
     *
     * Its state is a fold over the participant rows; its deadline is what it was
     * when the person asked. An operator who thinks a field is wrong is looking
     * at a case that should be withdrawn and re-opened, which leaves both facts
     * on the record.
     */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /** @return Builder<PrivacyRequest> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', PanelTenant::current());
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [
            ParticipantsRelationManager::class,
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Progress')
                ->description('A case is done when every registered participant completed. Anything else that is finished is completed in part, and the participants that did not answer are named.')
                ->columns(3)
                ->schema([
                    TextEntry::make('state')
                        ->label('State')
                        ->badge()
                        ->formatStateUsing(fn (RequestState $state): string => $state->label())
                        ->color(fn (RequestState $state): string => Render::stateColour($state)),
                    TextEntry::make('answered')
                        ->label('Participants answered')
                        ->state(fn (PrivacyRequest $record): string => Render::progress($record->progress())),
                    TextEntry::make('attention')
                        ->label('Needs attention')
                        ->badge()
                        ->color('danger')
                        ->state(fn (PrivacyRequest $record): string => Render::isLate($record) ? 'Past its deadline' : 'No')
                        ->helperText('Nothing sweeps deadlines and nothing concludes a case on its own. A late case stays late until somebody opens it.'),
                    TextEntry::make('outstanding')
                        ->label('Have not completed')
                        ->state(fn (PrivacyRequest $record): string => Render::names($record->progress()->outstanding))
                        ->helperText('Named rather than counted, because "your data has been erased" is a claim about all of it.')
                        ->columnSpanFull(),
                    TextEntry::make('mismatched')
                        ->label('Answered at a different scope than was asked')
                        ->state(fn (PrivacyRequest $record): string => Render::names($record->progress()->mismatched))
                        ->helperText('Recorded, never reconciled. Some modules can only erase within one merchant and some can only erase everywhere; an answer that reached further or less far than the case asked is a fact, not an error to smooth over.')
                        ->columnSpanFull(),
                ]),

            Section::make('The case')
                ->columns(3)
                ->schema([
                    TextEntry::make('reference')->label('Case reference')->copyable(),
                    TextEntry::make('kind')
                        ->label('Kind')
                        ->badge()
                        ->formatStateUsing(fn (RequestKind $state): string => $state->label()),
                    TextEntry::make('scope')
                        ->label('Reach')
                        ->badge()
                        ->formatStateUsing(fn (RequestScope $state): string => $state->label())
                        ->helperText('How far the case reaches. It is a stored field because the fleet genuinely disagrees, and it is never inferred from an absent merchant.'),
                    TextEntry::make('subject_ref')
                        ->label('Subject')
                        ->copyable()
                        ->helperText('An opaque reference. This module never resolves it to a name, and it is not searchable or filterable anywhere in this panel.'),
                    TextEntry::make('tenant_id')
                        ->label('Raised at merchant')
                        ->helperText('Where the case was raised. A case that reaches beyond this merchant still has one.'),
                    TextEntry::make('lawful_basis')
                        ->label('Lawful basis')
                        ->formatStateUsing(fn (LawfulBasis $state): string => $state->label()),
                    TextEntry::make('channel')->label('Arrived by'),
                    TextEntry::make('requested_by_ref')->label('Asked by')->placeholder('The subject'),
                    TextEntry::make('reauthenticated_via')
                        ->label('Re-authenticated by')
                        ->placeholder(Render::NONE)
                        ->helperText('How the host satisfied itself the request came from the subject. A name, never the material: no password, token or second factor is stored by this module.'),
                    TextEntry::make('reason')->label('Reason given')->placeholder(Render::NONE)->columnSpanFull(),
                ]),

            Section::make('Times')
                ->description('Two times, because they answer different questions, and a deadline that is a date in a jurisdiction rather than an instant.')
                ->columns(4)
                ->schema([
                    TextEntry::make('requested_at')
                        ->label('Person asked')
                        ->dateTime()
                        ->helperText('The regulatory clock runs from here.'),
                    TextEntry::make('recorded_at')
                        ->label('We recorded it')
                        ->dateTime()
                        ->helperText('The support conversation runs from here.'),
                    TextEntry::make('deadline')
                        ->label('Deadline')
                        ->state(fn (PrivacyRequest $record): string => Render::deadline($record))
                        ->color(fn (PrivacyRequest $record): string => Render::isLate($record) ? 'danger' : 'gray'),
                    TextEntry::make('concluded_at')->label('Concluded')->dateTime()->placeholder('Still open'),
                ]),

            Section::make('Export')
                ->description('Assembled from what participants contributed, never streamed out of a table. This panel shows whether it is whole; the file itself is delivered to the subject through the API.')
                ->visible(fn (PrivacyRequest $record): bool => $record->kind === RequestKind::Access)
                ->columns(2)
                ->schema([
                    TextEntry::make('export_complete')
                        ->label('Whole')
                        ->badge()
                        ->state(fn (PrivacyRequest $record): string => self::exportIsComplete($record) ? 'Every participant contributed' : 'Partial')
                        ->color(fn (PrivacyRequest $record): string => self::exportIsComplete($record) ? 'success' : 'warning'),
                    TextEntry::make('export_missing')
                        ->label('Contributed nothing')
                        ->state(fn (PrivacyRequest $record): string => Render::names(self::exportMissing($record))),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('Case')
                    // The one searchable column, and it is our own case number:
                    // the string the person is told to quote. The subject
                    // reference below is not, because a search term lands in
                    // the query string and stays in a browser's history.
                    ->searchable()
                    ->copyable(),
                TextColumn::make('kind')
                    ->label('Kind')
                    ->badge()
                    ->formatStateUsing(fn (RequestKind $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('scope')
                    ->label('Reach')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (RequestScope $state): string => $state->label()),
                TextColumn::make('state')
                    ->label('State')
                    ->badge()
                    ->formatStateUsing(fn (RequestState $state): string => $state->label())
                    ->color(fn (RequestState $state): string => Render::stateColour($state))
                    ->sortable(),
                TextColumn::make('answered')
                    ->label('Answered')
                    ->state(fn (PrivacyRequest $record): string => Render::progress($record->progress()))
                    ->badge()
                    ->color(fn (PrivacyRequest $record): string => $record->progress()->isSatisfied() ? 'success' : 'warning'),
                TextColumn::make('outstanding')
                    ->label('Have not completed')
                    ->state(fn (PrivacyRequest $record): string => Render::names($record->progress()->outstanding))
                    ->wrap(),
                TextColumn::make('subject_ref')
                    ->label('Subject')
                    ->limit(16),
                TextColumn::make('deadline_date')
                    ->label('Deadline')
                    ->state(fn (PrivacyRequest $record): string => Render::deadline($record))
                    ->color(fn (PrivacyRequest $record): string => Render::isLate($record) ? 'danger' : 'gray')
                    ->sortable(),
                TextColumn::make('requested_at')->label('Asked')->dateTime()->sortable(),
            ])
            ->filters([
                // Three filters, all of them the desk's own vocabulary. Nothing
                // a person supplied is filterable: filter state persists into
                // the query string exactly as a search term does.
                SelectFilter::make('state')
                    ->label('State')
                    ->options(fn (): array => self::labelled(RequestState::cases())),
                SelectFilter::make('kind')
                    ->label('Kind')
                    ->options(fn (): array => self::labelled(RequestKind::cases())),
                // How a cross-tenant case is found. Never by a null tenant.
                SelectFilter::make('scope')
                    ->label('Reach')
                    ->options(fn (): array => self::labelled(RequestScope::cases())),
            ])
            ->defaultSort('requested_at', 'desc')
            ->toolbarActions([]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListPrivacyRequests::route('/'),
            'view' => ViewPrivacyRequest::route('/{record}'),
        ];
    }

    /**
     * @param  array<int, RequestState|RequestKind|RequestScope>  $cases
     * @return array<string, string>
     */
    private static function labelled(array $cases): array
    {
        $options = [];

        foreach ($cases as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * The kind guard is restated here rather than left to the section's own
     * visibility. `AssembleExport` refuses an erasure case by design — an
     * erasure has no export to assemble — and a state closure that ran before
     * the section resolved its visibility would raise `WrongRequestKind` from a
     * screen that was never going to show the answer.
     */
    private static function exportIsComplete(PrivacyRequest $record): bool
    {
        return $record->kind === RequestKind::Access
            && (new AssembleExport())($record->reference)->complete;
    }

    /** @return list<string> */
    private static function exportMissing(PrivacyRequest $record): array
    {
        return $record->kind === RequestKind::Access
            ? (new AssembleExport())($record->reference)->missing
            : [];
    }
}
