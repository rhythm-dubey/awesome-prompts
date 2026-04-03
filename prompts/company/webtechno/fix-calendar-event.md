You are working on a Laravel + Filament project with an existing legacy CRM system.

Task:
Revert and correctly re-implement calendar-related changes based on the legacy system.

Context:

* Some recent changes were made to the admin dashboard calendar view, but they are incorrect and need to be reverted.
* The correct implementation should strictly follow the legacy CRM behavior.

Instructions:

1. Revert Changes:

   * Revert ALL calendar-related changes made in the admin dashboard.
   * Restore the previous working state.
   * Do NOT modify:
     `@Saturio/app/Filament/Resources/CalendarEvents/Pages/ViewCalendar.php`
     (This file is already correct and should remain untouched.)

2. Source of Truth (Legacy सिस्टम):
   Carefully analyze and replicate behavior from:

   * `@legacyCRM/public_html/admin-area/files/add_calendar_event.php`
   * `@legacyCRM/public_html/admin-area/files/edit_calendar_event.php`

3. Re-Implementation:

   * Re-implement calendar logic ONLY based on the above legacy files.
   * Ensure:

     * Same field behavior (show/hide, dependencies, validations)
     * Same data handling logic
     * Same UI flow wherever applicable

4. Event Filters:

   * Restrict filters to ONLY these four select filters (as per legacy):

     * (Inspect legacy code and identify exact 4 filters)
   * Remove any अतिरिक्त or newly added filters.

5. Events List Table:

   * Match the legacy table EXACTLY in:
     a) Column headings (names and order)
     b) “Details” column content and formatting
   * Ensure formatting, concatenation, and display logic matches legacy behavior.

6. Relationship & Data Consistency:

   * Ensure all data used in calendar/events is correctly mapped to Eloquent models
   * Avoid using undefined or incorrect relationships
   * Maintain Livewire-compatible state (no objects in form state)

7. Do NOT:

   * Introduce new features
   * Change business logic
   * Refactor unrelated code

8. Deliverables:

   * Summary of reverted changes
   * Updated implementation details
   * Any files modified (with explanation)
   * Confirmation that behavior matches legacy

Goal:
Achieve 1:1 functional and visual parity with the legacy calendar system while keeping the current Filament structure stable.
