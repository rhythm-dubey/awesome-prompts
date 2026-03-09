## Laravel/PHP Senior Interview Q&A (3–4 Years)

Below is a senior-level curated interview preparation set for a Laravel/PHP developer with ~3–4 years of experience. Answers are concise, practical, and interview-ready.

---

### 1. Laravel Core (10 Questions)

#### 1. What is the Laravel Service Container?

**Answer:**  
The Service Container is Laravel’s dependency injection container used to manage class dependencies automatically. It resolves dependencies using constructor injection.

```php
class UserController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }
}
```

#### 2. What are Service Providers in Laravel?

**Answer:**  
Service providers are the central place to register services, bindings, and event listeners.

Common providers include:

- `AppServiceProvider`
- `AuthServiceProvider`
- `EventServiceProvider`
- `RouteServiceProvider`

Example binding:

```php
$this->app->bind(
    PaymentInterface::class,
    StripePayment::class
);
```

#### 3. Difference between `bind()` and `singleton()`?

**Answer:**

| Method       | Behavior                 |
|-------------|--------------------------|
| `bind()`    | New instance every time  |
| `singleton()` | Same instance reused   |

Example:

```php
$this->app->singleton(Logger::class, FileLogger::class);
```

#### 4. What is Middleware?

**Answer:**  
Middleware filters HTTP requests before they reach controllers.

Common use cases:

- Authentication
- Logging
- Rate limiting

Example:

```php
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class);
});
```

#### 5. What is Eloquent ORM?

**Answer:**  
Eloquent is Laravel's Active Record ORM used for database interaction. Each table corresponds to a model class.

```php
$user = User::find(1);
$posts = $user->posts;
```

#### 6. Difference between `hasOne` and `belongsTo`

**Answer:**

| Relation    | Meaning               |
|------------|-----------------------|
| `hasOne`   | Parent owns child     |
| `belongsTo` | Child references parent |

Example:

```php
class User extends Model
{
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }
}
```

#### 7. What is Route Model Binding?

**Answer:**  
Route model binding automatically injects model instances into routes.

```php
Route::get('/users/{user}', function (User $user) {
    return $user;
});
```

Laravel automatically fetches the record by the route parameter.

#### 8. What is the difference between Collection and Query Builder?

**Answer:**

| Feature     | Collection | Query Builder |
|------------|------------|---------------|
| Runs in    | PHP        | Database      |
| Performance | Slower     | Faster        |

Example:

```php
User::all()->filter();

User::where('status', 1)->get();
```

#### 9. What is Lazy Loading vs Eager Loading?

**Answer:**

- **Lazy loading (N+1 problem):**

```php
$users = User::all();

foreach ($users as $user) {
    $posts = $user->posts;
}
```

- **Eager loading:**

```php
$users = User::with('posts')->get();
```

#### 10. What are Laravel Facades?

**Answer:**  
Facades provide a static interface to classes in the service container.

```php
Cache::put('key', 'value', 60);
```

The facade resolves the underlying service from the container.

---

### 2. PHP OOP & Design Principles (8 Questions)

#### 11. What are the four pillars of OOP?

**Answer:**

- Encapsulation  
- Inheritance  
- Polymorphism  
- Abstraction  

#### 12. What is Dependency Injection?

**Answer:**  
Passing dependencies into a class instead of creating them inside the class.

Bad:

```php
class Order
{
    private StripePayment $payment;

    public function __construct()
    {
        $this->payment = new StripePayment();
    }
}
```

Good:

```php
class Order
{
    private PaymentInterface $payment;

    public function __construct(PaymentInterface $payment)
    {
        $this->payment = $payment;
    }
}
```

#### 13. What is SOLID?

**Answer:**

| Principle | Meaning                |
|----------|------------------------|
| S        | Single Responsibility  |
| O        | Open/Closed            |
| L        | Liskov Substitution    |
| I        | Interface Segregation  |
| D        | Dependency Inversion   |

#### 14. Interface vs Abstract Class?

**Answer:**

| Interface                        | Abstract Class            |
|----------------------------------|--------------------------|
| Only method signatures           | Can contain logic        |
| Multiple interfaces allowed      | Single inheritance       |

#### 15. What is Method Overriding?

**Answer:**  
Child class redefining a parent method.

```php
class Animal
{
    public function sound()
    {
    }
}

class Dog extends Animal
{
    public function sound()
    {
        return 'Bark';
    }
}
```

