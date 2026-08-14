<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament\Support;

use Carbon\CarbonImmutable;
use Liberu\Ecommerce\CustomerAccounts\Data\RequestProgress;
use Liberu\Ecommerce\CustomerAccounts\Enums\ClaimAttemptOutcome;
use Liberu\Ecommerce\CustomerAccounts\Enums\ParticipantOutcome;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestState;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequest;

/**
 * The sentences this panel is most likely to get wrong, written once so it
 * cannot write them differently on two screens.
 *
 * The module's shaping fact is that the host answered an erasure request with
 * `{"success": true}` from a function that wrote ten tables and had never heard
 * of six of the modules holding the person's data. Every rendering rule here
 * exists to stop the panel restating that: a case is "3 of 5 answered", never
 * "done"; a registry with nobody in it is a defect, not an empty table.
 */
final class Render
{
    public const NO_PARTICIPANTS = 'No participants registered';

    public const NOT_ANSWERED = 'Not yet asked';

    public const NONE = '—';

    /**
     * "3 of 5 answered", and never a bare tick.
     *
     * Only `completed` counts. Unavailable and failed are answers a participant
     * gave, but neither is its rows being dealt with, and a numerator that
     * included them would be the false assurance in arithmetic form.
     *
     * A case with no participants reads as the defect it is rather than as 0 of
     * 0, which rounds to "nothing left to do".
     */
    public static function progress(RequestProgress $progress): string
    {
        if ($progress->participants === 0) {
            return self::NO_PARTICIPANTS;
        }

        return $progress->completed.' of '.$progress->participants.' answered';
    }

    /** A list of participant names, or an em dash. Never an empty string. */
    public static function names(array $names): string
    {
        return $names === [] ? self::NONE : implode(', ', $names);
    }

    /**
     * A date and the zone it is counted in, because a statutory period is a
     * calendar answer in a jurisdiction and not an instant.
     */
    public static function deadline(PrivacyRequest $request): string
    {
        $deadline = $request->deadline();

        return $deadline->date.' ('.$deadline->timezone.')';
    }

    /**
     * Whether the deadline has passed on a case still running.
     *
     * **Nothing acts on this.** There is no sweeper, nothing auto-concludes and
     * no queue is watching — a late case is late until a person looks at it,
     * which is why the desk shows it rather than trusting a job that does not
     * exist to have handled it.
     */
    public static function isLate(PrivacyRequest $request, ?CarbonImmutable $at = null): bool
    {
        if ($request->state->isConcluded()) {
            return false;
        }

        return $request->deadline()->hasPassed($at ?? CarbonImmutable::now());
    }

    public static function stateColour(RequestState $state): string
    {
        return match ($state) {
            RequestState::Completed => 'success',
            // Partial is a warning and not a success, on every screen. It is
            // the state the host could not represent at all.
            RequestState::Partial => 'warning',
            RequestState::Withdrawn => 'gray',
            RequestState::InProgress => 'info',
            RequestState::Open => 'gray',
        };
    }

    public static function outcomeColour(ParticipantOutcome $outcome): string
    {
        return match ($outcome) {
            ParticipantOutcome::Completed => 'success',
            ParticipantOutcome::Unavailable => 'warning',
            ParticipantOutcome::Failed => 'danger',
            ParticipantOutcome::Pending => 'gray',
        };
    }

    public static function attemptColour(ClaimAttemptOutcome $outcome): string
    {
        return $outcome->isFailure() ? 'danger' : 'success';
    }
}
