You are refactoring a Laravel codebase.

Context:
- File: Property.php
- Lines: 2183–2199 contain two relation methods that appear to be duplicates (same model, same relationship type, same keys/constraints).

Tasks:

1. Analyze both relation methods and confirm if they are functionally identical.
2. If they are duplicates:
   - Keep the method that follows Laravel naming conventions or is more widely used.
   - Mark the other method as deprecated and prepare it for removal.

3. Search the entire @Saturio/ directory for usages of the duplicate relation method:
   - Replace all usages with the retained relation method.
   - Ensure no behavioral changes occur (same eager loading, constraints, chaining, etc.).

4. After replacing all usages:
   - Safely delete the duplicate relation method from Property.php.

5. Validate:
   - No broken references remain.
   - All queries using the relation still work correctly.
   - No change in returned data structure.

6. Output:
   - List of files updated.
   - Before vs after of the relation methods.
   - Any edge cases found (e.g., different naming expectations or chained conditions).

Important:
- Do NOT modify unrelated code.
- Preserve coding style and formatting.
- Ensure backward compatibility during replacement.