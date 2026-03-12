# Laravel Rate Limiting Guide

---

## Question

**Rate Limit kya hoti hai?** Laravel me ise kaha implement karte hain aur iska logic kya hota hai?

---

## Answer

**Rate Limiting** ek technique hai jisme hum **API ya route par requests ki maximum limit set kar dete hain** taaki koi user ya bot server par bahut zyada requests na bhej sake.

### Main Purpose

Iska main purpose hai:
- **Server ko overload hone se bachana**
- **DDoS attacks ko prevent karna**
- **API abuse ko rokna**
- **Fair usage maintain karna**

### Real-World Example

Agar limit **60 requests per minute** hai, toh ek user **1 minute me sirf 60 requests** hi bhej sakta hai.

---

## Rate Limiting Implementation in Laravel

Laravel me rate limiting mainly **3 jagah implement ki ja sakti hai**:

### 1. Routes me (Most Common)

Laravel me `throttle` middleware use hota hai.

#### Basic Syntax

```php
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
});
```

**Explanation:**

| Component | Meaning |
|-----------|---------|
| `throttle` | Rate limiting middleware |
| `60` | Maximum requests |
| `1` | Time window in minutes |

**Meaning:** User 1 minute me 60 requests kar sakta hai.

#### Example with Multiple Routes

```php
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
});
```

---

### 2. API Routes

Laravel by default api middleware group me throttle apply karta hai.

**File:** `routes/api.php`

#### Default API Rate Limit

```php
Route::middleware('throttle:api')->group(function () {
    Route::get('/users', function () {
        return User::all();
    });
    
    Route::post('/orders', function () {
        return Order::all();
    });
});
```

#### Custom API Rate Limit

```php
Route::middleware('throttle:100,1')->group(function () {
    Route::get('/api/users', [UserController::class, 'index']);
});
```

---

### 3. RouteServiceProvider me Custom Rate Limit (Advanced)

Laravel me advanced rate limit RouteServiceProvider me define kiya jata hai.

**File:** `app/Providers/RouteServiceProvider.php`

#### Implementation

```php
<?php

namespace App\Providers;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;

class RouteServiceProvider
{
    public function boot()
    {
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip());
        });
    }
}
```

**Explanation:**

| Component | Purpose |
|-----------|---------|
| `perMinute(60)` | 60 requests allowed per minute |
| `by($request->user()?->id ?: $request->ip())` | Limit basis (User ID or IP address) |

---

## Rate Limiting ka Logic

Rate limiting usually **token bucket** ya **fixed window algorithm** par kaam karta hai.

### Simple Logic Flow

1. **Server** har user/IP ke liye request counter maintain karta hai
2. **Time window** define hoti hai (jaise 1 minute)
3. Har **request par counter increase** hota hai
4. Agar **counter limit cross** kar deta hai → request block ho jati hai

### Step-by-Step Example

**Scenario:** Limit = 60 requests, Time window = 1 minute

```
Request 1   → ✅ Allowed   (Count: 1/60)
Request 2   → ✅ Allowed   (Count: 2/60)
Request 30  → ✅ Allowed   (Count: 30/60)
Request 60  → ✅ Allowed   (Count: 60/60)
Request 61  → ❌ Blocked   (Rate limit exceeded)
```

### Server Response

```
HTTP 429 Too Many Requests

Headers:
Retry-After: 45 (seconds remaining)
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1615123456
```

---

## Rate Limiting Backend Storage

Laravel internally use karta hai:

### Storage Options

1. **Cache System**
   - Usually **Redis** / **Memcached**
   - Super fast access

2. **Example Stored Data**
   ```
   IP: 192.168.1.10
   Requests: 45
   Time Window: 1 minute
   Expiry: 2026-03-12 16:50:00
   ```

3. **Auto Reset**
   - Agar cache expire ho jaye → counter automatically reset ho jata hai

### Redis Example

```php
// Laravel internally stores something like this:
Cache::put('rate_limit:192.168.1.10', 45, 60); // 60 seconds

// Retrieve count
$requestCount = Cache::get('rate_limit:192.168.1.10', 0);
```

---

## Real-World Examples

### Example 1: Login Protection

Brute-force attack se protect karne ke liye:

```php
Route::post('/login')->middleware('throttle:5,1');
```

**Meaning:**
- 1 minute me maximum **5 login attempts**
- 5 attempts ke baad request block hogi

