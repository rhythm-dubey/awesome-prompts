## ROUND 8 — Debugging & Problem-Solving Scenarios

---

### Q1. A user reports that your Laravel app is returning a 500 error intermittently. How do you debug it?

**Answer:**

**Step 1: Check logs immediately**
```bash
tail -100 storage/logs/laravel.log
tail -100 /var/log/nginx/error.log
tail -100 /var/log/php8.2-fpm.log
```

**Step 2: Reproduce the issue** — user-specific, route-specific, or time-specific?

**Step 3: Common intermittent 500 error causes:**

1. **Database connection exhaustion:**
```
Symptom: "SQLSTATE[HY000] [2002] Connection refused" intermittently
Fix: Check max_connections, increase pool size
```

2. **Redis connection timeout:**
```
Symptom: "Connection timed out" with Redis cache/session
Fix: Check Redis memory, connection limits
```

3. **Memory limit exceeded:**
```
Symptom: "Allowed memory size exhausted"
Fix: Find the memory-heavy operation, optimize
```

4. **External API timeout:**
```php
// Fix: Add timeouts and retry
Http::timeout(5)->retry(3, 100)->get('https://api.external.com/data');
```

5. **File permission issues after deployment:**
```bash
chown -R www-data:www-data storage bootstrap/cache
```

**Step 4: Add monitoring** — Sentry alerting, health checks, CloudWatch alarms.

**Interview Tip:** Show a systematic approach: logs -> reproduce -> common causes -> monitor. Don't jump to "restart the server."

---

### Q2. You see "Class 'App\Models\User' not found" in production but it works locally. What happened?

**Answer:**

**Most likely causes:**

**1. Case sensitivity (most common):**
```
Linux (production) is case-sensitive, macOS (local) is not
File: app/Models/user.php  (lowercase 'u')
Code: use App\Models\User;  (uppercase 'U')
Works on macOS, FAILS on Linux
```

**2. Composer autoloader not updated:**
```bash
composer dump-autoload --optimize
```

**3. Config cache referencing old namespace:**
```bash
php artisan config:clear
php artisan config:cache
```

**4. Missing file from deployment** — check git history.

**5. Composer `--no-dev` removed a dev dependency.**

**Quick fix checklist:**
```bash
composer dump-autoload --optimize
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

**Interview Tip:** Immediately mention the **case sensitivity** issue — it's the #1 cause of "works on Mac, fails on Linux."

---

### Q3. Queue workers consume jobs but jobs keep failing silently. How do you investigate?

**Answer:**

**Step 1: Check `failed_jobs` table:**
```bash
php artisan queue:failed
```

**Step 2: If jobs disappear without appearing in failed_jobs:**

```php
// 1. No failed() method — add one for logging
public function failed(\Throwable $exception)
{
    Log::error('Job failed', [
        'job' => static::class,
        'exception' => $exception->getMessage(),
    ]);
}

// 2. Job timeout — increase $timeout
public $timeout = 120;

// 3. Memory limit — worker restarts silently
// Run: php artisan queue:work --memory=256

// 4. Serialization error — model deleted between dispatch and handle
public $deleteWhenMissingModels = true;

// 5. Payload too large for queue driver
// SQS max: 256KB — pass IDs, not full models
// BAD:  ProcessOrder::dispatch($orderWithAllRelations);
// GOOD: ProcessOrder::dispatch($order->id);
```

**Step 3: Enable verbose logging:**
```bash
php artisan queue:work --verbose 2>&1 | tee /tmp/queue-debug.log
```

**Step 4: Check Horizon dashboard** for failed jobs and metrics.

**Interview Tip:** Mention `$deleteWhenMissingModels`, payload size limits, and the difference between job failure and worker crash.

---

### Q4. A developer reports that `$user->orders` returns empty but the database has orders. What could be wrong?

**Answer:**

**Debugging checklist:**

```php
// 1. Check relationship definition
// Does Order table have user_id? Or is FK named differently?
return $this->hasMany(Order::class, 'customer_id');

// 2. Check soft deletes
$user->orders()->withTrashed()->get();

// 3. Check global scopes
$user->orders()->withoutGlobalScopes()->get();

