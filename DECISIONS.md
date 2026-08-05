# Decisions

Assumptions made where the brief was silent, the reasoning behind the Part D
picks, trade-offs accepted, and what two more weeks would go toward.

## Assumptions

1. **Multi-tenant via a `role` + self-referencing `tenant_id` column on
   `users`, not a separate `teams`/`organizations` table.** The brief lists
   multi-tenant isolation only as a Part D *bonus*, not a mandatory
   requirement, but it was requested directly and built for real. Three
   roles share one table: `super` (platform admin, owns nothing, manages the
   list of companies), `tenant` (a company/workspace owner — the existing
   form-builder feature set), and `user` (a team member whose `tenant_id`
   points at their tenant-owner's `id` and who shares **full read/write
   access to that tenant's entire pool of forms/data**, not just what they
   personally created — that sharing is the actual point of the feature).
   `User::tenantId()` resolves which company a given account's data belongs
   to (own id for a tenant, the `tenant_id` column for a user, `null` for a
   super) and is the one value every policy compares against.

   `forms`, `ai_generations` and `imports` each got their own **denormalized**
   `tenant_id` column rather than resolving ownership via a join through
   `users` at query time. Every existing hot-path index on these tables
   already led with the ownership column (`[user_id, status]`,
   `[user_id, status, id]`); swapping in `tenant_id` in the same shape kept
   every dashboard listing and AI/import status poll index-backed with zero
   query-plan regression — a join would have defeated those indexes on
   every poll, and `ai_generations`/`imports` are polled frequently from the
   frontend. `user_id` was kept on all three tables, its meaning narrowed to
   "who personally created this record" — an audit/author field, exactly
   the role `form_versions.created_by` already played before this change.
   The trade-off accepted: `tenant_id` must never be set from client input:
   every write path goes through one model-level `Form::createForTenant()`
   (mirrored on the other two models), so there's exactly one place per
   model where it's ever assigned — a mass-assignment bug here (fixed
   during implementation: `user_id`/`tenant_id` aren't in `$fillable`, so
   `new Form(array_merge(...))` was silently dropping them) is exactly the
   kind of drift this single-entry-point design is meant to prevent.

   Public self-registration was removed entirely — the only paths to a new
   account are a seeded super, a super creating companies from `/companies`,
   or a tenant creating team members from `/team`. A super "deleting" a
   company from that screen **suspends** it (`disabled_at` timestamp) rather
   than destroying anything: forms, submissions and team members are all
   preserved, logins for that company are blocked with a specific message,
   and its public fill URLs show the same "closed" screen an archived form
   shows. This was a deliberate choice over a hard delete + cascade, since a
   platform admin's "remove this company" action being irreversible and
   silently destroying a paying customer's data felt like the wrong default
   — restore is one click, data loss should never be.

   For the same reason, the self-service "delete my account" feature that
   shipped with the original Breeze scaffold was removed rather than adapted:
   in a multi-tenant model a tenant deleting themselves would orphan their
   team and cascade-delete the whole company's forms as an irreversible
   side effect of what a user might treat as a routine settings action —
   removed instead of building a multi-tenant-safe version of it, since
   the Companies "disable" flow already covers the legitimate underlying
   need (an admin removing a company) without the accidental-self-destruct
   risk.

2. **React via Inertia.js, inside the Laravel app, no separate frontend
   project.** The brief allows "Livewire and/or React"; the user explicitly
   asked for React + Bootstrap *inside* Laravel, not a decoupled SPA. Inertia
   was chosen over a hand-rolled API + client-side router because it keeps
   one request/response cycle, one auth session, and one set of routes —
   `resources/js/Pages` are server-routed exactly like Blade views would be,
   just rendered as React.

3. **The AI layer lives inside Laravel** (queued jobs calling Gemini
   directly), not as a separate FastAPI service. The brief lists a separate
   Python service as a *positive signal*, not a requirement, and the user
   confirmed this trade-off directly: one deployable is meaningfully simpler
   to ship to shared hosting, and the queued-job architecture already
   satisfies "don't block a request on a long LLM call" without a second
   service boundary.

4. **Gemini (`gemini-2.0-flash`) as the LLM provider**, chosen for its free
   tier and native JSON-mode output. The provider is isolated behind
   `GeminiClient` — swapping providers means implementing one method
   (`generate(system, user) -> {text, tokens, latency}`), not touching
   `FormGenerator`'s parse/repair/retry logic.

