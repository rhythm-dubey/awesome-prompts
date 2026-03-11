## ROUND 7 — System Design & Real-World Scenarios

---

### Q1. Design a notification system that supports email, SMS, push, and in-app notifications.

**Answer:**

```
User Action -> Event -> NotificationService -> Queue -> Channel handlers
                                                     -> Email (SES/Mailgun)
                                                     -> SMS (Twilio/SNS)
                                                     -> Push (FCM/APNs)
                                                     -> Database (in-app)
```

```php
class OrderShippedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Order $order) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        $prefs = $notifiable->notification_preferences;

        if ($prefs['email'] ?? true) $channels[] = 'mail';
        if ($prefs['sms'] ?? false) $channels[] = 'vonage';
        if ($prefs['push'] ?? true) $channels[] = FcmChannel::class;

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Order #{$this->order->number} Shipped!")
            ->line("Your order has been shipped.")
            ->action('Track Order', url("/orders/{$this->order->id}/track"));
    }

    public function toArray($notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'message' => "Order #{$this->order->number} has been shipped",
            'type' => 'order_shipped',
        ];
    }
}
```

**Scaling considerations:**
- Queue notifications on a dedicated `notifications` queue.
- Rate limit SMS/email sends.
- Real-time in-app via WebSockets.

**Interview Tip:** Show the user preference system. Mention queuing and channel-specific rate limits.

---

### Q2. How would you design an e-commerce order processing system?

**Answer:**

**Order state machine:**
```
pending -> confirmed -> processing -> shipped -> delivered
    |         |           |
 cancelled  cancelled   returned -> refunded
```

```php
class Order extends Model
{
    protected $casts = ['status' => OrderStatus::class];

    public function canTransitionTo(OrderStatus $newStatus): bool
    {
        $allowed = match ($this->status) {
            OrderStatus::Pending => [OrderStatus::Confirmed, OrderStatus::Cancelled],
            OrderStatus::Confirmed => [OrderStatus::Processing, OrderStatus::Cancelled],
            OrderStatus::Processing => [OrderStatus::Shipped, OrderStatus::Returned],
            OrderStatus::Shipped => [OrderStatus::Delivered, OrderStatus::Returned],
            OrderStatus::Returned => [OrderStatus::Refunded],
            default => [],
        };
        return in_array($newStatus, $allowed);
    }

    public function transitionTo(OrderStatus $newStatus): void
    {
        if (!$this->canTransitionTo($newStatus)) {
            throw new InvalidOrderTransitionException($this->status, $newStatus);
        }
        $oldStatus = $this->status;
        $this->update(['status' => $newStatus]);
        event(new OrderStatusChanged($this, $oldStatus, $newStatus));
    }
}
```

**Order creation with stock locking:**

```php
class CreateOrderAction
{
    public function execute(OrderDTO $dto): Order
    {
        return DB::transaction(function () use ($dto) {
            foreach ($dto->items as $item) {
                $product = Product::where('id', $item->product_id)
                    ->lockForUpdate()->firstOrFail();
                if ($product->stock < $item->quantity) {
                    throw new InsufficientStockException($product);
                }
            }

            $order = Order::create([
                'user_id' => $dto->userId,
                'status' => OrderStatus::Pending,
                'total' => $dto->total,
            ]);

            foreach ($dto->items as $item) {
                $order->items()->create([...]);
                Product::where('id', $item->product_id)
                    ->decrement('stock', $item->quantity);
            }

            OrderPlaced::dispatch($order);
            return $order;
        });
    }
}
```

**Interview Tip:** Show the state machine pattern. Use transactions with locking for stock management.

---

### Q3. Tell me about a challenging Laravel project you built.

**Answer (Example):**

"I built a multi-vendor marketplace with real-time bidding.

**1. Race conditions in bidding:**
```php
Cache::lock("auction-{$auctionId}", 10)->block(5, function () use ($bid) {
    $currentHighest = Bid::where('auction_id', $bid->auction_id)->max('amount');
    if ($bid->amount > $currentHighest) {
        $bid->save();
        event(new NewHighestBid($bid));
    }
});
```

