<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament\Resources\ClaimAttempts\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Liberu\Ecommerce\CustomerAccounts\Filament\Resources\ClaimAttempts\ClaimAttemptResource;

/**
 * A feed, with nothing to press.
 *
 * There is no "grant this claim anyway" and no "clear the rate limit". Both
 * would be an operator overriding proof-of-possession on behalf of somebody who
 * failed to prove it, which is the attack the token exists to stop rather than
 * an inconvenience to be smoothed over.
 */
final class ListClaimAttempts extends ListRecords
{
    protected static string $resource = ClaimAttemptResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
