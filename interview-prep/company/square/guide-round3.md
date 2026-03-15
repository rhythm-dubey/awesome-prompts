## ROUND 3 — API Design & Backend Architecture

---

### Q1. How do you design a RESTful API in Laravel? What conventions do you follow?

**Answer:**

**URL conventions:**
```
GET    /api/v1/orders              -> index (list)
POST   /api/v1/orders              -> store (create)
GET    /api/v1/orders/{id}         -> show (read)
PUT    /api/v1/orders/{id}         -> update (full)
PATCH  /api/v1/orders/{id}         -> update (partial)
DELETE /api/v1/orders/{id}         -> destroy (delete)
GET    /api/v1/orders/{id}/items   -> nested resource
```

**Key conventions I follow:**
1. **Use plural nouns** — `/orders` not `/order`.
2. **API Resources** for consistent response formatting.
3. **Form Requests** for validation.
4. **Proper HTTP status codes** — 200, 201, 204, 400, 401, 403, 404, 422, 429, 500.
5. **Consistent error format.**
6. **Pagination** for list endpoints.

```php
// Controller
class OrderController extends Controller
{
    public function index(OrderIndexRequest $request)
    {
        $orders = Order::query()
            ->where('user_id', auth()->id())
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request)
    {
        $order = Order::create($request->validated());
        return new OrderResource($order);
    }
}

// API Resource
class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'total' => $this->total,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

**Interview Tip:** Mention `whenLoaded()` in Resources (prevents N+1), Form Requests for validation separation, and consistent error response structure.

---

### Q2. How do you implement API versioning in Laravel?

**Answer:**

**Three common approaches:**

**1. URI Versioning (Most Common):**

```php
Route::prefix('v1')->group(function () {
    Route::apiResource('orders', V1\OrderController::class);
});

Route::prefix('v2')->group(function () {
    Route::apiResource('orders', V2\OrderController::class);
});
```

Directory structure:
```
app/Http/Controllers/Api/V1/OrderController.php
app/Http/Controllers/Api/V2/OrderController.php
app/Http/Resources/V1/OrderResource.php
app/Http/Resources/V2/OrderResource.php
```

**2. Header-based Versioning:**

```php
class ApiVersion
{
    public function handle($request, Closure $next)
    {
        $version = $request->header('Accept-Version', 'v1');
        config(['app.api_version' => $version]);
        return $next($request);
    }
}
```

**My recommendation:** URI versioning for simplicity and discoverability. Only introduce v2 when there are breaking changes.

**Interview Tip:** Say you prefer URI versioning and explain why. Mention that you version Resources/Transformers too, not just controllers.

---

### Q3. How do you handle API rate limiting in Laravel?

**Answer:**

```php
// In RouteServiceProvider or bootstrap
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

// Different limits per tier
RateLimiter::for('api', function (Request $request) {
    return match ($request->user()?->plan) {
        'premium' => Limit::perMinute(120)->by($request->user()->id),
        'basic'   => Limit::perMinute(30)->by($request->user()->id),
        default   => Limit::perMinute(10)->by($request->ip()),
    };
});

// Route usage
Route::middleware('throttle:api')->group(function () {
    Route::apiResource('orders', OrderController::class);
});
```

**Response headers:**
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 58
Retry-After: 30  (when rate limited)
```

When the limit is exceeded, Laravel returns `429 Too Many Requests`.

**Interview Tip:** Mention tiered rate limits based on user plans and that Redis is essential for multi-server rate limiting.

---

### Q4. How do you handle API authentication for SPAs, mobile apps, and third-party consumers?

**Answer:**

**SPA (Same domain/subdomain) — Sanctum Cookie Auth:**

```php
// Frontend (Axios)
await axios.get('/sanctum/csrf-cookie');
await axios.post('/login', credentials);
```

**Mobile Apps — Sanctum Token Auth:**

```php
public function login(LoginRequest $request)
{
    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    return response()->json([
        'token' => $user->createToken('mobile', ['orders:read', 'orders:write'])->plainTextToken,
        'user' => new UserResource($user),
    ]);
}
```

**Third-party API consumers — Passport OAuth2:**
- Authorization Code flow for web apps.
- Client Credentials for server-to-server.
- Scopes for granular permissions.

**Interview Tip:** Explain the decision matrix — cookie for SPA, tokens for mobile, OAuth2 for third-party. Mention token abilities/scopes and revocation.

---

### Q5. How do you structure a large Laravel application?

**Answer:**

```
app/
|-- Http/
|   |-- Controllers/       # Thin controllers
|   |-- Requests/          # Form Request validation
|   |-- Resources/         # API Resources (transformers)
|   |-- Middleware/
|-- Models/                # Eloquent models
|-- Services/              # Business logic layer
|   |-- OrderService.php
|   |-- PaymentService.php
|-- Repositories/          # Data access (optional)
|-- Actions/               # Single-purpose classes
|-- DTOs/                  # Data Transfer Objects
|-- Enums/                 # PHP 8.1 Enums
|-- Events/
|-- Listeners/
|-- Jobs/
|-- Notifications/
|-- Policies/
|-- Exceptions/
```

**Architecture rules I follow:**
1. **Thin Controllers:** Controllers only handle HTTP concerns.
2. **Fat Services:** Business logic lives in service classes.
3. **Single Action Classes:** For complex operations.
4. **DTOs:** Instead of passing arrays between layers.
5. **Enums:** Replace string constants for status fields.

