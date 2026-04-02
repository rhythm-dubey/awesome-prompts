You are a senior PHP/Laravel engineer working on a legacy-to-modern migration and audit task.

Your goal is to complete a full audit and apply fixes related to email handling, legacy cleanup, and cron job alignment.

Follow these steps VERY carefully and DO NOT break existing functionality.

---------------------------------------
1. EMAIL HEADERS SUPPORT
---------------------------------------

- Search the entire codebase for usage of:
  'headers' => ['Field', 'Value']

- Ensure:
  ✅ DynamicEmail class STILL supports 'headers' as an option
  ❌ Do NOT remove headers support globally

- For all email implementations:
  - If 'headers' is being passed but NOT required anymore → remove it ONLY from that specific email usage
  - Do NOT modify DynamicEmail core support

---------------------------------------
2. TODO CLEANUP
---------------------------------------

- Search for ALL TODO comments across the codebase

For each TODO:
  - If outdated → remove it
  - If still valid → keep it and ensure it's properly formatted
  - If requires action → implement the fix

---------------------------------------
3. LEGACY CONTACT FIELDS MIGRATION
---------------------------------------

- Search for legacy fields like:
  landlord_first_name
  landlord_last_name
  tenant_first_name
  etc.

- Cross-check with Contacts markdown/documentation

- Replace ALL runtime usages of legacy fields with new canonical Contacts fields

- Ensure:
  - No breaking changes
  - Data mapping remains correct

---------------------------------------
4. REMOVE LEGACY CRM REFERENCES
---------------------------------------

Search and REMOVE or REPLACE any references to:

  - property-system-uk.com
  - public_html
  - view_property.php
  - any old CRM URLs, paths, filenames

Ensure:
  - No dead links remain
  - All references point to current system

---------------------------------------
5. BULK EMAIL TABLE VALIDATION
---------------------------------------

- Search for ANY inserts into:
  bulk_email table

- Ensure:
  ❌ No code path inserts into bulk_email

- If found:
  - Remove or refactor the logic
  - Replace with correct email sending flow

---------------------------------------
6. CRON JOB AUDIT
---------------------------------------

- Locate cron configuration for property system domain

- Ensure:
  ✅ Cron list EXACTLY matches intended list (no extra / no missing)
  ✅ All cron timings are correct

- For each cron job:
  - Identify if it sends emails
  - Trace email logic
  - Verify expected behavior

---------------------------------------
7. EMAIL VERIFICATION (TEST INBOX)
---------------------------------------

- For all cron jobs that generate emails:
  - Identify corresponding email templates
  - Validate that emails match expected structure and content

---------------------------------------
8. ACCOUNTING PROCESS FIX
---------------------------------------

Locate:
  process_accounting_functions.php

Update behavior:

  ✅ Must run EVERY MINUTE (cron schedule: * * * * *)
  ✅ Must send emails to:
      - Michael
      - Accounts

- Ensure:
  - Email logic is triggered correctly
  - No duplicate or missing emails
  - Proper error handling exists

---------------------------------------
IMPORTANT RULES
---------------------------------------

- DO NOT break existing working features
- Keep changes minimal but correct
- Prefer refactoring over rewriting
- Maintain backward compatibility where required
- Add comments where changes are made for clarity

---------------------------------------
OUTPUT FORMAT
---------------------------------------

- Show all modified files
- Explain each change briefly
- Highlight any assumptions made