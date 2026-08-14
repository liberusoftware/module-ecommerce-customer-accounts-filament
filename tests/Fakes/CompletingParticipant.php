<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerAccounts\Filament\Tests\Fakes;

use Liberu\Ecommerce\CustomerAccounts\Contracts\ParticipatesInPrivacyRequests;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipationAnswer;
use Liberu\Ecommerce\CustomerAccounts\Data\ParticipationRequest;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;

/**
 * A module that answered for its own rows — the only one of the three shapes in
 * this folder that leaves a case able to be called done.
 *
 * It states the scope it actually applied, which is how a mismatch against what
 * the case asked for becomes a recorded fact rather than a reconciliation.
 */
final class CompletingParticipant implements ParticipatesInPrivacyRequests
{
    public function __construct(private readonly RequestScope $applies = RequestScope::Tenant) {}

    public function participate(ParticipationRequest $request): ParticipationAnswer
    {
        return ParticipationAnswer::completed(
            $this->applies,
            ['rows' => 3],
            ['profile' => ['name' => 'redacted']],
        );
    }
}
