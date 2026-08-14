# What is on each screen, and why

Four screens and one widget, all under one navigation group: **Privacy desk**.

| Screen | What it is | What it refuses to say |
| --- | --- | --- |
| Privacy requests | Cases raised at this merchant, with reach, deadline, state and "3 of 5 answered" | "Done", when a participant did not complete |
| A case | Its progress, its participants, its times, and its export's wholeness | That anything will conclude it on its own |
| Participant registry | Which modules are registered and which are silent | That an empty registry is an empty table |
| Claim attempts | Failed guest-order claims, defaulting to the failures | Which half of the evidence was wrong |
| Saved lists | Lists, items and share links, read-only | What anything cost |

## Privacy requests

The list shows the cases **raised at this merchant**. `tenant_id` is NOT NULL on
every table in the domain and means where the case was raised; how far it reaches
is `RequestScope`, which is a column and a filter. **A cross-tenant case is never
found by a null tenant** — the predicate is unconditional, and a case raised
somewhere else that reached this person everywhere is still that merchant's case
to work.

The `Answered` column counts **only** `completed`. Unavailable and failed are
answers a participant gave, and neither is its rows being dealt with; a numerator
including them would be the false assurance in arithmetic form. Beside it,
`Have not completed` names them rather than counting them.

`reference` is the one searchable column anywhere in this panel. It is our own
minted case number — the string a person is told to quote — and identifies a case
rather than a person. `subject_ref` is not searchable and not filterable: a search
term and a filter's state both persist into the query string, and from there into
browser history, bookmarks and pasted links.

### Recording a request

`Record a request` on the list page opens a case through `OpenPrivacyRequest`.
It exists because `Channel` has `Post`, `Phone` and `Desk` in it and no other
surface in the fleet can serve those. Opening a case **erases and exports
nothing**: every registered participant gets a row before anybody is asked
anything, which is what makes a partial case representable at all.

An erasure will not open without a recorded `reauthenticated_via`. That is a name
— "identity documents at the desk", "re-login" — and never material. The domain
refuses it and the form requires it; both, because the form is a courtesy and the
domain is the guarantee.

### Concluding and withdrawing

**Concluding cannot force `Completed`.** If every participant completed, the case
concluded itself when the last one answered. If any did not, the answer is
`Partial` and the modal names who. There is no override, because an override is
the button somebody presses to make a report look finished.

**Withdrawal is available until the case concludes, and it is not a cooling-off
period.** No such period exists in this module — there is no clock and nothing to
run one — so nothing here says "you can still cancel". Withdrawal records that
the person stopped asking; it does not undo an erasure that already ran.

### The deadline

A deadline is a date and a timezone, never an instant, because a statutory
response period is a calendar answer in a jurisdiction. A passed deadline shows
in red on the case and is counted on the widget. **That is all that happens to
it.** There is no sweeper, nothing auto-concludes, and a late case stays late
until a person opens it. The runbook says the host schedules whatever it wants to
schedule.

## A case's participants

This table is the module. A panel listing only the participants that answered
would make every case look complete, and a case that looks complete is the false
assurance the host shipped: `{"success": true}` from a function that had never
heard of six of the modules holding the person's data.

- **There are no filters.** A filter here is a way to stop looking at the
  participants that did not answer.
- **`Unavailable` is an answer with a reason, not an absence.** A module that
  publishes an erasure and no export is registered, commissioned, and answers
  unavailable saying so — which is the majority case, not an edge one.
  `Reason given` is how an operator tells "nobody bound an adapter" from "this
  module has no export to give" from "it threw".
- **`Scope mismatch`** flags an answer that reached further or less far than the
  case asked. Commerce Customers can only erase everywhere; Reviews can only
  erase per merchant. Neither is wrong and reconciling them silently would be.
- **`Ask` / `Ask again`** re-commissions through `CommissionParticipant`, which
  calls the one contract this package publishes. **Nothing in this panel writes
  into another module's tables**, and there is no button that marks a participant
  answered.

Customer Accounts does not appear in this table. A coordinator that is a
participant of its own registry reports its own erasure as one more green tick,
and stops erasing itself the moment somebody edits the entry out.

## Participant registry

The screen that earns the panel. Every other view is about a case that already
exists; this one is about the gap, and the gap is knowable before anybody asks
for anything.

Read from the shipped repositories rather than inferred: four modules in this
fleet publish an erasure, one of those four publishes no export, and **six
modules holding personal data publish neither half**. A registry entry with
nothing bound, or one that publishes no half at all, turns every case it is on
into a partial one.

The registry is explicit configuration and never discovery. A module takes part
because a human named it in `customer-accounts.participants`. **A participant
nobody registered does not appear here as a gap — it does not appear at all**,
and that is the one failure this desk cannot detect for you. `docs/runbook.md`
says registering a module is part of installing it.

An empty registry renders its empty state as a defect: every case opened will be
unsatisfiable, because a case that asked nobody anything can never be completed.

## Claim attempts

A guest order claim is proof-of-possession. A reference plus the address it was
placed with both appear on a receipt that may have been forwarded, so the pair
only opens the claim; a token sent to the address on the order completes it. The
failures are the security signal — a module storing only successful claims cannot
show that somebody tried forty.

Two things are absent and always will be:

- **The claim token.** It exists readable exactly once, in the `ClaimProof`
  returned when a claim is opened, and is stored only as a fingerprint.
- **The email fingerprint.** Not a column, not a search, not a filter. A hash of
  somebody's address does not stop being about them because it is a hash.

Nothing on this screen is searchable. Attempts against one order are found by
**grouping** on the order reference, which puts a column name in the URL and
never a value.

`Refused` covers a reference that does not exist and an address that does not
match it, as one outcome. The verification seam answers matched / not matched /
unavailable and is never told which failed, so no surface can leak the
difference. **Do not add a friendlier message** — that is the order-reference
oracle the design exists to prevent.

There is no "grant it anyway" and no "clear the rate limit".

## Saved lists

Read-only, for a support conversation. Nothing here creates, renames, empties or
shares a list, and nothing revokes a share: revoking somebody's link on their
behalf is a decision about their property. The shopper does it from the
`-livewire` surface, and concluding an erasure revokes every one of them without
anybody pressing anything.

**No price appears, because none exists.** A list holds product references and
quantities; a cart holds priced lines. Different aggregates, and this module
builds only the first.

A share is its own aggregate with its own revocable token — the correction of a
host that kept one token per user on `users` and re-minted it on every share,
killing every published link with no record it had existed. The row survives
revocation, so "this stopped working on Tuesday" and "this never existed" stay
different answers. The token fingerprint is not rendered.

Item and share counts are on the record's own page rather than in the list table.
`SavedList::items()` and `shares()` restate the tenant from the parent, and
Eloquent builds `withCount()` and `whereHas()` from a *fresh* model instance — so
both would count zero for every row. They fail closed, which is the right
direction, but they fail.

## The desk overview widget

Four numbers on the request list. The fourth is not about cases: **silent
participants**, counted before anybody opens one. An operator who learns from a
case that a module could never have answered is learning it too late.

"Past their deadline" is a count of cases somebody has to look at, not a queue
something is working through.

## Accessibility

Every column and entry carries an explicit `label()`, every section a heading,
and the two boolean columns pair a colour with a distinct icon rather than
relying on colour alone. The registry page is a Filament table inside a standard
page shell, so its heading order, focus handling and keyboard reach are the
panel's own.