```php
// Thin Controller
public function store(StoreOrderRequest $request, CreateOrderAction $action)
{
    $dto = OrderDTO::fromRequest($request);
    $order = $action->execute($dto);
    return new OrderResource($order);
}
```

**Interview Tip:** Show progression — "For small projects I keep it simple, for larger ones I introduce services and actions, for enterprise I consider DDD."

---

### Q6. Explain how you handle error and exception handling in a Laravel API.

**Answer:**

```php
class Handler extends ExceptionHandler
{
    public function register()
    {
        $this->renderable(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'RESOURCE_NOT_FOUND',
                        'message' => 'The requested resource was not found.',
                    ]
                ], 404);
            }
        });

        $this->renderable(function (ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The given data was invalid.',
                        'details' => $e->errors(),
                    ]
                ], 422);
            }
        });

        $this->reportable(function (\Throwable $e) {
            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        });
    }
}
```

**Custom business exceptions:**

```php
class InsufficientBalanceException extends Exception
{
    public function render($request)
    {
        return response()->json([
            'error' => [
                'code' => 'INSUFFICIENT_BALANCE',
                'message' => 'Your account balance is insufficient.',
            ]
        ], 402);
    }
}
```

**Consistent error format:**
```json
{
    "error": {
        "code": "MACHINE_READABLE_CODE",
        "message": "Human-readable message",
        "details": {}
    }
}
```

**Interview Tip:** The consistent error format is key. Mention external error tracking (Sentry) — it shows production awareness.

---

### Q7. What is the difference between `sync`, `database`, and `redis` queue drivers?

**Answer:**

| Driver | How it works | Use case |
|--------|-------------|----------|
| `sync` | Executes immediately in current process | Local development, testing |
| `database` | Stores jobs in a `jobs` table | Small-scale apps without Redis |
| `redis` | Stores jobs in Redis lists | Production — fast, reliable |
| `sqs` | AWS Simple Queue Service | Serverless, auto-scaling |

**`redis` is the production standard.** Use with **Laravel Horizon** for monitoring.

```bash
# Process queues in priority order
php artisan queue:work redis --queue=high,default,low
```

**Interview Tip:** Say "I use `sync` locally, `redis` in production with Horizon for monitoring." Then explain job priorities and Supervisor.

---

### Q8. How do you implement webhook handling in Laravel?

**Answer:**

```php
// routes/api.php
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->withoutMiddleware(['auth:sanctum']);

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1. Verify webhook signature
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sigHeader, config('services.stripe.webhook_secret')
            );
        } catch (\Exception $e) {
            return response('Invalid signature', 400);
        }

        // 2. Check idempotency
        if (WebhookLog::where('stripe_event_id', $event->id)->exists()) {
            return response('Already processed', 200);
        }

        // 3. Log the event
        WebhookLog::create([
            'stripe_event_id' => $event->id,
            'type' => $event->type,
            'payload' => $payload,
        ]);

        // 4. Dispatch to queue
        ProcessStripeWebhook::dispatch($event->type, $event->data->object);

        // 5. Return 200 immediately
        return response('OK', 200);
    }
}
```

**Key principles:**
1. **Verify signatures** — Never trust unverified payloads.
2. **Idempotency** — Webhooks can be sent multiple times.
3. **Return 200 fast** — Process heavy logic in a queued job.
4. **Log everything** — Store raw payloads for debugging.

**Interview Tip:** The three magic words are **signature verification**, **idempotency**, and **queue processing**.

---

### Q9. How do you write tests for a Laravel API?

**Answer:**

```php
class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_their_orders()
    {
        $user = User::factory()->create();
        Order::factory()->count(3)->for($user)->create();
        Order::factory()->count(2)->create(); // other user's orders

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/orders');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'status', 'total', 'created_at']],
                'meta' => ['current_page', 'total'],
            ]);
    }

    public function test_store_order_creates_order_and_dispatches_job()
    {
        Queue::fake();
        $user = User::factory()->create();
        $payload = ['items' => [['product_id' => 1, 'quantity' => 2]]];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/orders', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('orders', ['user_id' => $user->id]);
        Queue::assertPushed(ProcessPayment::class);
    }
}
```

**Test categories:**
- **Feature tests** — Full HTTP request through controller, middleware, validation.
- **Unit tests** — Isolated service/action class testing.
- **Fakes** — `Queue::fake()`, `Mail::fake()`, `Notification::fake()`, `Event::fake()`, `Storage::fake()`.

**Interview Tip:** Show that you test validation, authorization, happy paths, and edge cases. Mention fakes for side effects.

---

### Q10. How do you implement real-time features in Laravel?

**Answer:**

```php
// 1. Event
class OrderStatusUpdated implements ShouldBroadcast
{
    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("orders.{$this->order->user_id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'status' => $this->order->status,
        ];
    }
}

// 2. Channel authorization (routes/channels.php)
Broadcast::channel('orders.{userId}', function ($user, $userId) {
    return $user->id === (int) $userId;
});

// 3. Frontend (Laravel Echo)
Echo.private(`orders.${userId}`)
    .listen('OrderStatusUpdated', (e) => {
        updateOrderStatus(e.order_id, e.status);
    });
```

**WebSocket drivers:**
- **Pusher** — Managed service, easiest setup.
- **Laravel Reverb** (Laravel 11+) — First-party WebSocket server.
- **soketi** — Open-source, self-hosted, Pusher-compatible.

**Interview Tip:** Mention `ShouldBroadcastNow` vs `ShouldBroadcast` (queued), private vs presence channels, and Laravel Reverb as the modern choice.

---