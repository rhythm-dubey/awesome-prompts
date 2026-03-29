## ROUND 4 — Database & Optimization (MySQL, Indexing, Query Optimization)

---

### Q1. Explain database indexing. How do you decide which columns to index?

**Answer:**

An index is a data structure (usually B-tree in MySQL) that speeds up data retrieval by creating a sorted reference to rows.

**When to add indexes:**
1. Columns used in `WHERE` clauses frequently.
2. Columns used in `JOIN` conditions.
3. Columns used in `ORDER BY` and `GROUP BY`.
4. Foreign key columns (Laravel adds these automatically).
5. Columns used in `unique` constraints.

**When NOT to index:**
- Tables with very few rows (full scan is faster).
- Columns with very low cardinality (e.g., `gender` with only M/F).
- Columns that are updated very frequently (index maintenance overhead).

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->index();
    $table->string('status')->index();
    $table->decimal('total', 10, 2);
    $table->timestamps();

    // Composite index for common query pattern
    $table->index(['user_id', 'status', 'created_at']);
});
```

**Composite index rule:** The leftmost prefix rule — an index on `(A, B, C)` can serve queries on `(A)`, `(A, B)`, and `(A, B, C)`, but NOT `(B, C)` alone.

**Interview Tip:** Mention the leftmost prefix rule for composite indexes — it's a key MySQL concept. Also mention `EXPLAIN` to verify index usage.

---

### Q2. How do you use `EXPLAIN` to analyze and optimize slow queries?

**Answer:**

```sql
EXPLAIN SELECT * FROM orders
WHERE user_id = 5 AND status = 'pending'
ORDER BY created_at DESC;
```

**Key columns to analyze:**

| Column | What to look for |
|--------|-----------------|
| `type` | `const` > `eq_ref` > `ref` > `range` > `index` > `ALL` (full table scan = BAD) |
| `possible_keys` | Indexes MySQL considered |
| `key` | Index actually used (NULL = no index used) |
| `rows` | Estimated rows to scan (lower is better) |
| `Extra` | `Using index` (good), `Using filesort` (bad), `Using temporary` (bad) |

**In Laravel:**
```php
DB::enableQueryLog();
$orders = Order::where('status', 'pending')->where('total', '>', 100)->get();
dd(DB::getQueryLog());
```

**Interview Tip:** Walk through a real optimization story with numbers.

---

### Q3. What is database normalization? When would you denormalize?

**Answer:**

**Normalization** organizes data to reduce redundancy:
- **1NF:** Atomic values, no repeating groups.
- **2NF:** 1NF + no partial dependencies.
- **3NF:** 2NF + no transitive dependencies.

**When to denormalize:**
1. **Read-heavy dashboards** — Store computed aggregates.
2. **Reporting tables** — Flatten data for fast analytics.
3. **Caching counters** — `orders_count` on users table.
4. **Reducing JOINs** — Store `user_name` on orders if always displayed together.

```php
$users = User::withCount('orders')
    ->withSum('orders', 'total')
    ->get();
// Adds $user->orders_count and $user->orders_sum_total
```

**Interview Tip:** Start with "I normalize by default and denormalize when performance requires it."

---

### Q4. How do you optimize Eloquent queries for large datasets?

**Answer:**

**1. Use chunking for large datasets:**

```php
// DON'T — loads all 1M records into memory
User::all()->each(fn($user) => processUser($user));

// DO — processes 1000 at a time
User::chunk(1000, function ($users) {
    foreach ($users as $user) {
        processUser($user);
    }
});

// Even better — lazy loading
User::lazy(1000)->each(fn($user) => processUser($user));
```

**2. Select only needed columns:**

```php
$users = User::select('id', 'name', 'email')
    ->with('posts:id,user_id,title')
    ->get();
```

**3. Use `cursor()` for minimal memory:**

```php
foreach (User::where('active', true)->cursor() as $user) {
    // Uses PHP generator — one record in memory at a time
}
```

**4. Use DB raw for complex aggregations:**

```php
$stats = Order::select(
    DB::raw('DATE(created_at) as date'),
    DB::raw('COUNT(*) as count'),
    DB::raw('SUM(total) as revenue')
)->groupBy('date')->orderBy('date')->get();
```

**Interview Tip:** Show you know the memory implications. `chunk()` vs `cursor()` vs `lazy()` — explain when to use each.

---

### Q5. Explain database transactions in Laravel. When and how do you use them?

**Answer:**

Transactions ensure a group of database operations either ALL succeed or ALL fail.

```php
// Closure-based (auto commits/rolls back)
DB::transaction(function () use ($order, $paymentData) {
    $payment = Payment::create([
        'order_id' => $order->id,
        'amount' => $order->total,
        'status' => 'completed',
    ]);

    $order->update(['status' => 'paid']);

    foreach ($order->items as $item) {
        $item->product->decrement('stock', $item->quantity);
    }
}, 3); // retry 3 times on deadlock

