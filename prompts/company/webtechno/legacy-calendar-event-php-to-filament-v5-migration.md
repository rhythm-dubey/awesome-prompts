You are an expert Laravel + Filament v5 developer.

I have two legacy PHP files:
- add_calendar_event.php
- edit_calendar_event.php

These files contain a large HTML form with multiple fields and complex conditional visibility (show/hide logic based on user input, toggles, dropdowns, etc.).

Your task is to carefully analyze both files and migrate the form into a Filament v5 Form schema.

IMPORTANT REQUIREMENTS:

1. Preserve ALL existing show/hide logic exactly as in legacy:
   - Any field that is conditionally shown/hidden must behave identically.
   - Translate JavaScript/jQuery visibility logic into Filament reactive logic using:
     - ->visible()
     - ->hidden()
     - ->reactive()
     - ->afterStateUpdated()
     - ->dependsOn()

2. Maintain field dependencies:
   - If one field controls another (e.g., checkbox, select, toggle), replicate the same relationship.
   - Ensure dynamic updates work without page reload.

3. Handle differences between add and edit:
   - If there are logic differences between add_calendar_event.php and edit_calendar_event.php, merge them properly.
   - Ensure edit mode respects pre-filled values and visibility conditions.

4. Optimize for Filament best practices:
   - Use proper components like:
     - TextInput
     - Select
     - Toggle
     - DatePicker / DateTimePicker
     - Section / Grid / Fieldset
   - Group large forms into logical sections for readability.

5. Remove any legacy JS/jQuery:
   - Replace all DOM-based logic with Filament state-driven logic.

6. Ensure UX consistency:
   - No broken visibility states
   - No flickering or incorrect defaults
   - Proper default states based on old logic

7. If any logic is unclear:
   - Infer the most likely behavior based on context
   - Add comments explaining assumptions

OUTPUT FORMAT:

- Provide a complete Filament form schema (PHP)
- Clearly structured and readable
- Include comments wherever logic is mapped from legacy

GOAL:
The Filament form should behave 1:1 with the legacy implementation in terms of visibility and interaction.

Now start by analyzing both files and then generate the Filament v5 form schema.