<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament\Resources\PrivacyRequests\Pages;

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Liberu\Ecommerce\CustomerAccounts\Actions\OpenPrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Data\PrivacyRequestDraft;
use Liberu\Ecommerce\CustomerAccounts\Enums\Channel;
use Liberu\Ecommerce\CustomerAccounts\Enums\LawfulBasis;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;
use Liberu\Ecommerce\CustomerAccounts\Exceptions\CustomerAccountsException;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\PrivacyRequests\PrivacyRequestResource;
use Liberu\Ecommerce\CustomerAccounts\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CustomerAccounts\Filament\Widgets\PrivacyDeskOverview;

final class ListPrivacyRequests extends ListRecords
{
    protected static string $resource = PrivacyRequestResource::class;

    /** @return array<class-string> */
    protected function getHeaderWidgets(): array
    {
        return [
            PrivacyDeskOverview::class,
        ];
    }

    /**
     * One header action: record a case that arrived somewhere this application
     * has no form for.
     *
     * A request that comes by post, by telephone or over a counter is why
     * `Channel` has `Post`, `Phone` and `Desk` in it, and the desk is the only
     * surface that can record one. Everything the case needs is typed here and
     * handed to `OpenPrivacyRequest`; nothing is erased or exported by opening
     * it, which is the correction of a host that did all ten tables inside the
     * HTTP request that asked.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('openCase')
                ->label('Record a request')
                ->icon('heroicon-o-plus')
                ->modalHeading('Record a privacy request')
                ->modalDescription('Opens the case and gives every registered participant a row, before anybody is asked anything. Nothing is erased or exported here — participants are commissioned afterwards, one retryable step each.')
                ->schema([
                    TextInput::make('subject_ref')
                        ->label('Subject reference')
                        ->required()
                        ->maxLength(64)
                        ->helperText('The opaque reference for the person. Never an email address and never a login id.'),
                    Select::make('kind')
                        ->label('Kind')
                        ->required()
                        ->live()
                        ->default(RequestKind::Access->value)
                        ->options(fn (): array => self::options(RequestKind::cases())),
                    Select::make('scope')
                        ->label('Reach')
                        ->required()
                        ->default(RequestScope::Tenant->value)
                        ->options(fn (): array => self::options(RequestScope::cases()))
                        ->helperText('Whether the case covers this merchant only or the person wherever this deployment holds them. Participants record which of the two they actually applied.'),
                    Select::make('lawful_basis')
                        ->label('Lawful basis')
                        ->required()
                        ->default(LawfulBasis::LegalObligation->value)
                        ->options(fn (): array => self::options(LawfulBasis::cases())),
                    Select::make('channel')
                        ->label('Arrived by')
                        ->required()
                        ->default(Channel::Desk->value)
                        ->options(fn (): array => self::channels()),
                    DateTimePicker::make('requested_at')
                        ->label('When the person asked')
                        ->required()
                        ->default(fn (): string => CarbonImmutable::now()->toDateTimeString())
                        ->helperText('The regulatory clock runs from this moment, not from when it was typed in — a letter dated last Tuesday was asked last Tuesday.'),
                    TextInput::make('reauthenticated_via')
                        ->label('Re-authenticated by')
                        ->maxLength(64)
                        ->required(fn (Get $get): bool => $get('kind') === RequestKind::Erasure->value)
                        ->helperText('How you satisfied yourself this is the subject — "identity documents at the desk", "re-login". A name, never the material: do not type a password, a token or a code here.'),
                    Textarea::make('reason')
                        ->label('Reason given')
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $actor = Auth::id();
                    $reason = is_string($data['reason'] ?? null) && $data['reason'] !== '' ? (string) $data['reason'] : null;
                    $reauth = is_string($data['reauthenticated_via'] ?? null) && $data['reauthenticated_via'] !== ''
                        ? (string) $data['reauthenticated_via']
                        : null;

                    $draft = new PrivacyRequestDraft(
                        tenantId: PanelTenant::current(),
                        subjectRef: (string) $data['subject_ref'],
                        kind: RequestKind::from((string) $data['kind']),
                        scope: RequestScope::from((string) $data['scope']),
                        lawfulBasis: LawfulBasis::from((string) $data['lawful_basis']),
                        // A picker hands back a string. Parsed here rather than
                        // passed through, because a `TypeError` inside a
                        // Livewire component method is swallowed whole and
                        // reads as a session problem.
                        requestedAt: CarbonImmutable::parse((string) $data['requested_at']),
                        requestedByRef: $actor === null ? null : 'actor:'.$actor,
                        channel: Channel::from((string) $data['channel']),
                        reason: $reason,
                        reauthenticatedVia: $reauth,
                    );

                    try {
                        $request = App::make(OpenPrivacyRequest::class)($draft);
                    } catch (CustomerAccountsException $e) {
                        Notification::make()
                            ->title('The case was not opened')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    $progress = $request->progress();

                    Notification::make()
                        ->title('Case '.$request->reference.' opened')
                        ->body($progress->participants === 0
                            ? 'No participants are registered, so this case cannot be satisfied by anyone. Register the modules that hold this person’s data before commissioning it.'
                            : $progress->participants.' participants have a row on this case. None of them has been asked yet.')
                        ->color($progress->participants === 0 ? 'danger' : 'success')
                        ->send();
                }),
        ];
    }

    /**
     * @param  array<int, RequestKind|RequestScope|LawfulBasis>  $cases
     * @return array<string, string>
     */
    private static function options(array $cases): array
    {
        $options = [];

        foreach ($cases as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /** @return array<string, string> */
    private static function channels(): array
    {
        $options = [];

        foreach (Channel::cases() as $channel) {
            $options[$channel->value] = ucfirst($channel->value);
        }

        return $options;
    }
}
