<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament\Resources\SavedLists;

use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\CustomerAccounts\Filament\Concerns\DeniesUnpublishedResourceAbilities;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\SavedLists\Pages\ListSavedLists;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\SavedLists\Pages\ViewSavedList;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\SavedLists\RelationManagers\SavedListItemsRelationManager;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\SavedLists\RelationManagers\SavedListSharesRelationManager;
use Liberu\Ecommerce\CustomerAccounts\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CustomerAccounts\Models\SavedList;
use UnitEnum;

/**
 * A person's lists at this merchant. Read, and only read.
 *
 * The lists are the shopper's, not the merchant's, and the shopper's own surface
 * is the `-livewire` package. This resource exists so a support conversation
 * ("my shared link stopped working", "my list is empty since you migrated") has
 * something to look at, and for no other reason — so nothing here creates,
 * renames, empties or shares a list, and nothing revokes a share. Revoking
 * somebody's share on their behalf is a decision about their property, and if a
 * host wants an operator to be able to make it, it is a `0.2.0` conversation
 * with a reason recorded against it rather than a button added quietly.
 *
 * **No price appears anywhere on these screens**, because none exists: a saved
 * list holds product references and quantities, and the price at the moment
 * something was saved is not a fact this module may assert. A list survives a
 * repricing; a cart does not. They are different aggregates and this module
 * builds only the first.
 *
 * Items and shares are on the record's own page rather than counted in this
 * table, and that is not a layout preference. `SavedList::items()` and
 * `shares()` both restate the tenant, casting the parent's id to a string so that
 * an unsaved parent yields an empty-string predicate matching nothing, rather
 * than an `is null` one matching every orphan. Eloquent builds a `withCount()` or
 * a `whereHas()` from a *fresh* model instance, so both would silently count zero
 * for every row. They fail closed, which is the right
 * direction, but they fail — so the counts live where the record is loaded.
 */
final class SavedListResource extends Resource
{
    use DeniesUnpublishedResourceAbilities;

    protected static ?string $model = SavedList::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'saved list';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bookmark';

    protected static ?string $navigationLabel = 'Saved lists';

    protected static UnitEnum|string|null $navigationGroup = 'Privacy desk';

    protected static ?int $navigationSort = 30;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /** @return Builder<SavedList> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', PanelTenant::current());
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [
            SavedListItemsRelationManager::class,
            SavedListSharesRelationManager::class,
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('List')
                ->columns(3)
                ->schema([
                    TextEntry::make('name')->label('Name'),
                    TextEntry::make('reference')->label('Reference')->copyable(),
                    TextEntry::make('owner_ref')
                        ->label('Owner')
                        ->helperText('An opaque reference. It is not searchable or filterable anywhere in this panel.'),
                    TextEntry::make('created_at')->label('Created')->dateTime(),
                    TextEntry::make('updated_at')->label('Last changed')->dateTime(),
                    TextEntry::make('live_shares')
                        ->label('Live shares')
                        ->state(fn (SavedList $record): int => $record->liveShares()->count()),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Name')->sortable(),
                TextColumn::make('reference')->label('Reference')->searchable()->copyable(),
                TextColumn::make('owner_ref')->label('Owner')->limit(16),
                TextColumn::make('created_at')->label('Created')->dateTime()->sortable(),
                TextColumn::make('updated_at')->label('Last changed')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            // No filters. Everything worth narrowing by here is either the
            // person or their words, and neither belongs in a query string.
            ->filters([])
            ->defaultSort('updated_at', 'desc')
            ->toolbarActions([]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListSavedLists::route('/'),
            'view' => ViewSavedList::route('/{record}'),
        ];
    }
}
