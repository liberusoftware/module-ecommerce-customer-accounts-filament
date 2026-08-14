<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipantStatus;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Queries\ListParticipants;
use UnitEnum;

/**
 * Who takes part, and which of them cannot answer.
 *
 * This is the screen that earns the panel. Every other view here is about a case
 * that already exists; this one is about the gap, and the gap is knowable before
 * anybody asks for anything. Read from the shipped repositories rather than
 * inferred: four modules in this fleet publish an erasure, one of those four
 * publishes no export, and **six modules that hold personal data publish
 * neither half**. A registry entry with nothing bound to it, or one that
 * publishes no half of privacy at all, turns every case it is on into a partial
 * one — silently, unless somebody is shown it here.
 *
 * The registry is explicit configuration and never discovery. There is no scan
 * of installed packages and no interface probe: a module takes part because a
 * human named it in `customer-accounts.participants`, which is what makes this
 * table reviewable and what makes an *absent* module a visible decision rather
 * than an invisible one. A participant nobody registered does not appear as a
 * gap here — it does not appear at all, and that is the failure this module
 * cannot detect for you. `docs/runbook.md` says registering a module is part of
 * installing it.
 *
 * **This module is not on this list.** A coordinator that appears in its own
 * registry reports its own erasure as one more green tick, and stops erasing
 * itself the moment somebody edits the entry out. Our rows are cleared directly
 * on every erasure conclusion, partial ones included.
 */
final class ParticipantRegistry extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'Participant registry';

    protected static UnitEnum|string|null $navigationGroup = 'Privacy desk';

    protected static ?int $navigationSort = 40;

    protected static ?string $title = 'Participant registry';

    protected static ?string $slug = 'customer-accounts/participant-registry';

    protected string $view = 'ecommerce-customer-accounts-filament::pages.participant-registry';

    public function getSubheading(): string
    {
        return 'The modules registered to answer a privacy request in this application, and which of them can actually answer one. '
            .'A registered module that is silent makes every case it is on completed in part. Customer Accounts itself is deliberately not on this list.';
    }

    public function table(Table $table): Table
    {
        return $table
            // The registry is configuration, not rows, so the table is fed from
            // the domain query rather than from a model. Arrays, deliberately:
            // there is nothing here to click into and nothing to write.
            ->records(fn (): Collection => collect(array_map(
                fn (ParticipantStatus $status): array => [
                    'name' => $status->name,
                    'label' => $status->label,
                    'bound' => $status->bound,
                    'silent' => $status->isSilent(),
                    'handles' => array_map(fn (RequestKind $kind): string => $kind->label(), $status->handles),
                ],
                (new ListParticipants())(),
            )))
            ->columns([
                TextColumn::make('name')->label('Module'),
                TextColumn::make('label')->label('Holds'),
                TextColumn::make('handles')
                    ->label('Publishes')
                    ->badge()
                    // A participant that declares nothing publishes nothing, and
                    // that is the pessimistic default on purpose: reading an
                    // entry with no `handles` as "handles everything" produces
                    // cases waiting forever on a module with no erasure to run.
                    ->placeholder('Neither an export nor an erasure'),
                IconColumn::make('bound')
                    ->label('Adapter bound')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('warning'),
                TextColumn::make('silent')
                    ->label('Can answer')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Silent' : 'Yes')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'success'),
            ])
            ->paginated(false)
            // Nothing is searchable, filterable or sortable here. The registry
            // is a short, ordered list somebody wrote, and its order is theirs.
            ->filters([])
            ->recordUrl(null)
            ->recordAction(null)
            ->recordActions([])
            ->headerActions([])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-exclamation-triangle')
            ->emptyStateHeading('No participants are registered')
            ->emptyStateDescription(
                'Every privacy request opened in this application will be unsatisfiable: a case with no participants asked nobody anything, '
                .'so it can never be completed and every erasure will conclude in part. Name the modules that hold personal data in the '
                .'`customer-accounts.participants` config before this desk is used.'
            );
    }
}
