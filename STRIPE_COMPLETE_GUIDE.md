# Stripe Payment Integration - Complete Guide

A simple, comprehensive guide to Stripe payments, subscriptions, refunds, and webhooks in the Flipzy backend.

---

## Table of Contents

1. [Quick Setup](#quick-setup)
2. [API Endpoints](#api-endpoints)
3. [Payment Flows](#payment-flows)
4. [Testing](#testing)
5. [Webhooks](#webhooks)
6. [Troubleshooting](#troubleshooting)

---

## Quick Setup

### 1. Environment Variables

Add to `.env`:

```env
# Stripe Keys (Required)
STRIPE_SECRET_KEY=sk_test_...  # Get from Stripe Dashboard → Developers → API keys
STRIPE_PUBLIC_KEY=pk_test_...  # Get from Stripe Dashboard → Developers → API keys

# Webhook Secret (Optional for local, Required for production)
STRIPE_WEBHOOK_SECRET=whsec_...  # Get from Stripe Dashboard → Developers → Webhooks
```

### 2. Get Your Stripe Keys

**Secret & Public Keys:**
1. Go to [Stripe Dashboard](https://dashboard.stripe.com)
2. Navigate to **Developers** → **API keys**
3. Copy **Secret key** (starts with `sk_test_` or `sk_live_`)
4. Copy **Publishable key** (starts with `pk_test_` or `pk_live_`)

**Webhook Secret:**
1. Go to **Developers** → **Webhooks**
2. Click **Add endpoint**
3. Set URL: `https://yourdomain.com/api/v1/webhooks/stripe`
4. Select events:
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
   - `charge.refunded`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
5. Copy **Signing secret** (starts with `whsec_`)

### 3. What's Implemented

✅ **Payment Processing** - One-time payments  
✅ **Subscriptions** - Recurring payments (Checkout & Direct)  
✅ **Refunds** - Full and partial refunds  
✅ **Transaction History** - User and admin views  
✅ **Webhooks** - Real-time status updates  
✅ **Admin Dashboard** - Transaction management  
✅ **Testing** - 35+ tests passing  
✅ **Documentation** - Full Swagger/OpenAPI docs  

---

## API Endpoints

### Subscription Endpoints

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| `GET` | `/api/v1/subscriptions/plans` | Get available plans | ✅ |
| `POST` | `/api/v1/subscriptions/checkout` | Create Stripe Checkout session | ✅ |
| `POST` | `/api/v1/subscriptions` | Create subscription directly | ✅ |
| `GET` | `/api/v1/subscriptions/current` | Get current subscription | ✅ |
| `POST` | `/api/v1/subscriptions/cancel` | Cancel subscription | ✅ |
| `GET` | `/api/v1/subscriptions/history` | Get subscription history | ✅ |

### Payment Endpoints

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| `POST` | `/api/v1/payments/intent` | Create payment intent | ✅ |
| `POST` | `/api/v1/payments/confirm` | Confirm payment | ✅ |
| `GET` | `/api/v1/payments/transactions` | Get transaction history | ✅ |
| `GET` | `/api/v1/payments/transactions/{id}` | Get transaction details | ✅ |

### Refund Endpoints

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| `POST` | `/api/v1/refunds` | Create refund | ✅ |
| `GET` | `/api/v1/refunds/{id}` | Get refund details | ✅ |

### Admin Endpoints

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| `GET` | `/api/v1/admin/transactions` | List all transactions | Admin |
| `GET` | `/api/v1/admin/transactions/stats` | Get statistics | Admin |
| `GET` | `/api/v1/admin/transactions/{id}` | Get transaction details | Admin |

### Webhook Endpoint

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| `POST` | `/api/v1/webhooks/stripe` | Stripe webhook handler | Public (signature verified) |

---

## Payment Flows

### Subscription Flow - Method 1: Stripe Checkout (Recommended)

**Easiest to implement - Stripe handles the payment form**

```
1. Frontend: GET /api/v1/subscriptions/plans
   → Get available plans

2. User selects plan

3. Frontend: POST /api/v1/subscriptions/checkout
   Body: { plan_id, success_url, cancel_url }
   → Returns: { checkout_url }

4. Frontend: Redirect user to checkout_url
   → User completes payment on Stripe's page

5. Stripe: Redirects to success_url
   → Webhook automatically creates subscription

6. Frontend: GET /api/v1/subscriptions/current
   → Verify subscription is active
```

**Example Request:**
```json
POST /api/v1/subscriptions/checkout
{
  "plan_id": "uuid",
  "success_url": "https://yourapp.com/success",
  "cancel_url": "https://yourapp.com/cancel"
}
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "checkout_url": "https://checkout.stripe.com/c/pay/cs_test_...",
    "session_id": "cs_test_..."
  }
}
```

---

### Subscription Flow - Method 2: Direct Subscription

**More control - You handle the payment form**

```
1. Frontend: GET /api/v1/subscriptions/plans
   → Get available plans

2. User enters card in Stripe Elements

3. Frontend: stripe.createPaymentMethod({ type: 'card', card: cardElement })
   → Returns: { id: "pm_123..." }

4. Frontend: POST /api/v1/subscriptions
   Body: { plan_id, payment_method_id }
   → Creates subscription

5. Frontend: GET /api/v1/subscriptions/current
   → Verify subscription
```

**Example Request:**
```json
POST /api/v1/subscriptions
{
  "plan_id": "uuid",
  "payment_method_id": "pm_1234567890"
}
```

---

### One-Time Payment Flow

```
1. Frontend: POST /api/v1/payments/intent
   Body: { amount: 29.99, currency: "usd", description: "..." }
   → Returns: { client_secret }

2. Frontend: stripe.confirmCardPayment(client_secret, { payment_method: {...} })
   → Stripe processes payment

3. Frontend: POST /api/v1/payments/confirm
   Body: { payment_intent_id }
   → Updates transaction status

4. Frontend: GET /api/v1/payments/transactions
   → View transaction history
```

**Example Request:**
```json
POST /api/v1/payments/intent
{
  "amount": 29.99,
  "currency": "usd",
  "description": "Premium subscription"
}
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "transaction_id": "uuid",
    "payment_intent_id": "pi_...",
    "client_secret": "pi_..._secret_...",
    "amount": 29.99,
    "currency": "usd"
  }
}
```

---

### Refund Flow

```
1. User/Admin: POST /api/v1/refunds
   Body: { transaction_id, amount: 15.00 }  # Omit amount for full refund
   → Creates refund

2. Frontend: GET /api/v1/refunds/{id}
   → Get refund details
```

**Example Request:**
```json
POST /api/v1/refunds
{
  "transaction_id": "uuid",
  "amount": 15.00,  # Optional - omit for full refund
  "reason": "requested_by_customer"
}
```

---

## Testing

### Test Cards

Use these in Stripe Checkout or when creating payment methods:

| Card Number | Description | Result |
|-------------|-------------|--------|
| `4242 4242 4242 4242` | Visa (success) | ✅ Payment succeeds |
| `4000 0000 0000 0002` | Visa (declined) | ❌ Card declined |
| `4000 0000 0000 9995` | Visa (insufficient funds) | ❌ Insufficient funds |
| `5555 5555 5555 4444` | Mastercard (success) | ✅ Payment succeeds |
| `4000 0025 0000 3155` | Requires 3D Secure | 🔐 Requires authentication |

**Test Card Details:**
- **Expiry:** Any future date (e.g., 12/25)
- **CVC:** Any 3 digits (e.g., 123)
- **ZIP:** Any 5 digits (e.g., 12345)

### Running Tests

```bash
# Run all Stripe-related tests
php artisan test --filter="PaymentTest|RefundTest|AdminTransactionTest"

# Run specific test suite
php artisan test --filter=PaymentTest
php artisan test --filter=RefundTest
php artisan test --filter=AdminTransactionTest
```

### Test Coverage

✅ **35 tests passing** covering:
- Payment intent creation & confirmation
- Transaction history & filtering
- Full & partial refunds
- Admin transaction management
- Authorization checks
- Edge cases & error handling

---

## Webhooks

### What Webhooks Do

Webhooks automatically update your database when events happen in Stripe:
- ✅ Payment succeeds → Updates transaction status
- ✅ Payment fails → Updates transaction status
- ✅ Subscription created → Creates subscription record
- ✅ Subscription updated → Updates subscription record
- ✅ Refund processed → Updates refund status

### Setting Up Webhooks

**For Production:**
1. Go to Stripe Dashboard → Developers → Webhooks
2. Add endpoint: `https://yourdomain.com/api/v1/webhooks/stripe`
3. Select events (see list below)
4. Copy webhook secret to `.env`

**For Local Testing:**

**Option 1: Skip Webhooks (Easiest)**
- Payment endpoints work without webhooks
- You can test everything except automatic status updates
- Use `/payments/confirm` endpoint to manually update status

**Option 2: Stripe CLI (Recommended)**
```bash
# Install Stripe CLI
# macOS: brew install stripe/stripe-cli/stripe

# Login
stripe login

# Forward webhooks to local server
stripe listen --forward-to localhost:8000/api/v1/webhooks/stripe
```

**Option 3: ngrok (For Stripe Dashboard Testing)**
```bash
# Install ngrok
brew install ngrok/ngrok/ngrok

# Start tunnel
ngrok http 8000

# Use the ngrok URL in Stripe Dashboard
```

### Webhook Events Handled

- `payment_intent.succeeded` - Payment completed
- `payment_intent.payment_failed` - Payment failed
- `charge.refunded` - Refund processed
- `charge.refund.updated` - Refund status updated
- `customer.subscription.created` - Subscription created
- `customer.subscription.updated` - Subscription updated
- `customer.subscription.deleted` - Subscription cancelled
- `checkout.session.completed` - Checkout completed (for waiting list)

---

## Troubleshooting

### Common Issues

**Error: "Subscription plan is not configured for payments"**
- **Solution:** Ensure plan has `stripe_price_id` in database
- Create price in Stripe Dashboard, then update plan record

**Error: "You already have an active subscription"**
- **Solution:** Cancel existing subscription first, or use different user account

**Error: "Payment method not found"**
- **Solution:** Ensure payment method ID is valid and belongs to your Stripe account

**Error: "Webhook secret not configured"**
- **Solution:** For local testing, this is OK if `APP_DEBUG=true`
- For production, set `STRIPE_WEBHOOK_SECRET` in `.env`

**Error: "Transaction not found"**
- **Solution:** Ensure transaction exists and belongs to the user
- Check transaction ID is correct

**Error: "Cannot refund this transaction"**
- **Solution:** Only `completed` transactions can be refunded
- Check transaction status first

### Edge Cases Handled

✅ Payment intent creation failures  
✅ Payment confirmation failures  
✅ Duplicate payment attempts  
✅ Invalid payment amounts  
✅ Refunding already refunded transactions  
✅ Partial refund validation  
✅ Multiple partial refunds  
✅ Webhook signature verification failures  
✅ Concurrent webhook processing  
✅ Missing transaction records  

---

## Quick Reference

### Frontend Integration (Stripe.js)

```javascript
// Initialize Stripe
const stripe = Stripe('pk_test_...');

// Create Payment Method
const { paymentMethod, error } = await stripe.createPaymentMethod({
  type: 'card',
  card: cardElement,
  billing_details: {
    name: 'Customer Name'
  }
});

// Confirm Payment
const { error } = await stripe.confirmCardPayment(clientSecret, {
  payment_method: {
    card: cardElement
  }
});
```

### Important Notes

1. **Always use test keys** (`sk_test_...`, `pk_test_...`) for development
2. **Never send card details** directly to your backend - use Stripe.js
3. **Webhooks are optional** for local testing but required for production
4. **Subscription plans** must have `stripe_price_id` set in database
5. **Error handling** - All endpoints return `success: false` with error messages

---

## Test Coverage Summary

| Test Suite | Tests | Status |
|------------|-------|--------|
| Payment Tests | 11 | ✅ Passing |
| Refund Tests | 12 | ✅ Passing |
| Admin Transaction Tests | 12 | ✅ Passing |
| **Total** | **35** | ✅ **All Passing** |

---

## Next Steps

1. ✅ Add Stripe keys to `.env`
2. ✅ Set up webhook endpoint (optional for local)
3. ✅ Test with test cards
4. ✅ Integrate Stripe.js in frontend
5. ✅ Set up production webhooks
6. ✅ Monitor transactions in admin dashboard

---

## Support

- **Stripe API Docs:** https://stripe.com/docs/api
- **Stripe Testing Guide:** https://stripe.com/docs/testing
- **Stripe Webhooks Guide:** https://stripe.com/docs/webhooks
- **Swagger Documentation:** `/api/documentation`

---

**Ready for production use!** 🚀