// 4. Check actual IDs match
DB::table('orders')->where('user_id', $user->id)->get();

// 5. Check database connection
dd(config('database.default'), DB::connection()->getDatabaseName());

// 6. Check cache
Cache::flush();
$user->refresh();
$user->load('orders');

// 7. Check the actual SQL generated
$query = $user->orders()->toSql();
$bindings = $user->orders()->getBindings();
Log::info("Query: {$query}", ['bindings' => $bindings]);
```

**Interview Tip:** The most common causes are global scopes, soft deletes, and wrong foreign key names. Show that you check actual SQL with `toSql()`.

---

### Q5. Your Laravel app suddenly uses 100% CPU. How do you diagnose and fix it?

**Answer:**

**Immediate triage:**
```bash
top -c        # Check what processes use CPU
```

**Common causes:**

1. **Infinite loop in code** — Check recent deployments.
2. **Runaway queue worker** — Failed job retrying endlessly.
3. **N+1 query explosion** — New endpoint with lazy loading.
4. **Scheduler running overlapping tasks:**
```php
$schedule->command('heavy:task')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
```

5. **External service timeout causing process buildup:**
```php
Http::timeout(10)->get('https://slow-api.com/data');
```

**Emergency response:**
```bash
# 1. Restart PHP-FPM
sudo systemctl restart php8.2-fpm

# 2. Check if it recurs immediately (code issue) or gradually (traffic spike)
# 3. Scale horizontally if traffic-related
```

**Interview Tip:** Show a clear emergency plan: identify process -> check recent changes -> common causes -> fix -> prevent recurrence.

---

### Q6. You deploy code and users report old data showing. What's the caching issue?

**Answer:**

**Check all cache layers systematically:**

```bash
# 1. Config cache
php artisan config:clear && php artisan config:cache

# 2. Route cache
php artisan route:clear && php artisan route:cache

# 3. View cache
php artisan view:clear && php artisan view:cache

# 4. Application cache
php artisan cache:clear

# 5. OPcache (PHP bytecode cache)
sudo systemctl reload php8.2-fpm

# 6. Queue workers (they cache old code in memory!)
php artisan queue:restart

# 7. CDN cache
# Invalidate CloudFront or use cache-busting: mix.version()
```

**Proper deployment script:**
```bash
#!/bin/bash
cd /var/www/myapp
git pull origin main
composer install --optimize-autoloader --no-dev
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan cache:clear
php artisan queue:restart
sudo systemctl reload php8.2-fpm
```

**Interview Tip:** List ALL cache layers: config -> route -> view -> application -> OPcache -> CDN -> browser. The most missed one is `queue:restart`.

---

### Q7. A form works locally but returns 419 (CSRF token mismatch) in production. Why?

**Answer:**

**Common causes:**

```php
// 1. SESSION_SECURE_COOKIE=true but hitting HTTP
// If ALB terminates SSL, Laravel thinks it's HTTP
// Fix: Configure TrustProxies middleware
class TrustProxies extends Middleware
{
    protected $proxies = '*';
}

// 2. Wrong SESSION_DOMAIN
SESSION_DOMAIN=.myapp.com  // Include dot for subdomain support

// 3. File sessions on load-balanced servers
// User's session on Server A, request goes to Server B
// Fix: Use Redis sessions
SESSION_DRIVER=redis

// 4. Session expired (form open too long)
SESSION_LIFETIME=120

// 5. Redis connection issue
// If session driver is Redis but Redis is down

