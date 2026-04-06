# Laravel Interview Questions & Answers

---

## Q1: E-Commerce Application Scalability with Laravel

### Question

The requirement is to develop an e-commerce application using Laravel as the backend. How would you design and develop it so that it can handle 1 lakh+ users (high traffic/load)?

### Answer

To build a scalable e-commerce application capable of handling **100,000+ users**, the system must be designed with **scalability, performance, caching, and distributed architecture** in mind. Instead of relying on a single server, the application should use a **multi-layer architecture with load balancing, caching, database optimization, and background processing**.

#### 1. Scalable Infrastructure

- Deploy the application on **cloud infrastructure** such as AWS, GCP, or Azure
- Use **multiple application servers** instead of a single server
- Place a **load balancer** (e.g., Nginx, AWS ALB) in front to distribute incoming traffic
- Use **auto-scaling groups** to automatically add servers during high traffic

#### 2. Laravel Application Optimization

- Enable **OPcache** for faster PHP execution
- Use **Laravel Octane (Swoole or RoadRunner)** to improve request handling performance
- Use **config caching, route caching, and view caching**:
  ```bash
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  ```
- Avoid heavy logic inside controllers and use service classes

#### 3. Database Optimization

- Use **MySQL/PostgreSQL** with proper indexing
- Normalize the database structure for products, users, orders, and inventory
- Use **read replicas** to distribute read-heavy queries
- Implement query optimization and avoid **N+1 queries** using eager loading

**Example:**
```php
Product::with('category')->paginate(20);
```

#### 4. Caching Strategy

Caching is critical for high-traffic applications.

**Use:**
- **Redis** or **Memcached** for caching
- **Cache frequently accessed data** like:
  - Product lists
  - Categories
  - Homepage content
  - Pricing data

**Example:**
```php
$products = Cache::remember('products_page_1', 60, function () {
    return Product::latest()->take(20)->get();
});
```

#### 5. Queue System for Heavy Tasks

- Heavy operations should not run during the request cycle
- Use **Laravel Queues** with **Redis** or **RabbitMQ** for:
  - Order processing
  - Email sending
  - Inventory updates
  - Notifications

**Example:**
```bash
php artisan queue:work
```

#### 6. CDN for Static Assets

Serve static assets through a **Content Delivery Network (CDN)** such as:
- **Cloudflare**
- **AWS CloudFront**

This reduces load on the main servers and speeds up content delivery globally.

#### 7. Database & Session Management

- Store sessions in **Redis** instead of files
- Use **database connection pooling**
- Separate services if needed (microservices approach for payments, search, etc.)

#### 8. Search Optimization

For large product catalogs:
- Use **Elasticsearch** or **Meilisearch** for fast product search
- Integrate with **Laravel Scout**

#### 9. Monitoring & Logging

Use monitoring tools to track performance:
- **New Relic**
- **Datadog**
- **Prometheus + Grafana**

**Monitor:**
- Server CPU and memory
- Database performance
- Slow queries
- API response time

#### 10. Security and Rate Limiting

To prevent abuse during high traffic:
- Use **Laravel rate limiting**
- Implement **API throttling**
- Use **Web Application Firewall (WAF)**

**Example:**
```php
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
});
```

#### Architecture Overview

A scalable architecture for this system would typically include:
- **Load Balancer**
- **Multiple Laravel Application Servers**
- **Redis** (Cache + Queue + Session)
- **Primary Database + Read Replicas**
- **CDN** for static assets
- **Background Workers**

### Conclusion

By combining load balancing, caching, queue processing, optimized database queries, CDN usage, and scalable cloud infrastructure, a Laravel-based e-commerce application can efficiently handle 100,000+ concurrent users while maintaining high performance and reliability.

---

## Q2: Creating a Custom Facade for the Number Class

### Question

How can I create a custom Facade for the Number class named **Num** in Laravel? Explain the step-by-step process.

### Answer

In Laravel, a **Facade** provides a static interface to classes that are available in the **service container**. To create a custom facade named **Num** for a **Number class**, we need to:

