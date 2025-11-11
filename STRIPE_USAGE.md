# Stripe Payment & Subscription Usage Guide

This guide explains how Stripe payments and subscriptions work in the Flipzy backend, including the frontend flow, API endpoints, and manual testing steps.

## Table of Contents
1. [Frontend Flow Overview](#frontend-flow-overview)
2. [Subscription Flow (Two Methods)](#subscription-flow-two-methods)
3. [One-Time Payment Flow](#one-time-payment-flow)
4. [API Endpoints Reference](#api-endpoints-reference)
5. [Manual Testing with Postman](#manual-testing-with-postman)
6. [Stripe Test Cards](#stripe-test-cards)

---

## Frontend Flow Overview

### Subscription Purchase Flow

**Step 1: User Selects Plan**
- Frontend displays available subscription plans
- User clicks on a plan (e.g., Premium, VIP)
- Frontend calls: `GET /api/v1/subscriptions/plans`

**Step 2: User Enters Card Details**
- Frontend shows a payment form with Stripe Elements (card input)
- User enters card number, expiry, CVC, etc.
- Stripe Elements handles card validation client-side

**Step 3: Create Subscription (Two Options)**

#### Option A: Stripe Checkout (Easier - Recommended)
- Frontend calls: `POST /api/v1/subscriptions/checkout` with `plan_id`
- Backend returns `checkout_url`
- Frontend redirects user to Stripe Checkout page
- User completes payment on Stripe's hosted page
- Stripe redirects back to your `success_url` or `cancel_url`
- Webhook updates subscription status automatically

#### Option B: Direct Subscription (More Control)
- Frontend uses Stripe.js to create Payment Method: `stripe.createPaymentMethod()`
- Frontend calls: `POST /api/v1/subscriptions` with `plan_id` and `payment_method_id`
- Backend creates subscription and returns subscription details
- Frontend displays success message

**Step 4: Verify Subscription**
- Frontend calls: `GET /api/v1/subscriptions/current` to verify subscription is active

---

## Subscription Flow (Two Methods)

### Method 1: Stripe Checkout (Recommended for Quick Setup)

```
┌─────────────┐
│   Frontend  │
└──────┬──────┘
       │
       │ 1. GET /api/v1/subscriptions/plans
       │    (Get available plans)
       │
       ▼
┌─────────────┐
│   Backend   │
└──────┬──────┘
       │
       │ Returns: List of plans with prices
       │
       ▼
┌─────────────┐
│   Frontend  │
└──────┬──────┘
       │
       │ User selects plan
       │
       │ 2. POST /api/v1/subscriptions/checkout
       │    Body: { plan_id: "...", success_url: "...", cancel_url: "..." }
       │
       ▼
┌─────────────┐
│   Backend   │
└──────┬──────┘
       │
       │ Creates Stripe Checkout Session
       │ Returns: { checkout_url: "https://checkout.stripe.com/..." }
       │
       ▼
┌─────────────┐
│   Frontend  │
└──────┬──────┘
       │
       │ Redirects user to checkout_url
       │
       ▼
┌─────────────┐
│   Stripe    │
│  Checkout   │
└──────┬──────┘
       │
       │ User enters card details
       │ User completes payment
       │
       │ Stripe redirects to success_url
       │ Webhook: customer.subscription.created
       │
       ▼
┌─────────────┐
│   Backend   │
└──────┬──────┘
       │
       │ Webhook handler creates subscription record
       │
       ▼
┌─────────────┐
│   Frontend  │
└──────┬──────┘
       │
       │ 3. GET /api/v1/subscriptions/current
       │    (Verify subscription is active)
       │
       ▼
```

### Method 2: Direct Subscription (More Control)

```
┌─────────────┐
│   Frontend  │
└──────┬──────┘
       │
       │ 1. GET /api/v1/subscriptions/plans
       │
       ▼
┌─────────────┐
│   Backend   │
└──────┬──────┘
       │
       │ Returns: List of plans
       │
       ▼
┌─────────────┐
│   Frontend  │
└──────┬──────┘
       │
       │ User selects plan
       │ User enters card in Stripe Elements
       │
       │ 2. stripe.createPaymentMethod({ type: 'card', card: cardElement })
       │    (Stripe.js client-side)
       │
       │ Returns: { id: "pm_123..." }
       │
       │ 3. POST /api/v1/subscriptions
       │    Body: { plan_id: "...", payment_method_id: "pm_123..." }
       │
       ▼
┌─────────────┐
│   Backend   │
└──────┬──────┘
       │
       │ - Creates/retrieves Stripe customer
       │ - Attaches payment method to customer
       │ - Creates Stripe subscription
       │ - Creates subscription record in database
       │ - Creates transaction record
       │
       │ Returns: { subscription_id, status, plan, ... }
       │
       ▼
┌─────────────┐
│   Frontend  │
└──────┬──────┘
       │
       │ Displays success message
       │
       │ 4. GET /api/v1/subscriptions/current
       │    (Verify subscription)
       │
       ▼
```

---

## One-Time Payment Flow

For one-time payments (not subscriptions):

```
┌─────────────┐
│   Frontend  │
└──────┬──────┘
       │
       │ 1. POST /api/v1/payments/intent
       │    Body: { amount: 29.99, description: "..." }
       │
       ▼
┌─────────────┐
│   Backend   │
└──────┬──────┘
       │
       │ Creates Stripe Payment Intent
       │ Returns: { client_secret: "pi_..._secret_..." }
       │
       ▼
┌─────────────┐
│   Frontend  │
└──────┬──────┘
       │
       │ User enters card in Stripe Elements
       │
       │ 2. stripe.confirmCardPayment(client_secret, {
       │      payment_method: { card: cardElement }
       │    })
       │
       │ Stripe processes payment
       │
       │ 3. POST /api/v1/payments/confirm
       │    Body: { payment_intent_id: "pi_..." }
       │
       ▼
┌─────────────┐
│   Backend   │
└──────┬──────┘
       │
       │ Confirms payment intent
       │ Updates transaction status
       │
       │ Returns: { status: "succeeded" }
       │
       ▼
```

---

## API Endpoints Reference

### Subscription Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| `GET` | `/api/v1/subscriptions/plans` | Get available subscription plans | ✅ |
| `POST` | `/api/v1/subscriptions/checkout` | Create Stripe Checkout session | ✅ |
| `POST` | `/api/v1/subscriptions` | Create subscription directly | ✅ |
| `GET` | `/api/v1/subscriptions/current` | Get current active subscription | ✅ |
| `POST` | `/api/v1/subscriptions/cancel` | Cancel subscription | ✅ |
| `GET` | `/api/v1/subscriptions/history` | Get subscription history | ✅ |

### Payment Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| `POST` | `/api/v1/payments/intent` | Create payment intent | ✅ |
| `POST` | `/api/v1/payments/confirm` | Confirm payment intent | ✅ |
| `GET` | `/api/v1/payments/transactions` | Get transaction history | ✅ |
| `GET` | `/api/v1/payments/transactions/{id}` | Get single transaction | ✅ |

### Refund Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| `POST` | `/api/v1/refunds` | Create refund | ✅ |
| `GET` | `/api/v1/refunds/{id}` | Get refund details | ✅ |

---

## Manual Testing with Postman

### Prerequisites

1. **Get Your Access Token**
   - Register/Login: `POST /api/v1/register` or `POST /api/v1/login`
   - Copy the `access_token` from response
   - Use it in Postman: `Authorization` → `Bearer Token`

2. **Set Up Postman Environment**
   - Create a new environment
   - Add variables:
     - `base_url`: `http://localhost:8000/api/v1`
     - `token`: Your access token
     - `plan_id`: (Will get from step 1)

3. **Ensure Subscription Plans Exist**
   - Plans should be seeded in database
   - Or create via admin panel/database

---

### Step-by-Step: Test Subscription Creation (Method 2 - Direct)

#### Step 1: Get Available Plans

**Request:**
```
GET {{base_url}}/subscriptions/plans
Authorization: Bearer {{token}}
```

**Expected Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "019a5882-c272-71eb-97c4-3d81aa7a4807",
      "name": "Premium",
      "slug": "premium",
      "price": "29.99",
      "billing_interval": "monthly",
      "stripe_price_id": "price_1234567890",
      ...
    }
  ]
}
```

**Action:** Copy a `plan_id` and `stripe_price_id` from response.

---

#### Step 2: Create Payment Method (Using Stripe Dashboard or Stripe CLI)

**Note:** For Postman testing, you need a Payment Method ID. You have two options:

**Option A: Use Stripe Dashboard**
1. Go to Stripe Dashboard → Developers → Payment Methods
2. Create a test payment method
3. Copy the Payment Method ID (starts with `pm_`)

**Option B: Use Stripe API Directly**
```bash
# Using Stripe CLI (if installed)
stripe payment_methods create \
  --type=card \
  --card[number]=4242424242424242 \
  --card[exp_month]=12 \
  --card[exp_year]=2025 \
  --card[cvc]=123
```

**Option C: Use Postman to Call Stripe API**
```
POST https://api.stripe.com/v1/payment_methods
Authorization: Bearer sk_test_... (Your Stripe Secret Key)
Content-Type: application/x-www-form-urlencoded

type=card&card[number]=4242424242424242&card[exp_month]=12&card[exp_year]=2025&card[cvc]=123
```

**Expected Response:**
```json
{
  "id": "pm_1234567890abcdef",
  "type": "card",
  ...
}
```

**Action:** Copy the `id` (Payment Method ID).

---

#### Step 3: Create Subscription

**Request:**
```
POST {{base_url}}/subscriptions
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "plan_id": "019a5882-c272-71eb-97c4-3d81aa7a4807",
  "payment_method_id": "pm_1234567890abcdef"
}
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "subscription_id": "019a5882-c272-71eb-97c4-3d81aa7a4808",
    "status": "active",
    "plan": {
      "id": "019a5882-c272-71eb-97c4-3d81aa7a4807",
      "name": "Premium",
      "slug": "premium"
    },
    "starts_at": "2025-11-11T10:00:00.000000Z",
    "ends_at": "2025-12-11T10:00:00.000000Z"
  }
}
```

**✅ Success!** Subscription created.

---

#### Step 4: Verify Current Subscription

**Request:**
```
GET {{base_url}}/subscriptions/current
Authorization: Bearer {{token}}
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "id": "019a5882-c272-71eb-97c4-3d81aa7a4808",
    "plan": {
      "id": "019a5882-c272-71eb-97c4-3d81aa7a4807",
      "name": "Premium",
      "slug": "premium",
      "price": "29.99"
    },
    "status": "active",
    "starts_at": "2025-11-11T10:00:00.000000Z",
    "ends_at": "2025-12-11T10:00:00.000000Z"
  }
}
```

---

#### Step 5: Get Subscription History

**Request:**
```
GET {{base_url}}/subscriptions/history
Authorization: Bearer {{token}}
```

**Expected Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "019a5882-c272-71eb-97c4-3d81aa7a4808",
      "plan": {
        "id": "019a5882-c272-71eb-97c4-3d81aa7a4807",
        "name": "Premium",
        "slug": "premium"
      },
      "status": "active",
      "starts_at": "2025-11-11T10:00:00.000000Z",
      "ends_at": "2025-12-11T10:00:00.000000Z"
    }
  ]
}
```