// Manual transaction
DB::beginTransaction();
try {
    $payment = Payment::create([...]);
    $order->update(['status' => 'paid']);
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}
```

**Important considerations:**
- Transactions lock rows — keep them short to avoid deadlocks.
- Don't put API calls inside transactions.
- Use `afterCommit` on queued jobs.

```php
ProcessPayment::dispatch($order)->afterCommit();
```

**Interview Tip:** Mention `afterCommit()` for queued jobs and keeping transactions short.

---

### Q6. What are database migrations best practices in a team environment?

**Answer:**

1. **Never modify existing migrations** that have been run in production.
2. **Always include `down()` method** for rollbacks.
3. **Use descriptive names.**
4. **Handle data migrations separately** from schema migrations.
5. **Foreign key conventions:**

```php
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
```

6. **Production deployment:** `php artisan migrate --force`.
7. **Squash migrations** when unwieldy: `php artisan schema:dump`.

**Interview Tip:** Mention you never edit existing migrations, use `schema:dump` for large projects, and separate data migrations.

---

### Q7. How do you implement soft deletes and what are the gotchas?

**Answer:**

```php
// Migration
$table->softDeletes();

// Model
class Order extends Model
{
    use SoftDeletes;
}
```

**Gotchas:**

1. **Unique constraints fail:** Soft-deleted user with same email blocks new registration.
2. **Cascade deletes don't trigger soft delete:**

```php
protected static function booted()
{
    static::deleting(function ($order) {
        $order->items()->delete(); // Soft deletes items too
    });
    static::restoring(function ($order) {
        $order->items()->restore();
    });
}
```

3. **Growing table size** — Soft-deleted records accumulate. Schedule cleanup.

**Interview Tip:** The unique constraint gotcha separates experienced devs from beginners.

---

### Q8. Explain the difference between `hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, and polymorphic relationships.

**Answer:**

```php
// hasOne — User has one Profile (FK on profiles table)
public function profile() { return $this->hasOne(Profile::class); }

// hasMany — User has many Posts (FK on posts table)
public function posts() { return $this->hasMany(Post::class); }

// belongsTo — Post belongs to User (FK on posts table: user_id)
public function user() { return $this->belongsTo(User::class); }

// belongsToMany — User has many Roles through pivot (role_user table)
public function roles() {
    return $this->belongsToMany(Role::class)->withPivot('assigned_at')->withTimestamps();
}

// Polymorphic — One Comment model for Posts AND Videos
class Comment extends Model {
    public function commentable() { return $this->morphTo(); }
}
class Post extends Model {
    public function comments() { return $this->morphMany(Comment::class, 'commentable'); }
}

// Has Many Through
class Country extends Model {
    public function posts() { return $this->hasManyThrough(Post::class, User::class); }
}
```

**Interview Tip:** Draw the FK placement: `hasOne`/`hasMany` = FK on related table, `belongsTo` = FK on current table.

---

### Q9. How do you handle database deadlocks and race conditions in Laravel?

**Answer:**

**Race condition example:** Two users buying the last item.

```php
// PROBLEM — Race condition
$product = Product::find(1);
if ($product->stock > 0) {
    $product->decrement('stock'); // Both decrement — stock goes to -1!
}

// SOLUTION 1: Pessimistic Locking
DB::transaction(function () {
    $product = Product::where('id', 1)->lockForUpdate()->first();
    if ($product->stock > 0) {
        $product->decrement('stock');
        Order::create([...]);
    }
});

// SOLUTION 2: Atomic operation
$affected = DB::table('products')
    ->where('id', 1)
    ->where('stock', '>', 0)
    ->decrement('stock');

if ($affected) {
    Order::create([...]);
}

// SOLUTION 3: Redis distributed lock
Cache::lock('product-1-purchase', 10)->block(5, function () {
    // Only one process at a time
});
```

**Interview Tip:** Show three approaches: pessimistic locking, atomic operations, and distributed locks. Mention consistent ordering to prevent deadlocks.

---

### Q10. How do you design and optimize a MySQL schema for a multi-tenant SaaS application?

**Answer:**

**Shared DB with tenant_id (most common for SaaS):**

```php
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if (auth()->check()) {
            $builder->where('tenant_id', auth()->user()->tenant_id);
        }
    }
}

trait BelongsToTenant
{
    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);
        static::creating(function ($model) {
            $model->tenant_id = auth()->user()->tenant_id;
        });
    }
}

class Order extends Model
{
    use BelongsToTenant;
}
```

**Optimization:**
1. Index `tenant_id` on every table — always first in composite indexes.
2. Composite index: `(tenant_id, status, created_at)`.
3. Partitioning by `tenant_id` for very large tables.

**Interview Tip:** Explain the trade-offs between isolation levels. Mention the security risk of forgetting the tenant scope.

---