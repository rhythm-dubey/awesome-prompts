You are performing a strict legacy-to-Laravel migration audit.

Audit whether this legacy file:

@legacyCRM/public_html/admin-area/files/process_valuation_summary_email_listers.php

has been fully and correctly migrated into:

@Saturio/app/Console/Commands/ProcessValuationSummaryEmailListers.php
@Saturio/app/Providers/SchedulerServiceProvider.php

Your job is NOT to do a superficial similarity review.
Your job is to verify functional parity, execution parity, query parity, side-effect parity, scheduler parity, and edge-case parity.

Be extremely strict.
Assume the migration is incomplete unless proven otherwise by code.

Follow references as needed:
- If the Laravel command calls services, mail classes, jobs, helpers, models, traits, repositories, config, views, templates, enums, constants, or utility methods, inspect those too.
- If the legacy file includes or depends on shared functions/classes/config, inspect those too.
- If SQL is built indirectly or via helper methods/scopes, resolve the actual behavior.
- If scheduling depends on registration/bootstrapping, verify the command is actually registered and schedulable.

What to verify in detail:

1) Legacy execution flow
- How the legacy PHP file is triggered
- Whether it is web-invoked, cron-invoked, CLI-invoked, or included elsewhere
- Any request params, globals, includes, bootstrap files, session/auth assumptions
- Any implicit dependencies on legacy environment

2) Laravel execution flow
- Command signature and invocation method
- Whether it is manually runnable
- Whether it is scheduled
- Whether SchedulerServiceProvider properly registers the schedule in the app lifecycle
- Whether there are missing provider registration issues
- Whether the command is discoverable by Artisan

3) Exact business logic parity
- Which records are fetched in legacy
- Exact filtering criteria
- Exact date logic
- Exact status / type / eligibility checks
- Exact grouping/aggregation logic
- Exact lister selection logic
- Exact duplicate prevention logic
- Exact “already processed / already emailed / skip” rules
- Any differences in assumptions or default values

4) Query parity
For both implementations, identify the effective query behavior:
- tables touched
- joins
- where clauses
- group by / order by
- limit / batching
- date windows
- null handling
- string comparisons
- active/inactive flags
- tenancy/account scoping if any

Do NOT just summarize ORM code vaguely.
Translate the Laravel logic into effective SQL-like behavior where possible and compare it with legacy logic.

5) Email parity
- how recipients are determined
- whether recipients are unique/deduplicated
- cc/bcc/reply-to behavior
- subject generation
- body generation
- template/view used
- payload/data passed to email template
- summary content generation
- formatting differences
- conditional content differences
- attachments if any
- whether actual outgoing mail semantics match legacy behavior

6) Side-effect parity
- DB updates after send
- status changes
- timestamps
- audit/log rows
- history records
- counters
- queue dispatching
- retries
- exception handling
- transaction usage
- idempotency behavior
- prevention of duplicate sends on re-run

7) Scheduler parity
- exact schedule frequency
- cron expression equivalence
- timezone used
- overlap protection
- environment restrictions
- onOneServer / withoutOverlapping / runInBackground / queue usage if applicable
- whether the scheduler timing matches what the legacy cron/script likely did

8) Edge cases
Check whether behavior matches for:
- no matching records
- one matching lister
- multiple matching listers
- duplicate emails
- missing email addresses
- malformed data
- null values
- partial mail failure
- DB failure after send
- send failure before update
- timezone boundary issues
- date rollover issues
- repeated command execution
- command executed manually outside scheduler

9) Migration completeness
Determine whether every meaningful responsibility of the legacy file is represented in Laravel:
- trigger
- processing
- selection
- emailing
- side effects
- logging
- scheduling
- protections
- support code dependencies

10) Hidden gaps
Actively look for hidden migration gaps such as:
- legacy includes helper not migrated
- legacy constants/config not ported
- scheduler exists but command logic differs
- command exists but is never registered
- email template changed behavior
- query scope differs subtly
- timezone changed from server local time to app timezone
- legacy raw SQL behavior not matched by ORM scopes
- duplicate suppression removed
- state mutation after send omitted

Required output format:

# Migration Audit: process_valuation_summary_email_listers

## 1. Verdict
Choose exactly one:
- Fully migrated
- Partially migrated
- Not migrated correctly

## 2. Confidence score
Give a percentage from 0 to 100.
Explain what made the score high or low.

## 3. Legacy flow breakdown
Write a step-by-step breakdown of exactly what the legacy file does from entry to exit.

## 4. Laravel flow breakdown
Explain exactly what these files do:
- @Saturio/app/Console/Commands/ProcessValuationSummaryEmailListers.php
- @Saturio/app/Providers/SchedulerServiceProvider.php

Also include any additional Laravel files you inspected and why they matter.

## 5. Dependency trace
List every additional file/class/template/helper/service/config you followed on both legacy and Laravel sides.

Use this format:
- File path
- Why it matters
- Key behavior found

## 6. Functional parity matrix
Create a detailed table with these columns:
- Area
- Legacy implementation
- Laravel implementation
- Status (Exact / Partial / Different / Missing)
- Evidence
- Risk

Areas must include at least:
- Invocation
- Scheduling
- Record selection
- Filtering conditions
- Date logic
- Grouping/aggregation
- Recipient selection
- Deduplication
- Email composition
- Mail dispatch
- Post-send updates
- Logging/auditing
- Error handling
- Idempotency
- Timezone handling
- Re-run behavior

## 7. Query parity review
Show the effective legacy query logic and effective Laravel query logic in a SQL-like or pseudo-SQL form.
Then explain every difference precisely.

## 8. Exact mismatches and missing behavior
List every mismatch separately.
For each item provide:
- Title
- What legacy does
- What Laravel does
- Why this matters
- Severity (Low / Medium / High)
- Recommended fix

## 9. Line-level evidence
Where possible, cite concrete methods, blocks, or line ranges from the inspected files to support your conclusions.
If exact line numbers are unavailable, cite method names or uniquely identifiable code snippets.

## 10. Final conclusion
State plainly whether the migration is complete.
Do not hedge.

## 11. Developer action items
Give a concrete checklist of changes required to make the Laravel migration fully equivalent.

Important rules:
- Do not assume “same filename meaning same behavior”.
- Do not say “looks equivalent” without evidence.
- Do not ignore indirect dependencies.
- Do not stop at the command and scheduler if mail templates/services/helpers are involved.
- Be willing to conclude that migration is incomplete even if the broad intent matches.
- Treat subtle query/date/state differences as real migration gaps.
- Call out dead code, unregistered providers, unused commands, or missing scheduling hooks.
- If something cannot be verified from available code, explicitly say “not verifiable from inspected code” and reduce confidence accordingly.

Final instruction:
Your answer should read like a migration audit report prepared by a senior engineer doing a production-readiness review.