## ROUND 5 — Stripe Payment Integration

---

### Q1. Explain the Stripe payment flow in a Laravel application. How do you implement one-time payments?

**Answer:**

**Payment flow:**
1. Frontend creates a PaymentIntent via your backend.
2. Backend creates PaymentIntent with Stripe SDK, returns `client_secret`.
3. Frontend uses Stripe.js to confirm the payment (handles 3D Secure automatically).
4. Stripe processes the payment and sends webhook events.
5. Backend listens to webhooks to confirm the payment status.

```php
class PaymentController extends Controller
{
    public function createPaymentIntent(Request $request)
    {
        $request->validate(['amount' => 'required|integer|min:50']);
        $user = auth()->user();

        if (!$user->stripe_customer_id) {
            $customer = \Stripe\Customer::create([
                'email' => $user->email,
                'name' => $user->name,
            ]);
            $user->update(['stripe_customer_id' => $customer->id]);
        }

        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $request->amount, // in cents
            'currency' => 'usd',
            'customer' => $user->stripe_customer_id,
            'metadata' => [
                'order_id' => $request->order_id,
                'user_id' => $user->id,
            ],
        ]);

        return response()->json([
            'client_secret' => $paymentIntent->client_secret,
        ]);
    }
}
```

```javascript
// Frontend — Confirm payment with Stripe.js
const { error, paymentIntent } = await stripe.confirmCardPayment(clientSecret, {
    payment_method: {
        card: cardElement,
        billing_details: { name: 'Customer Name' },
    }
});
```

**Important:** Never trust the frontend confirmation alone — always verify payment status via webhooks.

**Interview Tip:** Emphasize the webhook-first approach. The frontend callback is for UX, but the webhook is the source of truth for fulfillment.

---

### Q2. How do you implement Stripe subscriptions with Laravel Cashier?

**Answer:**

```bash
composer require laravel/cashier
php artisan migrate
```

```php
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    use Billable;
}

class SubscriptionController extends Controller
{
    public function subscribe(Request $request)
    {
        $user = auth()->user();
        $user->createOrGetStripeCustomer();
        $user->updateDefaultPaymentMethod($request->payment_method);

        $subscription = $user->newSubscription('default', $request->plan)
            ->trialDays(14)
            ->create($request->payment_method);

        return response()->json(['subscription' => $subscription]);
    }

    public function changePlan(Request $request)
    {
        auth()->user()->subscription('default')->swap($request->new_plan);
        return response()->json(['message' => 'Plan changed']);
    }

    public function cancel()
    {
        auth()->user()->subscription('default')->cancel(); // Cancels at period end
        return response()->json(['message' => 'Subscription cancelled']);
    }
}
```

**Checking subscription status:**
```php
$user->subscribed('default');
$user->subscription('default')->onTrial();
$user->subscription('default')->cancelled();
$user->subscription('default')->ended();
$user->subscribedToPrice('price_premium_monthly');
```

**Interview Tip:** Mention trial periods, grace periods, plan swapping with proration. Show you understand the subscription lifecycle.

---

### Q3. How do you handle Stripe webhooks in production? What events do you listen for?

**Answer:**

```php
class WebhookController extends \Laravel\Cashier\Http\Controllers\WebhookController
{
    public function handlePaymentIntentSucceeded(array $payload)
    {
        $paymentIntent = $payload['data']['object'];
        $orderId = $paymentIntent['metadata']['order_id'] ?? null;

        if ($orderId) {
            $order = Order::find($orderId);
            $order->update([
                'status' => 'paid',
                'stripe_payment_intent_id' => $paymentIntent['id'],
            ]);
            OrderPaid::dispatch($order);
        }

        return response('OK', 200);
    }

    public function handleInvoicePaymentFailed(array $payload)
    {
        $stripeCustomerId = $payload['data']['object']['customer'];
        $user = User::where('stripe_customer_id', $stripeCustomerId)->first();
        $user?->notify(new PaymentFailedNotification());
    }
}
```

**Critical events to handle:**

| Event | Action |
|-------|--------|
| `payment_intent.succeeded` | Fulfill order |
| `payment_intent.payment_failed` | Notify customer |
| `customer.subscription.created` | Activate features |
| `customer.subscription.updated` | Handle plan changes |
| `customer.subscription.deleted` | Revoke access |
| `invoice.payment_failed` | Dunning: warn user |
| `invoice.paid` | Confirm recurring payment |
| `charge.refunded` | Process refund |
| `charge.dispute.created` | Alert team |

**Interview Tip:** Know at least 5 webhook events and their business implications. Mention idempotency and signature verification.

---

### Q4. How do you handle Stripe errors and failed payments gracefully?

**Answer:**

```php
class PaymentService
{
    public function charge(Order $order, string $paymentMethodId): PaymentResult
    {
        try {
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $order->total_in_cents,
                'currency' => 'usd',
                'customer' => $order->user->stripe_customer_id,
                'payment_method' => $paymentMethodId,
                'confirm' => true,
                'return_url' => route('payment.callback'),
            ]);

            if ($paymentIntent->status === 'requires_action') {
                return PaymentResult::requiresAction($paymentIntent->client_secret);
            }
            return PaymentResult::success($paymentIntent->id);

        } catch (\Stripe\Exception\CardException $e) {
            return PaymentResult::failed(
                $this->getUserFriendlyMessage($e->getDeclineCode())
            );
        } catch (\Stripe\Exception\RateLimitException $e) {
            return PaymentResult::retry();
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Log::error('Stripe invalid request', ['message' => $e->getMessage()]);
            return PaymentResult::error('An error occurred processing your payment.');
        } catch (\Stripe\Exception\AuthenticationException $e) {
            Log::critical('Stripe authentication failed');
            return PaymentResult::error('Payment system temporarily unavailable.');
        }
    }

    private function getUserFriendlyMessage(string $declineCode): string
    {
        return match ($declineCode) {
            'insufficient_funds' => 'Your card has insufficient funds.',
            'expired_card' => 'Your card has expired. Please use a different card.',
            'incorrect_cvc' => 'The CVC number is incorrect.',
            default => 'Your payment could not be processed. Please try again.',
        };
    }
}
```

