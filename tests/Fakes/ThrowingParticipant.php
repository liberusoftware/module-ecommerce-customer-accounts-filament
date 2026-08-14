<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament\Tests\Fakes;

use Liberu\Ecommerce\CustomerAccounts\Contracts\ParticipatesInPrivacyRequests;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipationAnswer;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipationRequest;
use RuntimeException;

/** A module that answered, badly. Recorded as failed and retryable, never lost. */
final class ThrowingParticipant implements ParticipatesInPrivacyRequests
{
    public function participate(ParticipationRequest $request): ParticipationAnswer
    {
        throw new RuntimeException('the upstream service refused the connection');
    }
}