---

#### Step 6: Cancel Subscription (Optional)

**Request:**
```
POST {{base_url}}/subscriptions/cancel
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "immediately": false
}
```

**Note:** 
- `immediately: false` → Cancels at period end (default)
- `immediately: true` → Cancels immediately

**Expected Response:**
```json
{
  "success": true,
  "message": "Subscription will be cancelled at period end",
  "data": {
    "subscription_id": "019a5882-c272-71eb-97c4-3d81aa7a4808",
    "status": "active",
    "ends_at": "2025-12-11T10:00:00.000000Z"
  }
}
```

---

### Step-by-Step: Test Subscription Checkout (Method 1 - Checkout)

#### Step 1: Get Available Plans
Same as Method 2, Step 1.

#### Step 2: Create Checkout Session

**Request:**
```
POST {{base_url}}/subscriptions/checkout
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "plan_id": "019a5882-c272-71eb-97c4-3d81aa7a4807",
  "success_url": "https://yourapp.com/success",
  "cancel_url": "https://yourapp.com/cancel"
}
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "checkout_url": "https://checkout.stripe.com/c/pay/cs_test_...",
    "session_id": "cs_test_..."
  }
}
```

**Action:** 
1. Copy the `checkout_url`
2. Open it in a browser
3. Use test card: `4242 4242 4242 4242`
4. Complete the checkout
5. Stripe redirects to `success_url`
6. Webhook automatically creates subscription

