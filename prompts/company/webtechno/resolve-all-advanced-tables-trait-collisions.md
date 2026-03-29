You are fixing a Laravel + Filament + Archilex AdvancedTables trait collision.

Project context:
- Class: `App\Filament\Pages\Reports\ViewPremiumProperties`
- File: `app/Filament/Pages/Reports/ViewPremiumProperties.php`
- The class extends `Page` and implements `HasTable`
- Traits used:
  - `Archilex\AdvancedTables\AdvancedTables`
  - `Filament\Widgets\Concerns\ExposesTableToWidgets`
  - `Filament\Tables\Concerns\InteractsWithTable`

Problem:
There are multiple PHP trait method collisions between:
- `Archilex\AdvancedTables\AdvancedTables`
- `Filament\Tables\Concerns\InteractsWithTable`

Current fatal errors already seen:
- `hasTableSearch`
- `resetTableSearch`

This means more overlapping methods may exist and must be handled proactively.

Your task:
1. Open `app/Filament/Pages/Reports/ViewPremiumProperties.php`
2. Resolve ALL collisions between `AdvancedTables` and `InteractsWithTable` using PHP trait conflict resolution.
3. Do not patch methods one-by-one reactively. Inspect both traits and resolve all overlapping methods now.
4. Prefer `AdvancedTables` implementations for overlapping table-search-related methods, because the package likely extends Filament’s native behavior.
5. Alias the `InteractsWithTable` versions where useful, so the class still has access to them if needed.
6. Keep existing page behavior intact.
7. Produce a clean production-safe solution.

Implementation requirements:
- Update the trait `use` block properly with `insteadof` and aliases.
- Check vendor trait methods to identify every overlapping method between:
  - `Archilex\AdvancedTables\AdvancedTables`
  - `Filament\Tables\Concerns\InteractsWithTable`
- Add conflict resolution entries for all overlaps, not just the currently failing one.
- Do not remove `AdvancedTables`.
- Do not remove `InteractsWithTable`.
- Do not introduce hacks or comment out functionality.

Expected output:
- Show the final trait `use` block
- Briefly explain which methods collided
- Explain why choosing `AdvancedTables` for those overlaps is the correct fix