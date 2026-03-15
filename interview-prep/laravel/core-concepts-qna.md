# Laravel Core Concepts – Interview Q&A

## 1. What is Dependency Injection in Laravel?

Dependency Injection is a design pattern where class dependencies are automatically injected by the Laravel **Service Container** instead of creating them manually.

Example:

```php
public function __construct(OrderService $orderService)
{
    $this->orderService = $orderService;
}
```

Benefits:

* Loosely coupled code
* Easier testing
* Better maintainability

---

# 2. What is the Laravel Service Container?

The **Service Container** is Laravel’s dependency injection container that manages class dependencies and automatically resolves them.

It allows developers to bind interfaces to implementations and inject them into controllers, services, and jobs.

Example:

```php
$this->app->bind(
    PaymentInterface::class,
    StripePaymentService::class
);
```

---

# 3. What is Middleware in Laravel?

Middleware acts as a **filter for HTTP requests** before they reach the controller.

Common uses:

* Authentication
* Authorization
* Logging
* Rate limiting

Example:

```php
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

---

# 4. What is the N+1 Query Problem?

The **N+1 query problem** occurs when Laravel runs one query for the main data and additional queries for each related record.

Example problem:

```php
$orders = Order::all();

foreach ($orders as $order) {
    echo $order->customer->name;
}
```

Solution using **Eager Loading**:

```php
Order::with('customer')->get();
```

This reduces database queries and improves performance.

---

# 5. What are Laravel Queues?

Queues allow Laravel to process **time-consuming tasks asynchronously in the background**.

Common use cases:

* Sending emails
* Processing payments
* Generating reports
* Sending notifications

Example:

```php
SendEmailJob::dispatch($user);
```

Queues improve **application performance and user experience**.

---

# 6. What is the difference between `hasMany` and `belongsTo`?

### hasMany

One model has multiple related records.

Example:

```php
User::hasMany(Order::class);
```

### belongsTo

The current model belongs to another model.

Example:

```php
Order::belongsTo(User::class);
```

---

# 7. What is CSRF Protection in Laravel?

CSRF (Cross-Site Request Forgery) is an attack where a malicious site tricks a user into sending unwanted requests.

Laravel prevents this using **CSRF tokens**, which are automatically verified by the `VerifyCsrfToken` middleware.

Example in forms:

```blade
@csrf
```

---

# 8. How do you handle API authentication in Laravel?

Laravel provides authentication tools like:

* Laravel Sanctum
* Laravel Passport

These use **tokens** to authenticate API requests and ensure secure access to protected endpoints.

---

# 9. What is Eloquent ORM?

Eloquent is Laravel’s **Object Relational Mapper (ORM)** that allows developers to interact with the database using PHP models instead of writing raw SQL queries.

Example:

```php
$users = User::where('status', 'active')->get();
```

Benefits:

* Clean syntax
* Easy relationship management
* Maintainable database queries

---

# 10. What is the difference between `get()` and `first()`?

### get()

Returns a **collection of records**.

```php
$users = User::where('status', 'active')->get();
```

### first()

Returns **only the first record**.

```php
$user = User::where('email', $email)->first();
```