**Interview Tip:** Handle each Stripe exception type differently. Map decline codes to user-friendly messages. Log everything.

---

### Q5. How do you implement Stripe Connect for a marketplace application?

**Answer:**

**Account types:**
- **Standard** — Seller has own Stripe dashboard. Easiest.
- **Express** — Stripe-hosted onboarding, platform controls payouts.
- **Custom** — Full control, most complex.

```php
// 1. Onboard seller (Express account)
public function createAccount()
{
    $account = \Stripe\Account::create([
        'type' => 'express',
        'country' => 'US',
        'email' => auth()->user()->email,
        'capabilities' => [
            'card_payments' => ['requested' => true],
            'transfers' => ['requested' => true],
        ],
    ]);

    auth()->user()->update(['stripe_connect_id' => $account->id]);

    $link = \Stripe\AccountLink::create([
        'account' => $account->id,
        'refresh_url' => route('seller.onboarding.refresh'),
        'return_url' => route('seller.onboarding.complete'),
        'type' => 'account_onboarding',
    ]);

    return redirect($link->url);
}

// 2. Create payment with platform fee
public function pay(Request $request)
{
    $listing = Listing::findOrFail($request->listing_id);

    $paymentIntent = \Stripe\PaymentIntent::create([
        'amount' => $listing->price_in_cents,
        'currency' => 'usd',
        'application_fee_amount' => $listing->price_in_cents * 0.10, // 10% fee
        'transfer_data' => [
            'destination' => $listing->seller->stripe_connect_id,
        ],
    ]);

    return response()->json(['client_secret' => $paymentIntent->client_secret]);
}
```

**Interview Tip:** Mention the three account types and when to use each. Explain `application_fee_amount` for platform revenue.

---

### Q6. How do you handle refunds and disputes in Stripe?

**Answer:**

```php
class RefundController extends Controller
{
    public function refund(Order $order)
    {
        $this->authorize('refund', $order);

        try {
            $refund = \Stripe\Refund::create([
                'payment_intent' => $order->stripe_payment_intent_id,
                'reason' => 'requested_by_customer',
            ]);

            $order->update([
                'status' => 'refunded',
                'refund_id' => $refund->id,
                'refunded_at' => now(),
            ]);

            event(new OrderRefunded($order));
            return response()->json(['message' => 'Refund processed']);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            return response()->json(['error' => 'Refund could not be processed'], 422);
        }
    }
}

// Dispute webhook handling
public function handleChargeDisputeCreated(array $payload)
{
    $dispute = $payload['data']['object'];
    $order = Order::where('stripe_charge_id', $dispute['charge'])->first();

    if ($order) {
        $order->update(['status' => 'disputed']);
        Notification::send(User::admins()->get(), new DisputeCreatedNotification($order));
    }
}
```

**Interview Tip:** Mention that disputes have deadlines and excessive disputes (>1% rate) can get your Stripe account terminated.

---

### Q7. How do you test Stripe integration without making real charges?

**Answer:**

**1. Stripe Test Mode:**
```env
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
```

**2. Test card numbers:**
```
4242 4242 4242 4242  — Succeeds
4000 0000 0000 0002  — Declined (generic)
4000 0000 0000 9995  — Insufficient funds
4000 0025 0000 3155  — Requires 3D Secure
```

**3. Stripe CLI for local webhook testing:**
```bash
stripe listen --forward-to localhost:8000/stripe/webhook
stripe trigger payment_intent.succeeded
```

**4. Unit testing with mocks:**
```php
public function test_successful_payment()
{
    $mockPaymentIntent = Mockery::mock();
    $mockPaymentIntent->status = 'succeeded';
    $mockPaymentIntent->id = 'pi_test_123';

    \Stripe\PaymentIntent::shouldReceive('create')
        ->once()
        ->andReturn($mockPaymentIntent);

    $result = $this->paymentService->charge($order, 'pm_test');
    $this->assertTrue($result->isSuccess());
}
```

**Interview Tip:** Mention the Stripe CLI for local webhook testing — it's the professional approach.

---

### Q8. How do you securely store and handle Stripe API keys and sensitive data?

**Answer:**

**1. Environment variables — Never commit keys:**
```env
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

**2. PCI compliance — Never log or store card numbers.**
Use Stripe.js/Elements — card data goes directly to Stripe, never touches your server.

**3. Production security checklist:**
```
- .env in .gitignore
- API keys in environment variables, not code
- Stripe.js collects card data (PCI SAQ-A compliance)
- Webhook signature verification enabled
- HTTPS enforced on all payment endpoints
- Card data never logged or stored
- Stripe Dashboard access restricted with 2FA
- Test keys on staging, live keys only on production
```

**4. AWS deployment — Use Parameter Store:**
```bash
aws ssm put-parameter --name "/myapp/stripe_secret" --value "sk_live_..." --type "SecureString"
```

**Interview Tip:** Mention PCI compliance levels. Say you never let raw card data touch your servers.

---