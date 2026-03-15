# Laravel Interview Q&A (Screening Preparation)

## 1. What is the difference between `hasOne`, `hasMany`, and `belongsTo` in Laravel?

These are **Eloquent relationships** used to define relations between models.

### hasOne

Used when one model has exactly one related record.

Example: A user has one profile.

```php
class User extends Model {
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }
}
```

### hasMany

Used when one model has multiple related records.

Example: A user can have many orders.

```php
class User extends Model {
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
```

### belongsTo

Used when the current model belongs to another model.

Example: Each order belongs to a user.

```php
class Order extends Model {
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

---

# 2. What is the difference between `where()` and `whereHas()`?

### where()

Used to filter records directly from a table.

```php
$users = User::where('status', 'active')->get();
```

### whereHas()

Used when filtering records based on a relationship condition.

Example: Get users who have at least one completed order.

```php
$users = User::whereHas('orders', function ($query) {
    $query->where('status', 'completed');
})->get();
```

This checks conditions on related models.

---

# 3. What is the difference between `dispatch()` and `dispatchSync()` in Laravel Jobs?

### dispatch()

Runs the job **asynchronously** through queues.

```php
ProcessOrder::dispatch($order);
```

The job will be processed by a **queue worker in the background**.

### dispatchSync()

Runs the job **immediately in the current request**.

```php
ProcessOrder::dispatchSync($order);
```

No queue worker is required.

Use `dispatch()` for time-consuming tasks like:

* sending emails
* generating reports
* processing uploads

---

# 4. What is the difference between `Cache::remember()` and `Cache::put()`?

### Cache::put()

Stores data in cache for a specific time.

```php
Cache::put('users', $users, 3600);
```

This stores the value for **1 hour**.

### Cache::remember()

Stores data **only if it doesn't already exist in cache**.

```php
$users = Cache::remember('users', 3600, function () {
    return User::all();
});
```

If cache exists → return cached data
If not → run query and store result.

---

# 5. What is the difference between Soft Delete and Hard Delete?

### Hard Delete

The record is **permanently removed** from the database.

```php
User::find(1)->delete();
```

### Soft Delete

The record is **not removed**, but marked as deleted using the `deleted_at` column.

Laravel automatically hides soft deleted records.

Enable soft delete:

```php
use SoftDeletes;

class User extends Model
{
    use SoftDeletes;
}
```

Restore deleted record:

```php
User::withTrashed()->find(1)->restore();
```

### Why use Soft Deletes?

Soft deletes allow us to:

* recover deleted records
* maintain history
* prevent accidental data loss

---

# Filament & Livewire Interview Questions

## 6. What is Filament in Laravel?

Filament is a **modern admin panel framework for Laravel** used to quickly build dashboards, CRUD panels, and management systems.

It is built on top of **Livewire + TailwindCSS**.

Filament provides ready-made components like:

* Admin Panels
* Forms
* Tables
* Widgets
* Relation Managers
* Role-based access control

Example command:

```bash
php artisan make:filament-resource Post
```

This generates:

* List page
* Create page
* Edit page
* Table configuration
* Form schema

---

# 7. What is Livewire and why is it used with Filament?

Livewire is a **full-stack Laravel framework** that allows developers to build dynamic interfaces using PHP instead of JavaScript.

It works by:

* Rendering Blade views
* Listening to user actions
* Sending AJAX requests
* Updating the DOM automatically

Example Livewire component:

```php
class Counter extends Component
{
    public $count = 0;

    public function increment()
    {
        $this->count++;
    }
}
```

---

# 8. What are Filament Resources?

Resources represent **CRUD management for a model**.

Each resource contains:

* List page
* Create page
* Edit page
* Table configuration
* Form configuration

Example:

```php
class PostResource extends Resource
{
    protected static ?string $model = Post::class;
}
```

Example form field:

```php
TextInput::make('title')->required();
```

Example table column:

```php
TextColumn::make('title');
```

---

# 9. What are Relation Managers in Filament?

Relation Managers allow managing **related models inside a parent resource**.

Example relationship:

```
User → hasMany → Orders
```

Example:

```php
class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';
}
```

This allows:

* Viewing related records
* Creating related records
* Editing related records

without leaving the parent resource page.

---

# 10. How does Livewire handle form submissions without page reload?

Livewire uses **AJAX requests behind the scenes**.

Process:

1. User interacts with component
2. Livewire sends request to server
3. Server updates component state
4. Only the changed HTML is re-rendered

Example:

Blade view:

```blade
<button wire:click="save">Save</button>
```

Livewire component:

```php
public function save()
{
    Post::create($this->data);
}
```

---

# Common Laravel Screening Questions

## 11. Tell me about a challenging Laravel project you worked on

I worked on a **Property Management System** built with Laravel where we managed multiple user roles such as landlords, tenants, and residents. The system had complex relationships between properties, units, and users. I implemented role-based access control, optimized database queries, and integrated third-party APIs like Stripe and TransUnion.

---

## 12. What is the difference between eager loading and lazy loading?

Lazy loading loads relationships only when they are accessed, which can cause the **N+1 query problem**.

Eager loading loads relationships in advance using `with()` to reduce database queries and improve performance.

Example:

```php
Order::with('customer')->get();
```

---

## 13. How do you optimize slow queries in Laravel?

To optimize slow queries I:

* check query logs or Debugbar
* identify N+1 issues
* add proper database indexes
* use eager loading
* implement caching for frequent queries

---

## 14. How do you handle background tasks in Laravel?

Laravel uses **queues** to process background jobs asynchronously.

Example:

```php
ProcessOrder::dispatch($order);
```

Queues are useful for:

* sending emails
* exporting reports
* processing notifications

---

## 15. How do you secure a Laravel API?

Laravel APIs can be secured using:

* Sanctum or Passport authentication
* Request validation
* Rate limiting
* Authorization policies
* Protection against SQL Injection and XSS

---

# Important Interview Question

## Why should we hire you?

I have around **3.5 years of experience in Laravel backend development** and have worked on complex systems like CRM and property management platforms. I have experience with REST APIs, RBAC systems, payment integrations like Stripe, and database optimization. I focus on writing clean, scalable backend code and solving real production problems.
