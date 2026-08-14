<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament\Resources\PrivacyRequests\RelationManagers;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\CustomerAccounts\Actions\CommissionParticipant;
use Liberu\Ecommerce\CustomerAccounts\Enums\ParticipantOutcome;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;
use Liberu\Ecommerce\CustomerAccounts\Filament\Concerns\DeniesUnpublishedRelationAbilities;
use Liberu\Ecommerce\CustomerAccounts\Filament\Support\Render;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequestParticipant;

/**
 * Every participant of this case, and what each one answered — **including the
 * ones that answered nothing**.
 *
 * This table is the module. A panel that listed only the participants that
 * answered would make every case look complete, and a case that looks complete
 * is the false assurance the host shipped: `{"success": true}` from a function
 * that had never heard of six of the modules holding the person's data.
 *
 * So three things are deliberate and none of them are style:
 *
 * **There are no filters.** A filter here is a way to hide the participants that
 * did not answer, which is precisely the view that must not exist. The rows are
 * all of them, always, in the order the domain lists them.
 *
 * **`Unavailable` is a first-class answer with a stated reason, not an absence.**
 * A module that publishes an erasure but no export — Promotions is the live case
 * — is registered, commissioned, and answers unavailable saying so. It is the
 * majority case in this fleet: four modules publish an erasure, six publish
 * neither half. The reason column is why an operator can tell "nobody bound an
 * adapter" from "this module has no export to give" from "it threw".
 *
 * **The retry writes to our own case and to nothing else.** "Ask again"
 * re-commissions the participant through `CommissionParticipant`, which calls
 * the one contract this package publishes and lets the host's adapter decide
 * what that means. Nothing in this panel reaches into another module's tables to
 * re-run its erasure, and there is no button that marks a participant answered.
 *
 * This module does not appear in this table. A coordinator that is a participant
 * of its own registry reports its own erasure as one more green tick, and stops
 * erasing itself the moment somebody edits the entry out; our own rows are
 * cleared on every erasure conclusion, partial ones included, without being
 * counted.
 */
final class ParticipantsRelationManager extends RelationManager
{
    use DeniesUnpublishedRelationAbilities;

    protected static string $relationship = 'participants';

    protected static ?string $title = 'Participants and their answers';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('participant')->label('Module')->sortable(),
                TextColumn::make('label')->label('Holds'),
                TextColumn::make('outcome')
                    ->label('Answer')
                    ->badge()
                    ->formatStateUsing(fn (ParticipantOutcome $state): string => $state->label())
                    ->color(fn (ParticipantOutcome $state): string => Render::outcomeColour($state)),
                TextColumn::make('note')
                    ->label('Reason given')
                    ->placeholder(Render::NONE)
                    ->wrap(),
                TextColumn::make('scope_applied')
                    ->label('Reached')
                    ->placeholder(Render::NONE)
                    ->formatStateUsing(fn (RequestScope $state): string => $state->label()),
                IconColumn::make('scope_mismatch')
                    ->label('Scope mismatch')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-minus-small')
                    ->falseColor('gray'),
                TextColumn::make('attempts')->label('Times asked'),
                TextColumn::make('answered_at')->label('Answered')->dateTime()->placeholder(Render::NOT_ANSWERED),
            ])
            // No filters. See the class docblock: the only thing a filter buys
            // on this table is a way to stop looking at the participants that
            // did not answer.
            ->filters([])
            ->defaultSort('participant')
            // Read-only rows with nothing to open. A participant row has no page
            // of its own and this manager publishes no view ability, so a
            // clickable row would offer a route it is not authorized for.
            ->recordUrl(null)
            ->recordAction(null)
            ->recordActions([
                Action::make('commission')
                    ->label(fn (PrivacyRequestParticipant $record): string => $record->outcome === ParticipantOutcome::Pending ? 'Ask' : 'Ask again')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Ask this participant again')
                    ->modalDescription('Re-runs this module\'s own answer through the adapter the host bound for it, and records whatever comes back. It writes nothing in that module\'s tables from here, and it cannot mark the participant answered — only the participant can do that.')
                    ->visible(fn (PrivacyRequestParticipant $record): bool => $record->outcome->isRetryable() && ! $this->caseIsConcluded())
                    ->action(function (PrivacyRequestParticipant $record): void {
                        $case = $this->getOwnerRecord();

                        if (! $case instanceof PrivacyRequest) {
                            return;
                        }

                        $answered = App::make(CommissionParticipant::class)($case->reference, $record->participant);

                        Notification::make()
                            ->title($answered->outcome->label())
                            ->body($answered->note ?? 'This participant answered for its own rows.')
                            ->color(Render::outcomeColour($answered->outcome))
                            ->send();
                    }),
            ])
            ->headerActions([])
            ->toolbarActions([]);
    }

    private function caseIsConcluded(): bool
    {
        $case = $this->getOwnerRecord();

        return $case instanceof PrivacyRequest && $case->state->isConcluded();
    }
}