#### 16. What is a Trait in PHP?

**Answer:**  
Traits allow code reuse across classes.

```php
trait Logger
{
    public function log(string $msg)
    {
    }
}

class UserService
{
    use Logger;
}
```

#### 17. What is the Repository Pattern?

**Answer:**  
The repository pattern separates data access logic from business logic.

Controller → Repository → Model

#### 18. What is the Dependency Inversion Principle?

**Answer:**  
High-level modules should depend on abstractions, not concrete classes.

Example:

```text
PaymentInterface
    ├─ StripePayment
    └─ RazorpayPayment
```

---

### 3. Database & Query Optimization (8 Questions)

#### 19. What is Indexing?

**Answer:**  
Indexes improve query performance by avoiding full table scans.

```sql
CREATE INDEX idx_email ON users(email);
```

#### 20. What is the N+1 Query Problem?

**Answer:**  
Multiple unnecessary queries caused by lazy loading in loops.

Example:

```php
$users = User::all();

foreach ($users as $user) {
    $posts = $user->posts;
}
```

Solution (eager loading):

```php
User::with('posts')->get();
```

#### 21. Difference between `where()` and `having()`?

**Answer:**

| Clause  | Usage                |
|---------|----------------------|
| `where` | Before aggregation   |
| `having` | After aggregation   |

#### 22. What is Query Builder?

**Answer:**  
A fluent interface to build SQL queries.

```php
DB::table('users')
    ->where('status', 1)
    ->get();
```

#### 23. When should you use pagination?

**Answer:**  
When you need to avoid loading huge datasets in a single request.

```php
User::paginate(20);
```

#### 24. How do you analyze slow queries?

**Answer:**  
Use tools like:

- `EXPLAIN`  
- Slow query log  
- Laravel Telescope  

Example:

```sql
EXPLAIN SELECT * FROM users;
```

#### 25. Difference between `chunk()` and `cursor()`?

**Answer:**

| Method    | Behavior                |
|-----------|-------------------------|
| `chunk()` | Loads records in batches |
| `cursor()` | Streams rows, memory efficient |

Example:

```php
User::chunk(100, function ($users) {
    //
});
```

#### 26. What is a Database Transaction?

**Answer:**  
A transaction ensures data integrity by committing all operations or none.

```php
DB::transaction(function () {
    Order::create();
    Payment::create();
});
```

---

### 4. API Design & Security (6 Questions)

#### 27. What is REST?

**Answer:**  
REST uses HTTP methods to operate on resources:

| Method | Purpose |
|--------|---------|
| GET    | Fetch   |
| POST   | Create  |
| PUT    | Update  |
| DELETE | Remove  |

#### 28. What is API Rate Limiting?

**Answer:**  
Rate limiting prevents abuse by limiting the number of requests per client.

Laravel example:

```php
Route::middleware('throttle:60,1');
```

#### 29. What is API Authentication in Laravel?

**Answer:**  
Common options:

- Laravel Sanctum  
- Laravel Passport  
- JWT-based auth  

#### 30. How do you secure APIs?

**Answer:**  
Use:

- Authentication tokens  
- Input validation  
- Rate limiting  
- HTTPS  
- CORS configuration  

#### 31. What is API Versioning?

**Answer:**  
Having multiple versions of an API to maintain backward compatibility.

Examples:

- `/api/v1/users`  
- `/api/v2/users`  

#### 32. What is Idempotency?

**Answer:**  
Multiple identical API calls produce the same result.

Example:

```http
PUT /order/1
```

---

### 5. Queue System & Background Jobs (5 Questions)

#### 33. What are Laravel Queues?

**Answer:**  
Queues handle background tasks asynchronously.

Examples:

- Emails  
- Payment processing  
- Report generation  

#### 34. What queue drivers does Laravel support?

**Answer:**  
Common drivers:

- Redis  
- Database  
- SQS  
- Beanstalkd  

#### 35. Example of a Job

```php
class SendEmailJob implements ShouldQueue
{
    public function handle()
    {
        Mail::send();
    }
}

SendEmailJob::dispatch();
```

#### 36. What is Laravel Horizon?

**Answer:**  
Horizon is a dashboard to monitor Redis queues.

Key features:

- Job metrics  
- Failed jobs tracking  
- Worker monitoring  

