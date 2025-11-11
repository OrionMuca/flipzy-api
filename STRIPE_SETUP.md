# Stripe Payment Integration Setup Guide

## Overview

A comprehensive Stripe payment integration has been implemented for the Flipzy backend, including:

- ✅ Payment processing (one-time payments)
- ✅ Refund handling (full and partial)
- ✅ Transaction history (user and admin)
- ✅ Webhook handling for real-time updates
- ✅ Admin dashboard for transaction management
- ✅ Edge case handling
- ✅ Comprehensive Swagger documentation

## What Was Implemented

### 1. Database Tables
- **`transactions`** - Stores all payment and refund transactions
- **`users.stripe_customer_id`** - Added to users table for Stripe customer tracking

### 2. Models
- **`Transaction`** - Model with relationships, scopes, and helper methods
- **`User`** - Updated with `transactions()` relationship and `stripe_customer_id` field

### 3. Services
- **`StripeService`** - Handles all Stripe API interactions:
  - Customer creation/retrieval
  - Payment intent creation
  - Payment confirmation
  - Refund processing
  - Subscription management

### 4. Controllers
- **`PaymentController`** - User payment endpoints
- **`RefundController`** - Refund processing
- **`AdminTransactionController`** - Admin transaction management
- **`StripeWebhookController`** - Webhook event handling

### 5. Routes

#### User Routes (Authenticated)
- `POST /api/v1/payments/intent` - Create payment intent
- `POST /api/v1/payments/confirm` - Confirm payment
- `GET /api/v1/payments/transactions` - Get user transaction history
- `GET /api/v1/payments/transactions/{transaction}` - Get transaction details
- `POST /api/v1/refunds` - Create refund
- `GET /api/v1/refunds/{refund}` - Get refund details

#### Admin Routes (Admin Only)
- `GET /api/v1/admin/transactions` - List all transactions
- `GET /api/v1/admin/transactions/stats` - Get transaction statistics
- `GET /api/v1/admin/transactions/{transaction}` - Get transaction details

#### Webhook Routes (Public, Signature Verified)
- `POST /api/v1/webhooks/stripe` - Stripe webhook endpoint

## Environment Variables

Add the following to your `.env` file:

```env
# Stripe Configuration (Required)
STRIPE_SECRET_KEY=sk_test_...  # Your Stripe secret key (test or live)
STRIPE_PUBLIC_KEY=pk_test_...  # Your Stripe publishable key (test or live)

# Webhook Secret (Optional for local testing)
STRIPE_WEBHOOK_SECRET=whsec_...  # Webhook signing secret (can be omitted for local testing)
```

**Note:** The webhook secret is optional for local development. The webhook handler will work in debug mode without it, but webhook signature verification will be skipped. For production, you MUST set this.

### Getting Your Stripe Keys

