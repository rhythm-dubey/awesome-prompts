# Laravel Developer Interview Preparation Guide
## For 3+ Years Experience | Product-Based Companies

---

## Table of Contents

1. [Round 1 — Basic PHP & Laravel Fundamentals](#round-1)
2. [Round 2 — Advanced Laravel Concepts](#round-2)
3. [Round 3 — API Design & Backend Architecture](#round-3)
4. [Round 4 — Database & Optimization](#round-4)
5. [Round 5 — Stripe Payment Integration](#round-5)
6. [Round 6 — AWS Basics](#round-6)
7. [Round 7 — System Design & Real-World Scenarios](#round-7)
8. [Round 8 — Debugging & Problem-Solving Scenarios](#round-8)

---

## ROUND 1 — Basic PHP & Laravel Fundamentals

---

### Q1. Explain the Laravel Request Lifecycle from the moment a user hits a URL to getting a response.

**Answer:**

1. The request enters through `public/index.php`, which loads the Composer autoloader and bootstraps the application from `bootstrap/app.php`.
2. The HTTP Kernel (`App\Http\Kernel`) receives the request. It defines an array of global middleware (bootstrappers) that run before routing — these handle environment detection, configuration loading, exception handling, and registering facades/service providers.
3. The request passes through the global middleware stack (e.g., `TrustProxies`, `CheckForMaintenanceMode`).
4. The Router matches the request to a route. Route-specific middleware runs next (e.g., `auth`, `throttle`).
5. The controller method (or closure) executes, interacting with models, services, and returning a response.
6. The response travels back through middleware (allowing post-processing like adding headers).
7. The Kernel sends the response to the client, and `terminate()` middleware runs for cleanup tasks (like session storage).

**Interview Tip:** Interviewers want you to mention bootstrappers, service providers, middleware pipeline, and the Kernel. Saying "index.php to controller" is too shallow for 3+ years.

---

### Q2. What is the Service Container in Laravel, and why is it important?

**Answer:**

The Service Container (IoC Container) is Laravel's core dependency injection tool. It manages class dependencies and performs dependency injection. When you type-hint an interface or class in a constructor or method, the container automatically resolves and injects the appropriate instance.

Key capabilities:
- **Binding:** `$this->app->bind(Interface::class, Implementation::class);` — creates a new instance each time.
- **Singleton:** `$this->app->singleton(Interface::class, Implementation::class);` — resolves once, reuses the same instance.
- **Contextual Binding:** Different implementations for different consumers using `when()->needs()->give()`.

```php
// In a Service Provider
$this->app->bind(PaymentGatewayInterface::class, StripePaymentGateway::class);

// In a Controller — automatically injected
public function __construct(PaymentGatewayInterface $gateway)
{
    $this->gateway = $gateway;
}
```

Without the container, you'd manually instantiate dependencies everywhere, making code tightly coupled and hard to test.

**Interview Tip:** Show that you understand the difference between `bind`, `singleton`, and contextual binding. Mention testability — the container lets you swap real implementations with mocks.

---

### Q3. What are Service Providers in Laravel? How do you create a custom one?

**Answer:**

Service Providers are the central place to configure and bootstrap your application. Every core Laravel service (routing, validation, database, queue) is bootstrapped via a service provider. They have two methods:

- `register()` — Bind things into the service container. Do NOT use any other service here because they may not be loaded yet.
- `boot()` — Called after all providers are registered. You can use any service, register event listeners, composers, routes, etc.

Creating a custom one:

```bash
php artisan make:provider PaymentServiceProvider
```

```php
class PaymentServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(PaymentGatewayInterface::class, function ($app) {
            return new StripePaymentGateway(config('services.stripe.secret'));
        });
    }

    public function boot()
    {
        // Publish config, register routes, event listeners, etc.
    }
}
```

Register it in `config/app.php` under the `providers` array (or in Laravel 11+, in `bootstrap/providers.php`).

**Interview Tip:** Clearly distinguish `register()` vs `boot()`. A common mistake candidates make is putting logic in `register()` that depends on other services.

---

### Q4. Explain the difference between `==` and `===` in PHP, and where this matters in Laravel.

**Answer:**

- `==` (loose comparison) compares values after type juggling. `0 == "foo"` is `true` in PHP < 8.0 (and `false` in PHP 8.0+).
- `===` (strict comparison) compares both value AND type. `0 === "foo"` is always `false`.

Where this matters in Laravel:
- Comparing Eloquent model attributes: `$user->is_admin == true` could pass for `1` (integer from DB), but strict comparison is safer.
- Checking `strpos()` results: `strpos('hello', 'h')` returns `0`, which is falsy with `==`.
- Middleware and gate checks where boolean logic must be exact.
- Config values from `.env` come as strings — `config('app.debug')` can be the string `"true"` if not cast.

**Interview Tip:** Mention PHP 8's changes to string-to-number comparisons. It shows you stay current with PHP evolution.

---

### Q5. What are Facades in Laravel? How do they work internally?

**Answer:**

Facades provide a static-like syntax to access services from the container. When you call `Cache::get('key')`, you're not calling a static method — the `Cache` facade resolves the underlying `cache` binding from the container and forwards the method call.

Internally, every facade extends `Illuminate\Support\Facades\Facade` and implements `getFacadeAccessor()`, which returns the container binding key.

```php
class Cache extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'cache'; // resolves $app['cache']
    }
}
```

The magic happens in `__callStatic()` — it resolves the instance from the container and delegates the method call.

Real-time facades: Any class can be used as a facade by prefixing its namespace with `Facades\`:

```php
use Facades\App\Services\PaymentService;
PaymentService::charge($amount);
```

**Interview Tip:** Emphasize that facades are NOT static classes — they're syntactic sugar over the container. Mention that they're testable because you can use `Cache::shouldReceive()` (Mockery integration).

---

### Q6. What is Middleware in Laravel? Explain types and a practical use case.

**Answer:**

Middleware filters HTTP requests entering your application. They sit between the request and the response in a pipeline pattern.

**Types:**
1. **Global Middleware** — Runs on every request (`TrimStrings`, `ValidatePostSize`).
2. **Route Middleware** — Assigned to specific routes (`auth`, `throttle`, `verified`).
3. **Middleware Groups** — Bundles like `web` (session, CSRF) and `api` (throttle, stateless).

**Before vs After Middleware:**

```php
// Before middleware — runs before the controller
public function handle($request, Closure $next)
{
    if (!$request->user()->hasSubscription()) {
        return redirect('/subscribe');
    }
    return $next($request);
}

// After middleware — runs after the controller
public function handle($request, Closure $next)
{
    $response = $next($request);
    $response->header('X-Custom-Header', 'value');
    return $response;
}
```

**Practical use case:** A `CheckApiQuota` middleware that counts requests per API key from Redis and returns `429 Too Many Requests` when the limit is exceeded.

**Interview Tip:** Give a real custom middleware example, not just "auth". Mention terminable middleware (`terminate()` method) that runs after the response is sent.

---

### Q7. Explain PHP traits and how Laravel uses them extensively.

**Answer:**

Traits are a mechanism for code reuse in single-inheritance languages. They let you inject methods into multiple classes without inheritance.

```php
trait Auditable
{
    public static function bootAuditable()
    {
        static::updating(function ($model) {
            $model->updated_by = auth()->id();
        });
    }
}
```

Laravel uses traits throughout:
- `SoftDeletes` — Adds soft deletion to models.
- `Notifiable` — Adds notification capabilities to the User model.
- `HasFactory` — Connects models to their factory classes.
- `HasApiTokens` (Sanctum) — Adds token-based authentication.
- `Searchable` (Scout) — Adds full-text search.
- `InteractsWithQueue` — Used in Jobs for queue interaction.
- `AuthorizesRequests`, `ValidatesRequests` — In base Controller.

The `boot{TraitName}` convention lets traits hook into the model boot process automatically.

**Interview Tip:** Mention the `boot{TraitName}` convention — it shows deep understanding. Explain when to use traits vs inheritance vs composition.

---

### Q8. What are anonymous classes and arrow functions in PHP? Where do you use them in Laravel?

**Answer:**

**Arrow functions** (PHP 7.4+): Short closures that automatically capture outer variables.

```php
$prices = collect([100, 200, 300]);
$discounted = $prices->map(fn($price) => $price * 0.9);
```

Unlike regular closures, arrow functions don't need `use` keyword — they capture by value automatically.

**Anonymous classes** (PHP 7.0+): Classes without a name, useful for one-off implementations.

```php
// In tests
$mock = new class implements PaymentGatewayInterface {
    public function charge($amount) { return true; }
};

// In Laravel migrations (Laravel 9+)
return new class extends Migration {
    public function up() { /* ... */ }
};
```

Laravel 9+ uses anonymous migration classes by default to avoid class name conflicts.

**Interview Tip:** The interviewer expects you to know modern PHP features (7.4-8.2). Mentioning named arguments, match expressions, enums, and fibers shows strong PHP fundamentals.

---

### Q9. What is CSRF protection in Laravel and how does it work?

**Answer:**

CSRF (Cross-Site Request Forgery) protection prevents malicious websites from submitting forms on behalf of authenticated users.

Laravel generates a unique CSRF token per session. The `VerifyCsrfToken` middleware checks every POST, PUT, PATCH, DELETE request for a valid token.

Implementation:

```blade
<form method="POST" action="/profile">
    @csrf
    <!-- form fields -->
</form>
```

For AJAX requests, Laravel reads the token from the `X-CSRF-TOKEN` header or the `XSRF-TOKEN` cookie (which Axios reads automatically).

```javascript
// Axios automatically uses XSRF-TOKEN cookie
// For other libraries:
headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
```

Routes in `routes/api.php` skip CSRF because they use the `api` middleware group (stateless, token-based).

You can exclude URIs in the middleware: `protected $except = ['/webhook/stripe'];`

**Interview Tip:** Mention why webhooks are excluded from CSRF, and how the XSRF-TOKEN cookie works with JavaScript frameworks. This shows practical experience.

---

### Q10. Explain the difference between `$request->input()`, `$request->get()`, `$request->query()`, and `$request->all()`.

**Answer:**

| Method | Source | Notes |
|--------|--------|-------|
| `$request->input('key')` | Body + Query string | Most common, supports dot notation for nested data |
| `$request->query('key')` | Only query string | Only `?key=value` from URL |
| `$request->get('key')` | Inherited from Symfony | Works but `input()` is preferred in Laravel |
| `$request->all()` | All input data | Returns everything (body + query), use cautiously with mass assignment |
| `$request->only(['name', 'email'])` | Whitelist specific keys | Safer for mass assignment |
| `$request->except(['_token'])` | Blacklist specific keys | Excludes specified keys |

```php
// POST /users?source=web  with body: { "name": "John", "email": "john@test.com" }

$request->input('name');    // "John"
$request->query('source');  // "web"
$request->input('source');  // "web" (input checks both)
$request->all();            // ["name" => "John", "email" => "john@test.com", "source" => "web"]
```

**Interview Tip:** Mention `$request->validated()` from Form Requests — it's the safest because it only returns fields that passed validation. This is what you should use in practice.

---