// Quick diagnostic:
Log::info('Session ID: ' . session()->getId());
Log::info('Token in session: ' . session()->token());
Log::info('Token in request: ' . request()->input('_token'));
```

**Interview Tip:** The most common cause is `SESSION_SECURE_COOKIE=true` with SSL termination at the load balancer plus missing `TrustProxies`.

---

### Q8. Write buggy code, then explain how to identify and fix it.

**Answer:**

**Buggy code:**
```php
public function applyDiscount(Request $request, Order $order)
{
    $discount = Discount::where('code', $request->code)->first();

    if ($discount->is_active && $discount->usage_count < $discount->max_usage) {
        $newTotal = $order->total - ($order->total * $discount->percentage / 100);
        $order->update(['total' => $newTotal, 'discount_id' => $discount->id]);
        $discount->increment('usage_count');
        return response()->json(['new_total' => $newTotal]);
    }

    return response()->json(['error' => 'Invalid discount'], 400);
}
```

**Bugs identified:**
1. **No null check** — `$discount` can be null -> error on `$discount->is_active`.
2. **No input validation** on `$request->code`.
3. **No authorization** — any user can apply discount to any order.
4. **Race condition** — two requests can exceed `max_usage`.
5. **No check if discount already applied.**
6. **Negative total possible** if percentage > 100.

**Fixed code:**
```php
public function applyDiscount(ApplyDiscountRequest $request, Order $order)
{
    $this->authorize('update', $order);

    if ($order->discount_id) {
        return response()->json(['error' => 'Discount already applied'], 422);
    }

    return DB::transaction(function () use ($request, $order) {
        $discount = Discount::where('code', $request->validated('code'))
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();

        if (!$discount || $discount->usage_count >= $discount->max_usage) {
            return response()->json(['error' => 'Invalid or expired discount'], 400);
        }

        $discountAmount = $order->total * min($discount->percentage, 100) / 100;
        $newTotal = max(0, $order->total - $discountAmount);

        $order->update(['total' => $newTotal, 'discount_id' => $discount->id]);
        $discount->increment('usage_count');

        return response()->json(['new_total' => $newTotal]);
    });
}
```

**Interview Tip:** Identify security issues (authorization, validation), data integrity (race conditions), and edge cases (null, boundaries).

---

### Q9. API response times degraded from 200ms to 2s over a month. Nothing changed in code. What do you investigate?

**Answer:**

**Investigation in order of likelihood:**

**1. Data growth — more data = slower queries:**
```sql
SELECT table_name, table_rows, data_length/1024/1024 AS data_mb
FROM information_schema.tables
WHERE table_schema = 'myapp'
ORDER BY data_length DESC;
```

**2. Missing or degraded indexes** — Run EXPLAIN on slow queries.

**3. Resource exhaustion:**
```bash
free -h          # Memory — is MySQL swapping?
df -h            # Disk full?
```

**4. External service degradation** — Third-party APIs responding slower.

**5. Cache eviction — Redis out of memory:**
```bash
redis-cli INFO memory
```

**6. Log file bloat** — Laravel log growing indefinitely.

**Action plan:**
1. Identify slowest endpoints (APM tool or access logs).
2. Profile those endpoints (Debugbar, Telescope, EXPLAIN).
3. Address root cause (index, cache, query optimization).
4. Add alerting for early detection.

**Interview Tip:** Key insight: "nothing in code changed but data grew." Performance degrades with data growth. Systematic investigation beats guessing.

---

### Q10. A colleague's PR has this code. What feedback would you give?

```php
public function getUsers(Request $request)
{
    $users = DB::select("SELECT * FROM users WHERE name LIKE '%" . $request->search . "%'");
    $output = '';
    foreach ($users as $user) {
        $output .= '<div>' . $user->name . ' - ' . $user->email . '</div>';
    }
    return $output;
}
```

**Answer:**

**Critical issues:**

1. **SQL Injection (CRITICAL):**
```php
// Attack: $request->search = "'; DROP TABLE users; --"
// Fix: Use Eloquent or parameterized queries
$users = User::where('name', 'LIKE', '%' . $request->search . '%')->get();
```

2. **XSS Vulnerability (CRITICAL):**
```php
// Attack: User with name "<script>steal_cookies()</script>"
// Fix: Use e() helper or Blade templates
$output .= '<div>' . e($user->name) . ' - ' . e($user->email) . '</div>';
```

3. **SELECT * — fetches all columns unnecessarily.**

4. **No pagination — crashes with large datasets.**

5. **No input validation.**

6. **Building HTML in controller — violates MVC.**

7. **No authorization check.**

**Review comment:** "This has two critical security vulnerabilities (SQL injection and XSS) that must be fixed before merging. I'd recommend using Eloquent with pagination and returning a proper API Resource."

**Interview Tip:** Prioritize security issues first, then architecture. Be constructive — suggest fixes, don't just point out problems.

---