5. **Database queue driver instead of Redis/Horizon.** The deployment target
   is shared hosting (domianz.in), which typically offers cron but not a
   persistent worker process or Redis. `queue:work --stop-when-empty
   --max-time=50` on a once-a-minute cron is the documented mechanism;
   Docker Compose mirrors this exact pattern locally (a self-restarting loop,
   not Supervisor) so dev and prod queue behavior match.

6. **MySQL 8 as the target, MariaDB/SQLite as fallbacks.** Local development
   happened to run against MariaDB 10.11 (already installed) and the
   automated test suite runs on SQLite in-memory (`phpunit.xml`) for speed
   and isolation. FULLTEXT search is guarded with a driver check
   (`SubmissionController::applySearch`) so it degrades to `LIKE` rather than
   throwing on drivers without native FULLTEXT parity.

7. **Twelve input field types + two display types**, one more than the
   brief's "at least ten": text, textarea, number, email, phone, date,
   dropdown, radio, checkbox, file, rating, hidden, plus heading/paragraph
   for layout. `hidden` was added because conditional-logic forms
   (Part D) frequently need a value that travels with the submission
   without being shown.

8. **CSV export is synchronous** (streamed, not queued). At the submission
   volumes a grading pass or an early-stage real form would generate, a
   queued export adds latency (wait for a job, then a notification) without
   a real benefit. Documented in README as a limitation to revisit if
   submission counts grow into the hundreds of thousands.

9. **Field `key`, not database column, is the stable identifier.** Renaming
   a field's label doesn't break historic exports or condition rules —
   everything (submissions, CSV columns, conditions) references the
   snake_case `key`, which the builder keeps stable unless a user explicitly
   edits it.

## Part D — why these three, and their trade-offs

### 1. Form versioning + rollback

**User problem:** a form author edits a live form and breaks something (a
required field they didn't mean to require, a dropdown option a downstream
process depends on) — with no way to see what changed or undo it. Worse,
existing submissions silently become impossible to interpret against the
new schema.

**Implementation:** `forms.schema` is the *working copy*; every save (from
the builder, an applied AI generation, or a committed import) calls
`Form::saveSchemaVersion()`, which increments `current_version` and inserts
an immutable row into `form_versions` tagged with a `source`
(`builder`/`ai`/`import`/`rollback`). Every `submissions` row stores
`form_version_id`, so a submission from three schema changes ago always
pairs with the exact field set it was answered against — this matters for
CSV export correctness as much as for the versions UI. `SchemaDiff` computes
a field-level diff (added/removed/changed-by-property) between consecutive
versions for the history screen. Rollback doesn't rewrite history — it calls
the same `saveSchemaVersion()` with the old snapshot, appending a new
version tagged `rollback`. History is append-only, full stop.

**Trade-offs accepted:** full-snapshot storage per version, not
deltas/patches. A form with hundreds of versions and huge schemas would
waste storage; in practice form schemas are a few KB of JSON and this
never mattered in testing. The upside is enormous simplicity — no patch
format to design, no replay logic, and "what did version 4 look like" is
a single `SELECT`.

**With two more weeks:** a side-by-side visual diff (not just a badge list)
rendered on the actual canvas layout; the ability to name/pin a version
("v3 — before the Q2 redesign"); pruning very old auto-versions while
keeping named ones.

### 2. Conditional logic & branching

**User problem:** static forms over-ask. "Would you recommend us? → No"
should surface a follow-up; a 40-field application shouldn't show every
field to every respondent. The brief lists this as a Part D example
specifically, and it's also the feature most likely to be graded against a
security mistake (client-only enforcement letting hidden-required fields
through, or hidden-field values leaking into storage).