**2. Payment splitting:** Used Stripe Connect with Express accounts. Platform took 15% fee.

**3. Real-time updates:** Laravel Broadcasting with Pusher for live bid updates.

**4. Performance at scale:**
- Eager loading to solve N+1
- Redis caching for hot auction data
- Elasticsearch for search/filter
- Database read replicas for listing pages"

**Interview Tip:** Be specific with technical challenges and solutions. Use numbers if possible.

---

### Q4. How would you design a rate-limited API that serves 10,000 requests per second?

**Answer:**

```
CloudFront (CDN) -> ALB -> Multiple EC2 instances (Auto Scaling)
                              |
                      Redis Cluster (rate limits + cache)
                              |
                      RDS with Read Replicas
```

**Layer 1: CDN** — Cache static responses at CloudFront (handles 60-70% of traffic).

**Layer 2: Rate Limiting:**
```php
RateLimiter::for('api', function (Request $request) {
    return [
        Limit::perSecond(10)->by($request->user()?->id ?: $request->ip()),
        Limit::perMinute(200)->by($request->user()?->id ?: $request->ip()),
    ];
});
```

**Layer 3: Application Optimization** — Response caching, query optimization, OPcache.

**Layer 4: Infrastructure** — Auto Scaling, Read Replicas, Redis Cluster.

**Back-of-envelope math:**
- 10,000 RPS -> with CDN handling 70% -> 3,000 RPS to application
- Each PHP-FPM process: ~50 req/s -> need 60 processes
- t3.xlarge: ~15 processes -> need 4 instances minimum
- Double for headroom -> 8 instances with auto-scaling

**Interview Tip:** Show layered thinking: CDN -> load balancer -> application -> cache -> database. Do back-of-envelope math.

---

### Q5. How would you implement a search feature with filters, sorting, and pagination?

**Answer:**

**For small-medium datasets (< 1M records):**

```php
class ProductSearchService
{
    public function search(SearchDTO $dto): LengthAwarePaginator
    {
        return Product::query()
            ->select('id', 'name', 'price', 'category_id', 'rating')
            ->with('category:id,name')
            ->when($dto->keyword, function ($q, $keyword) {
                $q->where(function ($q) use ($keyword) {
                    $q->where('name', 'LIKE', "%{$keyword}%")
                      ->orWhere('description', 'LIKE', "%{$keyword}%");
                });
            })
            ->when($dto->categoryId, fn($q, $id) => $q->where('category_id', $id))
            ->when($dto->minPrice, fn($q, $min) => $q->where('price', '>=', $min))
            ->when($dto->maxPrice, fn($q, $max) => $q->where('price', '<=', $max))
            ->when($dto->sortBy, function ($q, $sortBy) use ($dto) {
                $allowed = ['price', 'name', 'created_at', 'rating'];
                if (in_array($sortBy, $allowed)) {
                    $q->orderBy($sortBy, $dto->sortDirection ?? 'asc');
                }
            }, fn($q) => $q->latest())
            ->paginate($dto->perPage ?? 15);
    }
}
```

**For large datasets — Laravel Scout with Meilisearch:**

```php
$products = Product::search($keyword)
    ->where('category', 'Electronics')
    ->where('price', '<=', 1000)
    ->paginate(15);
```

**Interview Tip:** Start with Eloquent for simple cases, mention Scout/Meilisearch for scale. Always whitelist sort columns.

---

### Q6. How did you handle a production scaling issue?

**Answer (Example):**

"Dashboard page took 8+ seconds during peak hours.

**Investigation:**
1. Telescope showed 47 queries per request.
2. EXPLAIN showed full table scan on 2M row table.
3. MySQL CPU at 95%.

**Solutions:**

**1. Fixed N+1 queries (8s -> 3s):**
```php
$projects = $user->projects()
    ->withCount(['tasks', 'members'])
    ->with(['latestActivity'])
    ->get();
```

**2. Added composite index (3s -> 800ms):**
```sql
ALTER TABLE activities ADD INDEX idx_project_created (project_id, created_at DESC);
```