1. **Secret Key & Public Key:**
   - Go to [Stripe Dashboard](https://dashboard.stripe.com)
   - Navigate to **Developers** → **API keys**
   - Copy your **Secret key** (starts with `sk_test_` for test mode or `sk_live_` for live)
   - Copy your **Publishable key** (starts with `pk_test_` for test mode or `pk_live_` for live)

2. **Webhook Secret:**
   - Go to **Developers** → **Webhooks**
   - Click **Add endpoint**
   - Set endpoint URL to: `https://yourdomain.com/api/v1/webhooks/stripe`
   - Select events to listen to:
     - `payment_intent.succeeded`
     - `payment_intent.payment_failed`
     - `charge.refunded`
     - `charge.refund.updated`
     - `customer.subscription.created`
     - `customer.subscription.updated`
     - `customer.subscription.deleted`
   - After creating, click on the webhook endpoint
   - Copy the **Signing secret** (starts with `whsec_`)

## Edge Cases Handled

### Payment Edge Cases
- ✅ Payment intent creation failures
- ✅ Payment confirmation failures
- ✅ Duplicate payment attempts
- ✅ Invalid payment amounts
- ✅ Missing Stripe customer
- ✅ Network timeouts

### Refund Edge Cases
- ✅ Refunding already refunded transactions
- ✅ Partial refund validation (cannot exceed original amount)
- ✅ Multiple partial refunds (total cannot exceed original)
- ✅ Refunding non-completed transactions
- ✅ Missing charge ID
- ✅ Stripe refund API failures

### Transaction Edge Cases
- ✅ Concurrent webhook processing
- ✅ Webhook signature verification failures
- ✅ Missing transaction records
- ✅ Status synchronization between Stripe and database
- ✅ Failed payment recovery

### Security Edge Cases
- ✅ Unauthorized refund attempts
- ✅ Transaction ownership validation
- ✅ Webhook signature verification
- ✅ CSRF protection (webhook endpoint excluded)

## API Usage Examples

### 1. Create Payment Intent

```bash
POST /api/v1/payments/intent
Authorization: Bearer {token}

{
  "amount": 29.99,
  "currency": "usd",
  "description": "Premium subscription",
  "metadata": {
    "plan_id": "premium"
  }
}
```

**Response:**
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

### 2. Confirm Payment (Frontend)

On the frontend, use Stripe.js to confirm the payment:

```javascript
const stripe = Stripe('pk_test_...');
const { error } = await stripe.confirmCardPayment(clientSecret, {
  payment_method: {
    card: cardElement,
    billing_details: {
      name: 'Customer Name'
    }
  }
});

if (error) {
  // Handle error
} else {
  // Payment succeeded, call confirm endpoint
  await fetch('/api/v1/payments/confirm', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      payment_intent_id: paymentIntent.id
    })
  });
}
```

### 3. Create Refund

```bash
POST /api/v1/refunds
Authorization: Bearer {token}

{
  "transaction_id": "uuid",
  "amount": 15.00,  # Optional, omit for full refund
  "reason": "requested_by_customer"
}
```

### 4. Get User Transactions

```bash
GET /api/v1/payments/transactions?status=completed&per_page=20
Authorization: Bearer {token}
```

### 5. Admin: Get All Transactions

```bash
GET /api/v1/admin/transactions?status=completed&date_from=2025-01-01
Authorization: Bearer {admin_token}
```

## Testing

### Test Cards (Stripe Test Mode)

- **Success:** `4242 4242 4242 4242`
- **Decline:** `4000 0000 0000 0002`
- **Requires Authentication:** `4000 0025 0000 3155`

Use any future expiry date, any 3-digit CVC, and any postal code.

### Testing Webhooks Locally

**Option 1: Skip Webhooks (Easiest for Initial Testing)**
- You can test payments WITHOUT webhooks initially
- Payment endpoints will work fine - you just won't get automatic status updates
- The `/payments/confirm` endpoint will update transaction status manually
- **You can skip webhook setup entirely for now!**

**Option 2: Stripe CLI (Recommended for Local Testing)**
Install Stripe CLI and forward webhooks to your local server:

```bash
# Install Stripe CLI (if not installed)
# macOS: brew install stripe/stripe-cli/stripe
# Or download from: https://stripe.com/docs/stripe-cli

# Login to Stripe
stripe login

# Forward webhooks to local server
stripe listen --forward-to localhost:8000/api/v1/webhooks/stripe
```

This will give you a webhook signing secret to use in your `.env` file.

**Option 3: ngrok or localtunnel (For Testing with Stripe Dashboard)**
If you want to test webhooks from Stripe Dashboard:

```bash
# Using ngrok (install: brew install ngrok/ngrok/ngrok)
ngrok http 8000

# Or using localtunnel (npm install -g localtunnel)
lt --port 8000
```

Then use the provided URL (e.g., `https://abc123.ngrok.io/api/v1/webhooks/stripe`) in Stripe Dashboard.

## Important Notes

1. **Webhook Endpoint:** 
   - **For local testing:** You can skip webhooks entirely! Payment endpoints work without them.
   - **For production:** The webhook endpoint must be publicly accessible.
   - **For local webhook testing:** Use Stripe CLI (easiest) or ngrok/localtunnel.

2. **CSRF Protection:** The webhook endpoint is excluded from CSRF protection (handled via signature verification).

3. **Transaction Status:** The system maintains transaction status in sync with Stripe via webhooks. Manual status updates are also supported.

4. **Refunds:** Only completed transactions can be refunded. Partial refunds are supported and tracked.

5. **Currency:** Currently defaults to USD. Can be extended to support multiple currencies.

6. **Error Handling:** All Stripe API errors are logged and returned with appropriate HTTP status codes.

## Next Steps

1. Add your Stripe keys to `.env`
2. Set up webhook endpoint in Stripe dashboard
3. Test payment flow with test cards
4. Test refund flow
5. Monitor webhook events in Stripe dashboard
6. Review transaction logs in admin dashboard

## Support

For Stripe-specific issues, refer to:
- [Stripe API Documentation](https://stripe.com/docs/api)
- [Stripe Testing Guide](https://stripe.com/docs/testing)
- [Stripe Webhooks Guide](https://stripe.com/docs/webhooks)

