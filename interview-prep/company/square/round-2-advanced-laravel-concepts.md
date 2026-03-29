## ROUND 2 — Advanced Laravel Concepts

---

### Q1. Explain Queues and Jobs in Laravel. How do you handle failed jobs?

**Answer:**

**Queues** defer time-consuming tasks (sending emails, processing images, API calls) to a background worker, keeping HTTP responses fast.

**Creating and dispatching a job:**

```bash
php artisan make:job ProcessPayment
```

```php
class ProcessPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function handle(PaymentService $service)
    {
        $service->charge($this->order);
    }

    public function failed(\Throwable $exception)
    {
        // Notify admin, log error, etc.
    }

    public $tries = 3;
    public $backoff = [60, 300, 900]; // Exponential backoff
}

// Dispatch
ProcessPayment::dispatch($order)->onQueue('payments');
```

**Handling failures:**
1. Set `$tries` and `$backoff` on the job.
2. Implement `failed()` method for cleanup/notification.
3. Failed jobs land in the `failed_jobs` table: `php artisan queue:failed-table`.
4. Retry failed jobs: `php artisan queue:retry all` or `queue:retry {id}`.
5. Use `php artisan queue:failed` to list failures.

**Queue drivers:** `sync` (local), `database`, `redis` (recommended for production), `sqs` (AWS).

**Interview Tip:** Mention production setup — Supervisor for process management, Redis as driver, Horizon for monitoring. Explain when to use `dispatchAfterResponse()` vs full queue.

---

### Q2. What are Events and Listeners in Laravel? When would you use them over direct calls?

**Answer:**

Events implement the Observer pattern — decouple actions from their side effects.

```bash
php artisan make:event OrderPlaced
php artisan make:listener SendOrderConfirmation --event=OrderPlaced
php artisan make:listener UpdateInventory --event=OrderPlaced
```

```php
// Event
class OrderPlaced
{
    public function __construct(public Order $order) {}
}

// Listener
class SendOrderConfirmation implements ShouldQueue
{
    public function handle(OrderPlaced $event)
    {
        Mail::to($event->order->user)->send(new OrderConfirmationMail($event->order));
    }
}

// Registration in EventServiceProvider
protected $listen = [
    OrderPlaced::class => [
        SendOrderConfirmation::class,
        UpdateInventory::class,
        NotifyWarehouse::class,
    ],
];

// Dispatching
OrderPlaced::dispatch($order);
```

**When to use events over direct calls:**
- When one action triggers multiple side effects (order -> email + inventory + analytics).
- When side effects may change or grow over time — just add new listeners.
- When you want listeners to run asynchronously via `ShouldQueue`.
- When modules shouldn't know about each other (decoupling).

**When NOT to use events:** Simple, single-consequence actions where direct calls are clearer.

**Interview Tip:** Mention event subscribers (`EventSubscriber`), model events/observers, and that `ShouldQueue` on listeners is the real power — making side effects non-blocking.

---

### Q3. Explain Laravel's authentication ecosystem — Sanctum vs Passport. When to use which?

**Answer:**

| Feature | Sanctum | Passport |
|---------|---------|----------|
| Use case | SPA, mobile apps, simple token APIs | Full OAuth2 server |
| Complexity | Lightweight | Heavy, full OAuth2 |
| Token type | Simple personal access tokens + SPA cookie | OAuth2 access/refresh tokens |
| Grant types | N/A | Authorization code, client credentials, password, implicit |
| Third-party access | No | Yes (third-party apps can request access) |
| Setup | Minimal | Requires encryption keys, migrations, client setup |

**Use Sanctum when:**
- Your API is consumed by your own SPA or mobile app.
- You need simple token authentication without OAuth complexity.
- SPA authentication using session cookies (first-party).

**Use Passport when:**
- You're building an API that third-party developers will consume (like a public API).
- You need OAuth2 flows (authorization codes, refresh tokens, scopes).
- You need machine-to-machine authentication (client credentials).

```php
// Sanctum — Token creation
$token = $user->createToken('mobile-app', ['orders:read'])->plainTextToken;
```

**Interview Tip:** If asked "which do you use?", say Sanctum for most projects because OAuth2 is overkill unless you have third-party consumers. Mention token abilities (scopes) in Sanctum.

---

### Q4. What is the Repository Pattern? How do you implement it in Laravel?

**Answer:**

The Repository Pattern abstracts the data access layer, providing a clean separation between business logic and database queries.