**3. Added Redis caching (800ms -> 150ms):**
```php
$dashboard = Cache::remember("dashboard:{$user->id}", 300, function () use ($user) {
    return $this->buildDashboardData($user);
});
```

**4. Added read replica** for dashboard queries.

**Result:** 150ms load time, 2000+ concurrent users."

**Interview Tip:** Tell a story: problem -> investigation -> solution -> result. Use specific numbers.

---

### Q7. Design a file export system that generates large CSV reports without timing out.

**Answer:**

```php
class GenerateReportExport implements ShouldQueue
{
    public $timeout = 600;

    public function __construct(
        private int $userId,
        private array $filters,
        private string $exportId,
    ) {}

    public function handle()
    {
        $export = Export::find($this->exportId);
        $export->update(['status' => 'processing']);

        try {
            $filename = "exports/{$this->exportId}.csv";
            $tempFile = tempnam(sys_get_temp_dir(), 'export');
            $handle = fopen($tempFile, 'w');

            fputcsv($handle, ['Order ID', 'Customer', 'Amount', 'Status', 'Date']);

            Order::query()
                ->with('user:id,name,email')
                ->when($this->filters['status'] ?? null, fn($q, $s) => $q->where('status', $s))
                ->chunkById(1000, function ($orders) use ($handle) {
                    foreach ($orders as $order) {
                        fputcsv($handle, [
                            $order->id, $order->user->name,
                            $order->total, $order->status->value,
                            $order->created_at->format('Y-m-d'),
                        ]);
                    }
                });

            fclose($handle);
            Storage::disk('s3')->putFileAs('exports', new \Illuminate\Http\File($tempFile), "{$this->exportId}.csv");
            unlink($tempFile);

            $url = Storage::disk('s3')->temporaryUrl("exports/{$this->exportId}.csv", now()->addHours(24));

            $export->update(['status' => 'completed', 'file_url' => $url]);
            User::find($this->userId)->notify(new ExportReadyNotification($export));

        } catch (\Throwable $e) {
            $export->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }
}

// Controller
public function create(ExportRequest $request)
{
    $export = Export::create([
        'user_id' => auth()->id(),
        'type' => 'orders',
        'status' => 'pending',
    ]);

    GenerateReportExport::dispatch(auth()->id(), $request->validated(), $export->id)
        ->onQueue('exports');

    return response()->json(['export_id' => $export->id, 'message' => 'Export started.'], 202);
}
```

**Key design:** Queue-based, chunkById for memory, S3 storage, user notification.

**Interview Tip:** Never generate large files in a web request. "Queue it, chunk it, store on S3, notify the user."

---

### Q8. How would you implement a multi-step wizard form with data persistence?

**Answer:**

```php
class WizardController extends Controller
{
    public function saveStep(Request $request, string $step)
    {
        $rules = match ($step) {
            'personal' => ['first_name' => 'required', 'last_name' => 'required', 'email' => 'required|email'],
            'address' => ['street' => 'required', 'city' => 'required', 'zip' => 'required'],
            'payment' => ['payment_method' => 'required|in:card,bank'],
            default => throw new \InvalidArgumentException("Invalid step"),
        };

        $validated = $request->validate($rules);

        Wizard::updateOrCreate(
            ['user_id' => auth()->id(), 'type' => 'onboarding'],
            ["step_{$step}" => $validated, 'current_step' => $step]
        );

        return response()->json([
            'next_step' => $this->getNextStep($step),
            'completed' => $this->getNextStep($step) === null,
        ]);
    }

    public function submit()
    {
        $wizard = Wizard::where('user_id', auth()->id())
            ->where('type', 'onboarding')->firstOrFail();

        DB::transaction(function () use ($wizard) {
            auth()->user()->update($wizard->step_personal);
            auth()->user()->address()->create($wizard->step_address);
            ProcessOnboarding::dispatch(auth()->user());
            $wizard->delete();
        });

        return response()->json(['message' => 'Onboarding complete']);
    }
}
```

**Interview Tip:** Explain database storage vs session storage trade-off. For important wizards, always use the database.

---