#### Step 3: Verify Subscription
Same as Method 2, Step 4.

---

### Step-by-Step: Test One-Time Payment

#### Step 1: Create Payment Intent

**Request:**
```
POST {{base_url}}/payments/intent
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "amount": 29.99,
  "currency": "usd",
  "description": "Test payment"
}
```

**Expected Response:**
```json
{
  "success": true,
  "payment_intent_id": "pi_1234567890",
  "client_secret": "pi_1234567890_secret_..."
}
```

**Note:** In a real frontend, you would use `client_secret` with Stripe.js to confirm payment. For Postman testing, you can simulate confirmation.

#### Step 2: Confirm Payment Intent

**Request:**
```
POST {{base_url}}/payments/confirm
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "payment_intent_id": "pi_1234567890"
}
```

**Expected Response:**
```json
{
  "success": true,
  "status": "succeeded",
  "message": "Payment succeeded"
}
```

**Note:** This endpoint assumes the payment was already confirmed client-side. For full testing, you'd need to use Stripe.js or Stripe CLI to actually process the payment.

#### Step 3: View Transaction History

**Request:**
```
GET {{base_url}}/payments/transactions
Authorization: Bearer {{token}}
```

**Expected Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "019a5882-c272-71eb-97c4-3d81aa7a4809",
      "type": "payment",
      "status": "completed",
      "amount": "29.99",
      "currency": "usd",
      "description": "Test payment",
      "created_at": "2025-11-11T10:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 1
  }
}
```

---

## Stripe Test Cards

Use these test card numbers in Stripe Checkout or when creating payment methods:

| Card Number | Description | Result |
|-------------|-------------|--------|
| `4242 4242 4242 4242` | Visa (success) | ✅ Payment succeeds |
| `4000 0000 0000 0002` | Visa (declined) | ❌ Card declined |
| `4000 0000 0000 9995` | Visa (insufficient funds) | ❌ Insufficient funds |
| `5555 5555 5555 4444` | Mastercard (success) | ✅ Payment succeeds |
| `4000 0025 0000 3155` | Requires authentication (3D Secure) | 🔐 Requires 3DS |

**Test Card Details:**
- **Expiry:** Any future date (e.g., 12/25)
- **CVC:** Any 3 digits (e.g., 123)
- **ZIP:** Any 5 digits (e.g., 12345)

---

## Important Notes

1. **Webhooks:** For production, set up webhook endpoints in Stripe Dashboard. For local testing, use Stripe CLI or skip webhooks (see `STRIPE_SETUP.md`).

2. **Payment Method Creation:** In a real frontend, use Stripe.js to create payment methods securely. Never send card details directly to your backend.

3. **Subscription Plans:** Ensure subscription plans have `stripe_price_id` set. Create prices in Stripe Dashboard first, then update your database.

4. **Error Handling:** All endpoints return error responses with `success: false` and error messages. Check the response status codes (400, 404, 500, etc.).

5. **Testing:** Use Stripe test mode keys (`sk_test_...` and `pk_test_...`) for all testing. Never use live keys in development.

---

## Quick Reference: Postman Collection

Create a Postman collection with these requests:

1. **Auth**
   - `POST /api/v1/register` - Register user
   - `POST /api/v1/login` - Login user

2. **Subscriptions**
   - `GET /api/v1/subscriptions/plans` - Get plans
   - `POST /api/v1/subscriptions/checkout` - Create checkout
   - `POST /api/v1/subscriptions` - Create subscription
   - `GET /api/v1/subscriptions/current` - Get current
   - `POST /api/v1/subscriptions/cancel` - Cancel subscription
   - `GET /api/v1/subscriptions/history` - Get history

3. **Payments**
   - `POST /api/v1/payments/intent` - Create intent
   - `POST /api/v1/payments/confirm` - Confirm payment
   - `GET /api/v1/payments/transactions` - Get transactions

4. **Refunds**
   - `POST /api/v1/refunds` - Create refund
   - `GET /api/v1/refunds/{id}` - Get refund

---

## Troubleshooting

**Error: "Subscription plan is not configured for payments"**
- Solution: Ensure the plan has a `stripe_price_id` in the database. Create a price in Stripe Dashboard and update the plan.

**Error: "You already have an active subscription"**
- Solution: Cancel existing subscription first, or test with a different user account.

**Error: "Payment method not found"**
- Solution: Ensure the payment method ID is valid and belongs to your Stripe account.

**Error: "Webhook secret not configured"**
- Solution: For local testing, this is OK if `APP_DEBUG=true`. For production, set `STRIPE_WEBHOOK_SECRET` in `.env`.

---

## Next Steps

1. **Frontend Integration:** Use Stripe.js to create payment methods and confirm payments client-side.
2. **Webhook Setup:** Configure webhook endpoints in Stripe Dashboard for production.
3. **Error Handling:** Implement proper error handling and user feedback in frontend.
4. **Testing:** Use Stripe test cards and Stripe Dashboard to verify all flows.

For more details, see `STRIPE_SETUP.md`.