```php
// Interface
interface OrderRepositoryInterface
{
    public function find(int $id): ?Order;
    public function getByUser(int $userId, array $filters = []): LengthAwarePaginator;
    public function create(array $data): Order;
    public function updateStatus(int $id, string $status): bool;
}

// Implementation
class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function __construct(private Order $model) {}

    public function find(int $id): ?Order
    {
        return $this->model->with(['items', 'user'])->find($id);
    }

    public function getByUser(int $userId, array $filters = []): LengthAwarePaginator
    {
        return $this->model
            ->where('user_id', $userId)
            ->when($filters['status'] ?? null, fn($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15);
    }
}

// Bind in Service Provider
$this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);

// Controller
class OrderController extends Controller
{
    public function __construct(private OrderRepositoryInterface $orders) {}

    public function index(Request $request)
    {
        return $this->orders->getByUser(auth()->id(), $request->all());
    }
}
```

**Benefits:** Testability (mock the interface), swappable data sources, single responsibility.
**Criticism:** Can add unnecessary abstraction if you're always using Eloquent.

**Interview Tip:** Be balanced — mention both benefits and the overhead. Interviewers respect candidates who don't blindly follow patterns but apply them judiciously.

---

### Q5. Explain Laravel's Task Scheduling. How is it better than raw cron jobs?

**Answer:**

Laravel's scheduler lets you define all scheduled tasks in `App\Console\Kernel::schedule()` instead of managing multiple cron entries on the server.

You only need ONE cron entry:

```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('orders:cleanup-expired')
        ->daily()
        ->withoutOverlapping()
        ->onOneServer()
        ->emailOutputOnFailure('admin@example.com');

    $schedule->job(new ProcessPendingPayments)->everyFiveMinutes();

    $schedule->command('reports:generate')
        ->weekdays()
        ->at('09:00')
        ->when(fn() => config('features.reports_enabled'))
        ->runInBackground();
}
```

**Advantages over raw cron:**
1. Version-controlled with your code.
2. `withoutOverlapping()` prevents duplicate runs.
3. `onOneServer()` ensures only one server runs it in multi-server setups.
4. Built-in output logging, email notifications, hooks (`before`, `after`, `onSuccess`, `onFailure`).
5. Easy to test.

**Interview Tip:** Mention `onOneServer()` for load-balanced environments and `withoutOverlapping()` for long-running tasks. These show production experience.

---

### Q6. What is the N+1 Query Problem? How do you detect and fix it?

**Answer:**

The N+1 problem occurs when you fetch a collection of N records and then make 1 additional query for each record to load a relationship.

```php
// BAD — N+1 problem (1 query for posts + N queries for each post's author)
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->author->name;   // SELECT * FROM users WHERE id = ? (runs N times)
}

// GOOD — Eager loading (2 queries total)
$posts = Post::with('author')->get();
// SELECT * FROM posts
// SELECT * FROM users WHERE id IN (1, 2, 3, ...)
```

**Detection methods:**
1. **Laravel Debugbar** — Shows query count and duplicates.
2. **`Model::preventLazyLoading()`** in `AppServiceProvider::boot()` (Laravel 9+).
3. **Telescope** — Query tab shows all queries per request.

```php
// In AppServiceProvider::boot()
Model::preventLazyLoading(!app()->isProduction());
```

**Fix patterns:**
- `with()` — Eager load when fetching.
- `load()` — Lazy eager load after fetching.
- `withCount()` — When you only need counts.
- `select()` + `with()` — Load only needed columns.

**Interview Tip:** Mention `preventLazyLoading()` — it's a modern approach. Also explain that you've used Debugbar or Telescope to catch these in development.

---

### Q7. Explain caching strategies in Laravel. What do you cache and how?

**Answer:**

**Cache drivers:** `file` (default), `redis` (recommended), `memcached`, `database`, `dynamodb`, `array` (testing).

**What to cache:**
1. Database queries — Expensive aggregations, frequently read data.
2. API responses — Third-party API results.
3. Configuration — `php artisan config:cache`.
4. Routes — `php artisan route:cache`.
5. Views — `php artisan view:cache`.
6. Computed values — Dashboard stats, leaderboards.

