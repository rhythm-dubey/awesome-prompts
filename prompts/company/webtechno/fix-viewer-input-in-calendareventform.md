You are working on a Laravel + Filament (v3/v4/v5) project using Livewire.

There is a runtime error:
"Property type not supported in Livewire for property: [{}]"

This error occurs when selecting the "Applicant / Viewer" field in a Filament form.

Your task is to fix this issue properly.

Context:

* The form contains a Select field for "Viewer" (Applicant).
* The error is triggered after selecting a value.
* This indicates that a non-serializable value (like a Model object or Collection) is being assigned to the Livewire component state.

Instructions:

1. Locate the Select field related to "viewer", "viewer_id", or "applicant".

2. Ensure that the field stores only a primitive value:

   * स्वीकार्य types: int, string, or simple array
   * NOT allowed: Eloquent Model, Collection, or custom objects

3. If `->options()` is used:

   * Convert it to a key-value array using pluck:
     Example:
     ->options(User::pluck('name', 'id')->toArray())

4. If a custom label is needed (e.g., name + email):

   * Use mapWithKeys instead of passing full models:
     Example:
     ->options(
     User::all()->mapWithKeys(fn ($user) => [
     $user->id => "{$user->name} ({$user->email})"
     ])
     )

5. If `->relationship()` is used:

   * Ensure correct foreign key is used (e.g., viewer_id)
   * Example:
     Select::make('viewer_id')
     ->relationship('viewer', 'name')
     ->searchable()

6. Check any `afterStateUpdated`, `formatStateUsing`, or `mutateFormDataUsing`:

   * Ensure they DO NOT assign objects like:
     ❌ $set('viewer', User::find($state))
   * Instead assign only ID:
     ✅ $set('viewer_id', $state)

7. If the form uses nested state or arrays:

   * Ensure no Model instances are stored anywhere in the state.

8. After fixing:

   * Confirm that selecting a Viewer no longer throws a Livewire serialization error.
   * Ensure the selected value persists correctly.

Important:

* Do NOT change business logic.
* Only fix serialization/state issues.
* Keep Filament best practices.

Finally:

* Show the corrected code for the Viewer field.
* Briefly explain what was wrong and how it was fixed.