1. Create the service class  
2. Bind it to the service container  
3. Create the Facade class  
4. Register it in the service provider  
5. Use the facade in the application  

#### Step 1: Create the Number Service Class

First, create a class that contains the logic related to numbers.

**File:** `app/Services/Number.php`

```php
<?php

namespace App\Services;

class Number
{
    public function format($number)
    {
        return number_format($number);
    }

    public function isEven($number)
    {
        return $number % 2 === 0;
    }
}
```

This class will contain all number-related utility methods.

#### Step 2: Bind the Class in the Service Container

Next, register this class in a Service Provider so Laravel can resolve it from the container.

**File:** `app/Providers/AppServiceProvider.php`

```php
use App\Services\Number;

public function register()
{
    $this->app->singleton('num', function () {
        return new Number();
    });
}
```

Here, `'num'` is the service container binding key and Laravel will resolve the Number class whenever `num` is requested.

#### Step 3: Create the Custom Facade

Now create the facade class.

**File:** `app/Facades/Num.php`

```php
<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class Num extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'num';
    }
}
```

`getFacadeAccessor()` returns the container binding name (`num`).

#### Step 4: Register the Facade Alias

Open `config/app.php` and add the facade inside the aliases array:

```php
'aliases' => [
    'Num' => App\Facades\Num::class,
],
```

This allows you to call the facade like a static class.

#### Step 5: Use the Custom Facade

Now you can use the Num facade anywhere in your application.

**Example in a controller:**

```php
use Num;

class TestController extends Controller
{
    public function index()
    {
        $formatted = Num::format(1000000);
        $even = Num::isEven(10);

        return [$formatted, $even];
    }
}
```

**Output:**
```
1,000,000
true
```

#### How It Works Internally

1. You call `Num::format(1000)`
2. Laravel resolves the facade accessor `'num'`
3. It fetches the Number instance from the service container
4. The method `format()` is executed on that instance

### Summary

**Steps to create a custom facade:**

1. Create a service class (`Number`)
2. Bind it in the service container
3. Create a Facade class extending `Facade`
4. Return the binding key in `getFacadeAccessor()`
5. Register the alias in `config/app.php`
6. Use the facade like `Num::method()`

This allows clean, static-style access to reusable services in Laravel.

---

## Q3: Creating Custom Blade Directives

### Question

Laravel mein jaise Blade directives hote hain (e.g., `@if`, `@switch`). Kya hum isi tarah **apna custom Blade keyword/directive** bana sakte hain? Agar haan, toh uska **step-by-step process kya hoga?**

### Answer

Haan, Laravel mein hum **custom Blade directives** bana sakte hain. Laravel Blade engine allow karta hai ki developer apne **custom template keywords** define kare. Yeh kaam `Blade::directive()` method ki help se kiya jata hai.

#### Step 1: Service Provider Open karein

Custom Blade directive usually **Service Provider** mein register kiya jata hai.

**File open karein:** `app/Providers/AppServiceProvider.php`

#### Step 2: Blade Facade Import karein

File ke top par Blade facade import karein:

```php
use Illuminate\Support\Facades\Blade;
```

#### Step 3: Custom Blade Directive Create karein

`boot()` method ke andar directive define karein.

**Example:** agar hum `@currency` naam ka directive banana chahte hain:

```php
public function boot()
{
    Blade::directive('currency', function ($amount) {
        return "<?php echo number_format($amount, 2); ?>";
    });
}
```

**Yahaan:**
- `'currency'` → directive ka naam
- `$amount` → directive ka parameter
- Return value → PHP code jo Blade compile hone par execute hoga

#### Step 4: Blade File mein Use karein

Ab Blade template mein directive use kar sakte hain.

**Example:**

```html
<p>Price: @currency(1500)</p>
```

**Output:**
```
Price: 1,500.00
```

#### Step 5: Cache Clear karein (Important)

Kabhi kabhi directive turant work nahi karta, toh cache clear karein:

```bash
php artisan view:clear
php artisan cache:clear
```

#### Example 2: Custom Condition Directive

