<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament\Tests\Fakes;

use Liberu\Ecommerce\CustomerAccounts\Contracts\ParticipatesInPrivacyRequests;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipationAnswer;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipationRequest;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;

/**
 * Commerce Customers' shape: it can only erase a person everywhere, because a
 * person is a person. Asked for one merchant, it answers `everywhere` and the
 * case records the mismatch.
 */
final class CrossTenantParticipant implements ParticipatesInPrivacyRequests
{
    public function participate(ParticipationRequest $request): ParticipationAnswer
    {
        return ParticipationAnswer::completed(RequestScope::Everywhere, ['files' => 1]);
    }
}
