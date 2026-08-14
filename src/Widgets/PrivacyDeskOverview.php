<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament\Widgets;

use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipantStatus;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestState;
use Liberu\Ecommerce\CustomerAccounts\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Queries\ListParticipants;

/**
 * The four numbers a privacy desk is opened to see, and one of them is not
 * about cases at all.
 *
 * **Silent participants** is the stat that earns this widget. A module that is
 * registered and cannot answer — nothing bound to its adapter, or it publishes
 * no half of privacy at all — turns every case it is on into a partial one, and
 * that is knowable *before* anybody asks. Six of the modules in this fleet that
 * hold personal data publish neither an erasure nor an export. An operator who
 * learns that from a case is learning it too late; this is where they learn it
 * on a Tuesday morning instead.
 *
 * **Nothing here schedules anything.** "Past its deadline" is a count of cases
 * somebody has to look at, not a queue something is working through: there is no
 * sweeper, nothing auto-concludes, and a late case stays late.
 */
final class PrivacyDeskOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $tenant = PanelTenant::current();

        $running = PrivacyRequest::query()
            ->where('tenant_id', $tenant)
            ->whereIn('state', [RequestState::Open->value, RequestState::InProgress->value])
            ->get();

        $partial = PrivacyRequest::query()
            ->where('tenant_id', $tenant)
            ->where('state', RequestState::Partial->value)
            ->count();

        // Folded in PHP rather than compared in SQL, because a deadline is a
        // date in a named timezone and "has today passed there" is not a
        // comparison the database can make against a stored date string.
        //
        // ponytail: loads every running case for this merchant. A desk holding
        // tens of thousands of open cases wants a stored UTC end-of-day column
        // to pre-filter on; nothing in this module has one yet, and adding one
        // would be a second source of truth about a deadline.
        $now = CarbonImmutable::now();
        $late = $running->filter(fn (PrivacyRequest $request): bool => $request->deadline()->hasPassed($now))->count();

        $participants = (new ListParticipants())();
        $silent = array_values(array_filter($participants, fn (ParticipantStatus $p): bool => $p->isSilent()));

        return [
            Stat::make('Cases running', (string) $running->count())
                ->description('Open or in progress at this merchant')
                ->color('info'),

            Stat::make('Past their deadline', (string) $late)
                ->description('Nothing concludes these on its own')
                ->color($late > 0 ? 'danger' : 'gray'),

            Stat::make('Completed in part', (string) $partial)
                ->description('Concluded with a participant that did not complete')
                ->color($partial > 0 ? 'warning' : 'gray'),

            Stat::make('Silent participants', count($silent).' of '.count($participants))
                ->description($silent === []
                    ? 'Every registered participant can answer'
                    : 'Registered and unable to answer: '.implode(', ', array_map(fn (ParticipantStatus $p): string => $p->name, $silent)))
                ->color($silent === [] ? 'success' : 'warning'),
        ];
    }
}
