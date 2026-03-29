You are working on a Laravel + Filament project that uses `Archilex\AdvancedTables\AdvancedTables` and `Filament\Tables\Concerns\InteractsWithTable`.

There is a fatal trait collision in this file:

`app/Filament/Pages/Reports/ViewPremiumProperties.php`

Error:

`Trait method Filament\Tables\Concerns\InteractsWithTable::hasTableSearch has not been applied as App\Filament\Pages\Reports\ViewPremiumProperties::hasTableSearch, because of collision with Archilex\AdvancedTables\AdvancedTables::hasTableSearch`

Context:
- Class: `ViewPremiumProperties extends Page implements HasTable`
- Traits currently used:
  - `AdvancedTables`
  - `ExposesTableToWidgets`
  - `InteractsWithTable`

Your task:
1. Open `app/Filament/Pages/Reports/ViewPremiumProperties.php`
2. Resolve the trait method collision properly using PHP trait conflict resolution (`insteadof` and aliases if needed).
3. Keep `AdvancedTables` functionality intact.
4. Keep Filament table search working.
5. Do not remove any required existing behavior unless absolutely necessary.
6. Apply the cleanest and most maintainable fix.

Important:
- The collision is specifically around `hasTableSearch`.
- If both traits define the same method, explicitly choose which one should win and alias the other one only if needed.
- Prefer the implementation that is compatible with `Archilex AdvancedTables`, since that package likely extends Filament table search behavior.
- Also check for any other trait method collisions in the same class and fix them similarly if they exist.

Expected output:
- Modify the file directly.
- Show the final updated trait `use` block.
- Briefly explain why the fix works.

Aim for a production-safe fix, not a workaround.