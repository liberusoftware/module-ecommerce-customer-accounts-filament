# Running the privacy desk

## Every week: read the registry

**Privacy desk → Participant registry.** Anything marked `Silent` will make every
case it is on completed in part. Two shapes of silence, and they need different
people:

| What you see | What it means | Who fixes it |
| --- | --- | --- |
| `Adapter bound` false | Registered, nothing bound to answer for it | Whoever composes the application |
| `Publishes` empty | Registered, publishes neither an export nor an erasure | Whoever owns that module |
| Module missing entirely | Nobody registered it | You, right now — see below |

**A module that holds personal data and is not in the registry is invisible to
every case ever opened.** Nothing in this fleet can detect that for you; there is
no discovery and no scan, deliberately. **Registering a module is part of
installing it** — put it in the checklist beside running its migrations.

## When a case arrives

1. **Record it.** Privacy desk → Privacy requests → *Record a request*. Set
   `When the person asked` to when they actually asked — the regulatory clock
   runs from there, and a letter dated last Tuesday was asked last Tuesday.
2. **Say how you re-authenticated them.** An erasure will not open without it.
   Write what you did — "identity documents at the desk", "re-login" — and never
   the material itself.
3. **Choose the reach.** `This merchant` or `Everywhere in this deployment`.
   Participants record which they actually applied, and a mismatch is flagged
   rather than reconciled.
4. **Commission the participants.** Either from the case (Participants → *Ask*)
   or from your own job dispatching `CommissionParticipant`. **Nothing does this
   on its own.**

## Working a case

Open it and read `Have not completed`. Every participant is on that table
whatever it answered, and the `Reason given` column tells you which problem you
have:

- *"Registered, but nothing is bound to answer for it."* — an adapter is missing
  from this deployment. Bind it, then *Ask again*; resolution happens per
  commission, so you do not reopen the case.
- *"Publishes no erasure."* / *"Publishes no access."* — that module has no such
  half. Nothing to retry. The case will conclude in part and that is honest.
- Anything else — the adapter threw and the message is what it said. Fix it and
  *Ask again*.

`Ask again` re-runs that module's own answer through its adapter. **It never
writes into that module's tables from here.**

## Concluding

A case concludes itself the moment the last participant completes. Conclude it by
hand when it is not going to get any better — the deadline arrived, or you have
stopped retrying a module that is not coming back.

**Concluding cannot record a case as completed when a participant did not
complete.** It records `Completed in part` and names them. There is no override.
If somebody asks you for one, the answer is that a report saying "erased" over
rows nobody touched is the failure this whole module exists to prevent.

For an erasure, concluding — including concluding in part — clears this module's
own rows and revokes every share the subject holds. That happens whatever the
other participants did.

## Deadlines

The widget counts cases past their deadline. **Nothing acts on that count.**
There is no sweeper, no auto-conclusion and no notification to the address being
erased; a late case is late until somebody opens it.

If you want cases progressed automatically, schedule your own job over
`CommissionParticipant`. Do not schedule `ConcludePrivacyRequest`: concluding on
a timer converts "we did not finish" into "we finished" on the calendar's say-so.

## Claim attempts

**Privacy desk → Claim attempts**, which defaults to the failures. A run of
refusals against one order reference is somebody guessing; group by
`Order claimed` to see it. There is nothing to press: no "grant it anyway" and no
"clear the rate limit", because both are an operator overriding
proof-of-possession for somebody who failed to prove it.

The limit is `customer-accounts.claim.max_attempts` in
`attempt_window_minutes`, counted per merchant against both the presented order
reference and the presented address. Raise it in config, never per case.

If every attempt reads `Verifier unavailable`, no `VerifiesGuestOrderClaim` is
bound in this application and guest claiming is closed. That is the safe default,
not a fault to work around by granting claims by hand.

## Saved lists

Read-only. A shopper reporting a dead share link is looking at a revoked row with
a date and a reason, or at nothing at all — and "nothing at all" is what a link
that was never minted here looks like too. Neither you nor this panel can revoke,
re-mint or repair somebody's share; they do it from their own account.