#### Usage Scenario

```
Attempt 1 → ✅ Allowed
Attempt 2 → ✅ Allowed
Attempt 3 → ✅ Allowed
Attempt 4 → ✅ Allowed
Attempt 5 → ✅ Allowed
Attempt 6 → ❌ Blocked - "Too many login attempts"
```

---

### Example 2: API Endpoint Protection

Public API ko protect karna:

```php
Route::middleware('throttle:100,1')->group(function () {
    Route::get('/api/products', [ProductController::class, 'index']);
    Route::get('/api/categories', [CategoryController::class, 'index']);
});
```

**Meaning:** Public user 1 minute me 100 requests kar sakte hain.

---

### Example 3: Premium vs Free User Limits

```php
RateLimiter::for('api', function (Request $request) {
    $user = $request->user();
    
    if ($user && $user->isPremium()) {
        return Limit::perMinute(1000)->by($user->id);
    }
    
    return Limit::perMinute(100)->by($request->ip());
});
```

**Explanation:**
- **Premium users** → 1000 requests/minute
- **Free/Anonymous users** → 100 requests/minute

---

### Example 4: Multiple Rate Limits

```php
RateLimiter::for('strict', function (Request $request) {
    return [
        Limit::perMinute(60)->by($request->ip()),
        Limit::perHour(1000)->by($request->ip()),
        Limit::perDay(10000)->by($request->ip()),
    ];
});

Route::middleware('throttle:strict')->group(function () {
    Route::post('/api/critical-operation', ...);
});
```

**Meaning:**
- Maximum 60 requests per minute
- Maximum 1000 requests per hour
- Maximum 10000 requests per day

---

## Error Handling

### Handling Rate Limit Exception

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HandleRateLimitException
{
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (\Illuminate\Http\Exceptions\ThrottleRequestsException $e) {
            return response()->json([
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $e->getHeaders()['Retry-After'] ?? null
            ], 429);
        }
    }
}
```

---

## Rate Limiting Response Headers

Laravel automatically sends rate limit headers:

```
HTTP/1.1 200 OK

X-RateLimit-Limit: 60              (Total limit)
X-RateLimit-Remaining: 42          (Requests remaining)
X-RateLimit-Reset: 1615123456      (Unix timestamp when limit resets)
```

### Example Response When Limited

```json
{
  "message": "HTTP 429 Too Many Requests",
  "retry_after": 45
}
```

---

## Best Practices

### 1. Different Limits for Different Routes

```php
// Strict limit for sensitive operations
Route::middleware('throttle:5,1')->post('/password-change', ...);

// Moderate limit for normal operations
Route::middleware('throttle:60,1')->get('/products', ...);

// Lenient limit for public data
Route::middleware('throttle:1000,1')->get('/public-api', ...);
```

### 2. User-Based vs IP-Based Limiting

```php
// For authenticated users - use user ID
RateLimiter::for('authenticated', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()->id);
});

// For guests - use IP address
RateLimiter::for('guest', function (Request $request) {
    return Limit::perMinute(20)->by($request->ip());
});
```

### 3. Log Rate Limit Violations

```php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)
        ->by($request->user()?->id ?: $request->ip())
        ->response(function (Request $request, array $headers) {
            \Log::warning('Rate limit exceeded', [
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
                'path' => $request->path(),
            ]);
            
            return response()->json(['error' => 'Too many requests'], 429, $headers);
        });
});
```

---

## Summary

### Rate Limiting Ka Use

- ✅ Server overload prevent karne ke liye
- ✅ API abuse rokne ke liye
- ✅ Security improve karne ke liye
- ✅ DDoS attacks prevent karne ke liye
- ✅ Fair usage maintain karne ke liye

### Implementation Methods

| Method | Use Case | Complexity |
|--------|----------|-----------|
| Route Middleware | Simple cases | Low |
| API Middleware | API protection | Medium |
| RouteServiceProvider | Complex logic | High |

### Key Concepts

1. **Throttle Middleware** - Rate limit apply karne ke liye
2. **Time Window** - Requests count hone ka time period
3. **Request Limit** - Maximum allowed requests
4. **Rate Limiter** - Backend storage (Redis/Cache)
5. **HTTP 429** - Rate limit exceeded response

---

*Last Updated: 2026-03-12*