Agar hum `@admin` directive banana chahte hain:

```php
Blade::directive('admin', function () {
    return "<?php if(auth()->user()->role == 'admin'): ?>";
});

Blade::directive('endadmin', function () {
    return "<?php endif; ?>";
});
```

**Blade usage:**

```html
@admin
    <p>Welcome Admin</p>
@endadmin
```

#### Alternative (Better Approach): Blade::if()

Laravel ek helper bhi deta hai custom conditional directives ke liye:

```php
Blade::if('admin', function () {
    return auth()->check() && auth()->user()->role === 'admin';
});
```

**Blade usage:**

```html
@admin
   <p>Admin Panel</p>
@endadmin
```

### Summary

**Laravel mein custom Blade directive banane ke steps:**

1. AppServiceProvider open karein
2. Blade facade import karein
3. `boot()` method mein `Blade::directive()` use karein
4. Directive ka PHP logic define karein
5. Blade template mein use karein
6. Agar zarurat ho toh cache clear karein

✅ Is approach se hum custom template keywords bana sakte hain jo Blade templates ko clean aur reusable banate hain.

---

## Q4: Sharing Common Data Across All Blade Views

### Question

Agar kuch common data ho (jaise **categories ki list**) aur main chahta hoon ki woh **har Blade file me available ho**, toh Laravel me isko implement karne ke liye kya use karna chahiye?

### Answer

Laravel me agar hume **koi data sabhi Blade views me share karna ho**, toh uske liye best approach hai **View Composers** ya **View::share()** use karna.

Most recommended approach: **View Composer**, kyunki yeh clean, scalable aur maintainable hota hai.

---

### Method 1: Using View::share() (Simple Way)

Agar data simple hai aur har view me chahiye, toh `View::share()` use kar sakte hain.

#### Step 1: AppServiceProvider open karein

`app/Providers/AppServiceProvider.php`

#### Step 2: boot() method me data share karein

```php
use Illuminate\Support\Facades\View;
use App\Models\Category;

public function boot()
{
    $categories = Category::all();

    View::share('categories', $categories);
}
```

#### Step 3: Blade me use karein

Ab yeh data har Blade file me available hoga:

```html
<ul>
@foreach($categories as $category)
    <li>{{ $category->name }}</li>
@endforeach
</ul>
```

---

### Method 2: Using View Composer (Best Practice)

Agar data dynamic hai ya complex logic hai, toh View Composer use karna better hai.

#### Step 1: View Composer Class Create karein

`app/View/Composers/CategoryComposer.php`

```php
<?php

namespace App\View\Composers;

use Illuminate\View\View;
use App\Models\Category;

class CategoryComposer
{
    public function compose(View $view)
    {
        $categories = Category::all();

        $view->with('categories', $categories);
    }
}
```

#### Step 2: Service Provider me Register karein

Open `app/Providers/AppServiceProvider.php`

```php
use Illuminate\Support\Facades\View;
use App\View\Composers\CategoryComposer;

public function boot()
{
    View::composer('*', CategoryComposer::class);
}
```

`*` ka matlab hai yeh composer sabhi views par run hoga.

**Agar specific views ke liye chahiye ho:**

```php
View::composer(['home', 'products.*'], CategoryComposer::class);
```

---

### Method 3: Using Middleware (Another Approach)

Kabhi kabhi log middleware me bhi data share karte hain:

```php
View::share('categories', Category::all());
```

Lekin yeh recommended nahi hota jab view specific logic ho.

---

### Best Practice

**Large applications me recommended approach:**

✅ **View Composer**

**Kyuki:**
- Code organized rehta hai
- Business logic controllers se separate hota hai
- Reusable hota hai
- Performance optimize kar sakte hain

---

### Final Summary

Agar categories jaisa data har Blade view me chahiye toh Laravel me:

- **View::share()** → Simple global data sharing
- **View Composer** → Best scalable solution
- **Middleware** → Rare cases me use hota hai

Most professional Laravel projects me View Composer preferred approach hota hai.

---

*Last Updated: 2026-03-12*