#### 37. What are Failed Jobs?

**Answer:**  
Failed jobs are jobs that throw exceptions or cannot be processed.

Retry example:

```bash
php artisan queue:retry all
```

---

### 6. Payment Gateway / Stripe Integration (4 Questions)

#### 38. How does Stripe payment flow work?

**Answer:**  
High-level flow:

1. Frontend → collect card details and create Stripe token  
2. Backend → use token with Charge API  
3. Stripe → returns success/failure response  

#### 39. Example Stripe Charge

```php
\Stripe\Charge::create([
    'amount' => 2000,
    'currency' => 'usd',
    'source' => $token,
]);
```

#### 40. What are Webhooks?

**Answer:**  
Webhooks are callbacks from Stripe to notify your application about events.

Examples:

- `payment_succeeded`  
- `payment_failed`  

#### 41. How do you secure Stripe Webhooks?

**Answer:**  
Verify the webhook signature using Stripe’s SDK.

```php
\Stripe\Webhook::constructEvent(
    $payload,
    $sig_header,
    $endpoint_secret
);
```

---

### 7. Git & Development Workflow (3 Questions)

#### 42. Difference between merge and rebase

**Answer:**

| Operation | Behavior          |
|-----------|-------------------|
| Merge     | Preserves history |
| Rebase    | Rewrites to cleaner linear history |

#### 43. What is Git Flow?

**Answer:**  
Typical branches:

- `main`  
- `develop`  
- `feature/*`  
- `hotfix/*`  

#### 44. How do you resolve merge conflicts?

**Answer (high-level steps):**

1. `git pull` (or fetch + merge)  
2. Fix conflicts in files  
3. `git add` resolved files  
4. `git commit` to finalize merge  

---

### 8. Debugging & Production Issues (3 Questions)

#### 45. How do you debug Laravel production errors?

**Answer:**  
Use tools like:

- Laravel logs  
- Telescope  
- Sentry  
- CloudWatch  

Main log file:

```text
storage/logs/laravel.log
```

#### 46. What causes 502/504 errors on EC2?

**Answer (common reasons):**

- PHP-FPM crash  
- Nginx timeout  
- Worker overload  
- Slow database queries  

#### 47. How do you optimize Laravel performance?

**Answer:**  
Use:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

And use Redis with queues and caching where appropriate.

---

### 9. System Design / Architecture (3 Questions)

#### 48. How would you design a scalable API system?

**Answer (high-level architecture):**

```text
Load Balancer
    ↓
EC2 Instances
    ↓
Laravel API
    ↓
Redis Cache
    ↓
MySQL
```

Add where needed:

- Queue workers  
- CDN  
- Caching layers  

#### 49. How would you implement RBAC?

**Answer:**  
Use role and permission tables:

- `users`  
- `roles`  
- `permissions`  
- `role_user`  
- `permission_role`  

Use Laravel policies or packages like `spatie/laravel-permission`.

#### 50. How would you design a notification system?

**Answer:**  
Core components:

- Event  
- Queue job  
- Notification service  
- Channels (Email/SMS/etc.)  

Example:

```php
Notification::send($user, new OrderShipped());
```

---

### Advanced Scenario Questions (Senior Interviewers Ask)

#### 1. Your API response time increased from 200ms to 2s. How do you debug?

**Steps:**

- Check slow queries  
- Enable query log  
- Use `EXPLAIN` on heavy queries  
- Check Redis cache usage  
- Check queue workers and background jobs  

#### 2. Stripe charged user but order not created. How do you fix?

**Answer:**  
Use database transactions plus an idempotency key, and rely on Stripe webhook confirmation to finalize the order state.

#### 3. You have 1M users and sending email takes hours. Solution?

**Answer:**  
Use:

- Queues  
- Redis  
- Multiple workers  
- `chunk()` for batching  

Example:

```bash
php artisan queue:work --tries=3
```

#### 4. How do you prevent duplicate payments?

**Answer:**  
Use:

- Idempotency keys  
- Unique order references  
- Payment status checks before re-charging  

#### 5. Laravel app suddenly using 100% CPU.

**Debug steps:**

- Look for infinite queue jobs  
- Identify N+1 queries  
- Investigate memory leaks  
- Check worker loops and heavy cron jobs  
- Use `top` / `htop` and Laravel logs to find hotspots  
*** End of File