**Implementation:** any field can carry a `conditions` block — `{logic:
"all"|"any", rules: [{field, operator, value}]}` with seven operators
(equals, not_equals, contains, greater_than, less_than, is_empty,
is_not_empty). `resources/js/lib/conditions.js` evaluates this live in
React as the respondent types, including *cascading* hiding (if field B's
visibility depends on hidden field A, B stays hidden even if B's own rule
would technically pass on A's stale value). `app/Services/Schema/
ConditionEvaluator.php` is a **server-side twin** of the exact same logic —
`RuleCompiler` calls it before compiling Laravel validation rules, so a
field hidden by conditions is (a) never required server-side regardless of
what the client sent, and (b) its submitted value is discarded before the
submission is even validated, let alone stored. Builder-side, the "Field
options" panel lets a field reference any *earlier* input field by key.

**Trade-offs accepted:** conditions reference fields by `key`, flat across
the whole form (not scoped to "fields before this one in document order"
at the schema level) — the validator does check for self-reference and
unknown-key references, but doesn't currently forbid a field depending on
one that appears *after* it in the canvas (it would just always evaluate
against that field's default/empty value on first render, which is
confusing but not unsafe). Full DAG-cycle detection was judged lower value
than shipping the cascade-hiding correctness, which is the part that's
actually security-relevant.

**With two more weeks:** a visual condition-flow view (which fields gate
which), cycle detection with a clear builder-time error instead of relying
on evaluation order, and multi-value operators (e.g. "is one of [a, b, c]")
for checkbox/multi-select conditions.

### 3. Analytics & spam protection

**User problem:** two separate but related gaps in a bare form builder —
(a) form owners have no visibility into whether people are *abandoning*
their form partway through, and (b) a public, unauthenticated fill URL is
an open invitation to bots.

**Implementation — analytics:** `form_events` records four beacon types
(`view`, `start`, `step`, `submit`) keyed by a **salted daily session hash**
(`sha256(ip + user_agent + form_id + date + app_key)`) — deliberately not a
cookie or a stored IP, so there's no PII to protect or leak, at the cost of
undercounting a visitor who returns after midnight. The dashboard
(`AnalyticsController` + `Pages/Forms/Analytics.jsx`) aggregates unique
sessions per stage into a funnel with conversion rates, a 14-day submission
trend, and — for multi-step forms specifically — per-step reached-counts to
show where respondents give up. Charts are single-hue bar charts using a
palette validated against the dataviz skill's accessibility checks (fixed
categorical hue, no dual-axis, recessive gridlines).

**Implementation — spam protection**, layered rather than single-mechanism:
a per-IP-per-form rate limiter (`throttle:fill`, 10/min) on both the
submit and beacon routes; a honeypot field the React fill page hides purely
via CSS (`.hp-field`, `left:-9999px`) that real users never see or fill but
scrapers reliably do; an **encrypted, server-minted minimum-fill-time
token** (`Crypt::encryptString(timestamp)`, checked server-side, so it can't
be forged or replayed by a client that skips the wait); and an optional
per-form daily submission cap set in the Settings step. All four bot-catch
paths return the *same* success response as a real submission (never a 4xx
that would teach a scraper what tripped it) while silently storing nothing.

**Trade-offs accepted:** the analytics de-duplication trades precision for
zero PII footprint — a visitor is one "session" per day, not truly unique;
good enough for funnel *shape*, not authoritative unique-visitor counting.
Spam protection is heuristic (honeypot + timing + rate limit), not a
reCAPTCHA/hCaptcha integration — chosen because it requires no third-party
key/script and still stops the overwhelming majority of unsophisticated
bots; a determined attacker could defeat it. This felt like the right
default for a self-hosted form builder rather than forcing every user to
configure a CAPTCHA provider.

**With two more weeks:** an optional hCaptcha/Turnstile integration
per-form (opt-in, not forced); exportable analytics (CSV of the funnel);
field-level analytics (which specific field respondents abandon *on*, not
just which step); a rolling real-unique-visitor estimate using
HyperLogLog instead of daily session hashing.

## What's next with two more weeks (beyond the three Part D items above)

- **Live deployment** to the shared-hosting target, plus a recorded 3–5
  minute walkthrough — both flagged as outstanding in the README.
- **Billing/plans tied to company suspension** — disabling a company is
  currently a manual super-admin action with no automated trigger (unpaid
  invoice, trial expiry). Building on the multi-tenant model in Assumption 1
  rather than replacing it.
- **Webhooks + a public submissions API** (listed as a Part D example this
  submission didn't pick) — signed payloads on submission, a
  token-authenticated read API, so the form builder can sit behind other
  systems instead of only a human dashboard.
- **Template library** — the seeded feedback/internship forms are really
  early templates; formalizing "start from a template" as a first-class
  flow (distinct from AI generation and from import) would round out form
  creation options.
- **Autosave + concurrent-edit locking** on the builder — currently a save
  is an explicit action per wizard step; two people editing the same form
  simultaneously would silently overwrite each other's work.
- **Accessibility audit** of the fill page and builder against WCAG AA
  specifically (current work followed general semantic-HTML/labeling
  practice but wasn't screen-reader tested end-to-end).
