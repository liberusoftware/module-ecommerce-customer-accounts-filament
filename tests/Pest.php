<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\CustomerAccounts\Actions\AddItemToSavedList;
use Liberu\Ecommerce\CustomerAccounts\Actions\CreateSavedList;
use Liberu\Ecommerce\CustomerAccounts\Actions\OpenPrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Actions\ShareSavedList;
use Liberu\Ecommerce\CustomerAccounts\Data\PrivacyRequestDraft;
use Liberu\Ecommerce\CustomerAccounts\Enums\Channel;
use Liberu\Ecommerce\CustomerAccounts\Enums\ClaimAttemptOutcome;
use Liberu\Ecommerce\CustomerAccounts\Enums\LawfulBasis;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestKind;
use Liberu\Ecommerce\CustomerAccounts\Enums\RequestScope;
use Liberu\Ecommerce\CustomerAccounts\Filament\Tests\Fakes\CompletingParticipant;
use Liberu\Ecommerce\CustomerAccounts\Filament\Tests\TestCase;
use Liberu\Ecommerce\CustomerAccounts\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CustomerAccounts\Models\ClaimAttempt;
use Liberu\Ecommerce\CustomerAccounts\Models\PrivacyRequest;
use Liberu\Ecommerce\CustomerAccounts\Models\SavedList;
use Liberu\Ecommerce\CustomerAccounts\Support\ClaimAttempts;
use Liberu\Ecommerce\CustomerAccounts\Support\Fingerprint;

uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(function (): void {
        TestTenant::reset();
        Config::set('customer-accounts.participants', []);
    })
    ->in(__DIR__.'/Feature');

/**
 * Register participants the way a host does: by hand, in config, by name.
 *
 * There is no discovery anywhere in this module, so there is nothing to fake —
 * a participant takes part because a line of configuration says so, and every
 * test that wants one writes that line.
 *
 * @param  array<string, array<string, mixed>>  $entries
 */
function registerParticipants(array $entries): void
{
    Config::set('customer-accounts.participants', $entries);
}

/**
 * The registry shape this fleet actually has: one module that answers, one
 * registered with nothing bound to it, and one that publishes no export at all.
 *
 * Two of the three are silent, which is not a pessimistic test fixture — four
 * modules in this fleet publish an erasure and six publish neither half.
 */
function registerTheUsualThree(): void
{
    registerParticipants([
        'commerce-customers' => [
            'label' => 'Customer files',
            'adapter' => CompletingParticipant::class,
            'handles' => ['access', 'erasure'],
        ],
        'orders' => [
            'label' => 'Orders',
            'adapter' => null,
            'handles' => ['access', 'erasure'],
        ],
        'promotions' => [
            'label' => 'Offers and redemptions',
            'adapter' => CompletingParticipant::class,
            'handles' => ['erasure'],
        ],
    ]);
}

/** A case, opened through the published action rather than inserted. */
function openCase(
    RequestKind $kind = RequestKind::Erasure,
    string $tenantId = TestTenant::PRIMARY,
    string $subjectRef = 'person-1',
    RequestScope $scope = RequestScope::Tenant,
    ?CarbonImmutable $requestedAt = null,
    ?int $deadlineDays = null,
): PrivacyRequest {
    return app(OpenPrivacyRequest::class)(new PrivacyRequestDraft(
        tenantId: $tenantId,
        subjectRef: $subjectRef,
        kind: $kind,
        scope: $scope,
        lawfulBasis: LawfulBasis::LegalObligation,
        requestedAt: $requestedAt ?? CarbonImmutable::now(),
        requestedByRef: 'actor:desk',
        channel: Channel::Desk,
        reason: 'Asked at the counter.',
        // Required for an erasure and harmless on an access request: the module
        // records *that* the host re-authenticated and by what means, never the
        // material.
        reauthenticatedVia: 'identity documents at the desk',
        deadlineDays: $deadlineDays,
    ));
}

/** One attempt at a claim, recorded the way the domain records one. */
function recordAttempt(
    string $orderReference = 'ord-1001',
    ClaimAttemptOutcome $outcome = ClaimAttemptOutcome::Refused,
    string $tenantId = TestTenant::PRIMARY,
    string $email = 'someone@example.test',
    ?string $claimReference = null,
    ?CarbonImmutable $at = null,
): ClaimAttempt {
    return (new ClaimAttempts())->record(
        $tenantId,
        $orderReference,
        Fingerprint::of($email),
        $claimReference,
        $outcome,
        Channel::Web,
        $at ?? CarbonImmutable::now(),
    );
}

/** A list with one item and one live share, all through published actions. */
function savedListWithItems(
    string $tenantId = TestTenant::PRIMARY,
    string $ownerRef = 'person-1',
    string $name = 'Christmas',
    bool $shared = true,
): SavedList {
    $list = app(CreateSavedList::class)($tenantId, $ownerRef, $name);

    app(AddItemToSavedList::class)($list->reference, 'prod-1', 2, 'the blue one');

    if ($shared) {
        app(ShareSavedList::class)($list->reference);
    }

    return $list->refresh();
}
