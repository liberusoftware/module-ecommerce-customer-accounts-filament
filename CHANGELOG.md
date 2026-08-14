# Changelog

All notable changes to `liberusoftware/ecommerce-customer-accounts-filament` are
documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this package uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.1.0 — 2026-08-14

The privacy desk over
[`liberusoftware/ecommerce-customer-accounts`](https://github.com/liberusoftware/module-ecommerce-customer-accounts)
`0.1.0`.

### Added

- **`CustomerAccountsPlugin`** — the one entry point. The host attaches it per
  panel; the service provider registers no resource, page or widget.
- **`PrivacyRequestResource`** — cases with their reach, deadline, state and a
  participant count read as "3 of 5 answered". `ListPrivacyRequests` records a
  request that arrived by post, telephone or at a counter through
  `OpenPrivacyRequest`; `ViewPrivacyRequest` concludes and records a withdrawal.
- **`ParticipantsRelationManager`** — every participant of a case with its
  answer, its stated reason, the scope it applied and whether that mismatched
  what was asked, **including the participants that answered nothing**. Per-row
  re-commission through `CommissionParticipant`. No filters, deliberately.
- **`ParticipantRegistry`** page — which modules are registered and which of them
  are silent, read from `ListParticipants`. An empty registry renders as a
  defect rather than as an empty table.
- **`PrivacyDeskOverview`** widget — cases running, cases past their deadline,
  cases completed in part, and silent participants.
- **`ClaimAttemptResource`** — failed guest-order claim attempts, defaulting to
  the failures. No token and no email fingerprint appears anywhere.
- **`SavedListResource`** with **`SavedListItemsRelationManager`** and
  **`SavedListSharesRelationManager`** — read-only, no price, tokens never shown.
- Every unpublished Filament ability forced false by name, per resource and per
  relation manager, `canAssociate` and `canDissociate` included.

### Notes

- `filament/filament: ^5.6` is a **choice**. The true Laravel-13 floor is `^5.4`:
  `v5.3.0` caps at `illuminate/contracts: ^11.28|^12.0` and `v5.4.0` is the first
  to add `^13.0`, which `filament/filament` inherits through `self.version`.
- Nothing in this package schedules anything, concludes a case on its own, or
  offers a cooling-off window — none of the three exists in the domain.
