# WebTechno / Laravel codebase — read-only audit prompt suite

Use each block below as a **standalone** prompt. Every audit is **report-only**: do not modify application code; output findings only.

---

## 1. Email headers usage

You are auditing email header usage in a PHP/Laravel codebase.

**Tasks:**

1. Search for all occurrences of:
   `'headers' => ['Field', 'Value']`

2. Verify:
   - DynamicEmail class still supports `headers`
   - No global removal of headers support

3. For each email usage:
   - Check if `headers` is still being passed
   - Identify if it is actually required

4. **Output:**
   - List of files still using `headers`
   - Whether usage is valid or should be removed
   - Confirm DynamicEmail still supports headers

**Do NOT modify code. Only audit and report.**

---

## 2. TODO comments

Audit all TODO comments in the codebase.

**Tasks:**

1. Find all TODO comments

2. Categorize each:
   - Still valid
   - Already resolved but not removed
   - Requires action but not implemented

3. **Output:**
   - File + line number
   - Status of each TODO
   - Any suspicious or ignored TODOs

**Do NOT change code. Only report inconsistencies.**

---

## 3. Legacy contact fields migration

Audit migration from legacy contact fields to new Contacts structure.

**Tasks:**

1. Search for legacy fields like:
   - `landlord_first_name`
   - `tenant_first_name`
   - etc.

2. Verify:
   - No runtime usage of these fields remains
   - All have been replaced with canonical Contacts fields

3. **Output:**
   - Any remaining legacy field usage
   - Files where migration may be incomplete
   - Any risky replacements

**Do NOT modify code. Only audit.**

---

## 4. Legacy CRM references

Audit removal of legacy CRM references.

**Search for:**

- `property-system-uk.com`
- `public_html`
- `view_property.php`
- any old CRM URLs or paths

**Tasks:**

1. Identify any remaining references
2. Check if they are:
   - Dead code
   - Still in use (critical issue)

**Output:**

- List of files with legacy references
- Severity (low/medium/high)

**Do NOT modify code.**

---

## 5. `bulk_email` table usage

Audit usage of `bulk_email` table.

**Tasks:**

1. Search for:
   - `insert into bulk_email`
   - `DB::table('bulk_email')`
   - any ORM usage

2. Verify:
   - No code path inserts emails into `bulk_email`

3. **Output:**
   - Any violations
   - Related code paths

**Do NOT change anything.**

---

## 6. Cron jobs (definitions and inventory)

Audit cron jobs for the system.

**Tasks:**

1. Locate all cron definitions

2. Verify:
   - No extra cron jobs exist
   - No missing cron jobs
   - Timing matches expected schedules

3. For each cron:
   - Identify purpose
   - Check if it sends emails

**Output:**

- Full cron list
- Any mismatch or suspicious entries

**Do NOT modify code.**

---

## 7. Email flow triggered by cron jobs

Audit email flow triggered by cron jobs.

**Tasks:**

1. Identify all cron jobs that trigger emails

2. Trace:
   - Which function/class sends email
   - Which template is used

3. Verify:
   - Email logic is correct
   - No missing recipients
   - No duplicate triggers

**Output:**

- Cron → Email mapping
- Any inconsistencies

**Do NOT modify code.**

---

## 8. `process_accounting_functions.php` behavior

Audit `process_accounting_functions.php` behavior.

**Tasks:**

1. Verify cron schedule:
   - Must be: `* * * * *` (every minute)

2. Verify email behavior:
   - Emails are sent to:
     - Michael
     - Accounts

3. Check:
   - No duplicate emails
   - No missed triggers
   - Proper error handling

4. **Output:**
   - Confirmation of correct behavior
   - Any risks or edge cases

**Do NOT modify code.**