```php
// Basic cache usage
$users = Cache::remember('active-users', 3600, function () {
    return User::where('active', true)->with('roles')->get();
});

// Cache tags (Redis/Memcached only)
Cache::tags(['users', 'admins'])->put('admin-list', $admins, 3600);
Cache::tags('users')->flush();

// Model-level cache invalidation
class User extends Model
{
    protected static function booted()
    {
        static::saved(fn() => Cache::forget('active-users'));
        static::deleted(fn() => Cache::forget('active-users'));
    }
}
```

**Cache invalidation strategies:**
- **TTL-based** — Set expiration time.
- **Event-based** — Clear cache when data changes (model events).
- **Tag-based** — Group related cache entries, flush by tag.
- **Cache-aside** — App checks cache first, queries DB on miss, stores result.

**Interview Tip:** Mention your invalidation strategy — don't just say you cache things. Talk about cache warming for critical data.

---

### Q8. What are Eloquent Accessors, Mutators, and Casts? Give practical examples.

**Answer:**

**Accessors** — Transform attribute values when reading:

```php
// Laravel 9+ syntax
protected function fullName(): Attribute
{
    return Attribute::make(
        get: fn() => "{$this->first_name} {$this->last_name}",
    );
}
```

**Mutators** — Transform values when setting:

```php
protected function email(): Attribute
{
    return Attribute::make(
        set: fn(string $value) => strtolower($value),
    );
}
```

**Casts** — Automatic type conversion:

```php
protected $casts = [
    'is_admin' => 'boolean',
    'settings' => 'array',
    'amount' => 'decimal:2',
    'published_at' => 'datetime',
    'status' => OrderStatus::class,  // PHP 8.1 Enum cast
    'address' => AddressValueObject::class, // Custom cast
];
```

**Custom Cast:**

```php
class MoneyCast implements CastsAttributes
{
    public function get($model, $key, $value, $attributes)
    {
        return new Money($value, $attributes['currency']);
    }

    public function set($model, $key, $value, $attributes)
    {
        return ['amount' => $value->amount, 'currency' => $value->currency];
    }
}
```

**Interview Tip:** Use the Laravel 9+ `Attribute` syntax, not the old `getXAttribute`/`setXAttribute`. Mention enum casting — it shows you use modern PHP and Laravel together.

---

### Q9. Explain how you would implement Role-Based Access Control (RBAC) in Laravel.

**Answer:**

**Database structure:**
```
users -> role_user (pivot) -> roles -> permission_role (pivot) -> permissions
```

```php
class User extends Authenticatable
{
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('slug', $role)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn($q) => $q->where('slug', $permission))
            ->exists();
    }
}

// Gate registration in AuthServiceProvider
Gate::before(function ($user, $ability) {
    if ($user->hasRole('super-admin')) {
        return true;
    }
});

Gate::define('edit-post', function ($user, Post $post) {
    return $user->hasPermission('edit-posts') || $user->id === $post->user_id;
});

// Policy
class PostPolicy
{
    public function update(User $user, Post $post): bool
    {
        return $user->hasPermission('edit-posts') || $user->id === $post->user_id;
    }
}
```

**In practice**, most teams use **Spatie Laravel Permission** package which handles all of this with a well-tested, optimized implementation.

**Interview Tip:** Mention Spatie Permission but also show you understand the underlying concepts (gates, policies, middleware). Explain caching roles/permissions to avoid repeated queries.

---

### Q10. What are Laravel Pipelines and how do they relate to middleware?

**Answer:**

Laravel's middleware system is built on top of the Pipeline pattern. You can use Pipelines for any sequential processing workflow.

```php
use Illuminate\Pipeline\Pipeline;

$order = app(Pipeline::class)
    ->send($order)
    ->through([
        ValidateOrderItems::class,
        ApplyDiscountCodes::class,
        CalculateTax::class,
        CalculateShipping::class,
        FinalizeTotal::class,
    ])
    ->thenReturn();

// Each stage
class ApplyDiscountCodes
{
    public function handle(Order $order, Closure $next)
    {
        if ($order->discount_code) {
            $discount = DiscountCode::findByCode($order->discount_code);
            $order->discount_amount = $discount->calculate($order->subtotal);
        }
        return $next($order);
    }
}
```

**Use cases beyond middleware:**
- Multi-step form processing.
- Data transformation pipelines (import/export).
- Order/payment processing with multiple validation steps.
- Content filtering (profanity -> spam -> formatting).

**Interview Tip:** This is an advanced question. Showing you've used Pipelines outside of middleware demonstrates architectural thinking.

---