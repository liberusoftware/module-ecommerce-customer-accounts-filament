<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament\Resources\PrivacyRequests\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\CustomerAccounts\Actions\ConcludePrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Actions\WithdrawPrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestState;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\CustomerAccountsException;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\PrivacyRequests\PrivacyRequestResource;
use Liberu\Ecommerce\CustomerAccounts\Filament\Support\Render;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequest;

/**
 * One case, its progress, and the two things an operator may do to it.
 *
 * Both are honest about what they are. **Concluding cannot force a case to
 * `Completed`**: if every participant completed, the case concluded itself when
 * the last one answered and this is refused as already concluded; if any did
 * not, the answer is `Partial` and the outstanding participants are named. There
 * is no override, because an override is the button somebody presses to make a
 * report look finished.
 *
 * **Withdrawal is available until the case concludes, and it is not a
 * cooling-off period.** No such period exists in this module — there is no clock
 * and nothing that would run one — so nothing on this page says "you can still
 * cancel". Withdrawal records that the person stopped asking; it does not undo
 * an erasure a participant already ran, and its modal says so.
 */
final class ViewPrivacyRequest extends ViewRecord
{
    protected static string $resource = PrivacyRequestResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('conclude')
                ->label('Conclude this case')
                ->icon('heroicon-o-flag')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Conclude this case')
                ->modalDescription(fn (PrivacyRequest $record): string => $record->progress()->isSatisfied()
                    ? 'Every participant completed, so this case will be recorded as completed.'
                    : 'Not every participant completed. This case will be recorded as completed in part, naming '.Render::names($record->progress()->outstanding).'. It cannot be recorded as completed, and there is no way to make it say so.')
                ->visible(fn (PrivacyRequest $record): bool => ! $record->state->isConcluded())
                ->action(function (PrivacyRequest $record): void {
                    try {
                        $concluded = App::make(ConcludePrivacyRequest::class)($record->reference);
                    } catch (CustomerAccountsException $e) {
                        Notification::make()->title('Not concluded')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title($concluded->state->label())
                        ->body($concluded->state === RequestState::Partial
                            ? 'Recorded as completed in part. These participants did not complete: '.Render::names($concluded->progress()->outstanding).'.'
                            : 'Every registered participant completed.')
                        ->color(Render::stateColour($concluded->state))
                        ->send();

                    $this->refreshFormData(['state', 'concluded_at']);
                }),

            Action::make('withdraw')
                ->label('Record a withdrawal')
                ->icon('heroicon-o-hand-raised')
                ->color('gray')
                ->modalHeading('The subject withdrew this request')
                ->modalDescription('Stops further participants being commissioned and records that the person stopped asking, which is a different fact from a case that failed. It does not undo anything a participant already did — an erasure that ran is not coming back.')
                ->schema([
                    Textarea::make('reason')->label('What they said')->maxLength(255),
                ])
                ->visible(fn (PrivacyRequest $record): bool => ! $record->state->isConcluded())
                ->action(function (PrivacyRequest $record, array $data): void {
                    $reason = is_string($data['reason'] ?? null) && $data['reason'] !== '' ? (string) $data['reason'] : null;

                    try {
                        App::make(WithdrawPrivacyRequest::class)($record->reference, $reason);
                    } catch (CustomerAccountsException $e) {
                        Notification::make()->title('Not withdrawn')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('Withdrawn')->body('Recorded. No further participant will be commissioned on this case.')->success()->send();

                    $this->refreshFormData(['state', 'concluded_at']);
                }),
        ];
    }
}
