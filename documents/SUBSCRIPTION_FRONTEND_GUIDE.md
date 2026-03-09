# Subscription Frontend Implementation Guide

## Overview

The subscription flow uses **Stripe Checkout** — a Stripe-hosted payment page. The frontend never handles card data directly. The flow is:

1. Frontend fetches available plans
2. User picks a plan → frontend calls backend to create a checkout session
3. Backend returns a `checkout_url` → frontend redirects the user there
4. User completes payment on Stripe's page
5. Stripe redirects user back to your `success_url` or `cancel_url`
6. The subscription is already saved in the DB via webhook (no extra API call needed)

---

## Base URL

All endpoints are prefixed with `/api/v1` and require a `Bearer` token unless noted.

```
Authorization: Bearer <access_token>
Content-Type: application/json
```

---

## Endpoints

### 1. Get Available Plans

```
GET /api/v1/subscriptions/plans
```

**Response:**
```json
{
  "data": [
    {
      "id": "019a5882-c272-71eb-97c4-3d81aa7a4807",
      "name": "Free",
      "slug": "free",
      "description": "Basic plan for getting started",
      "price": 0.00,
      "billing_interval": "monthly",
      "features": ["View up to 5 properties", "Basic messaging", "Property search"],
      "max_properties": 5,
      "max_messages": 10,
      "has_ai_estimates": false,
      "has_api_access": false,
      "is_active": true
    },
    {
      "id": "019a5882-c272-71eb-97c4-3d81aa7a4808",
      "name": "Premium",
      "slug": "premium",
      "description": "Unlimited properties and AI estimates",
      "price": 49.99,
      "billing_interval": "monthly",
      "features": ["Unlimited property viewing", "Unlimited messaging", "AI rehab cost estimates", "Priority support", "Advanced analytics"],
      "max_properties": null,
      "max_messages": null,
      "has_ai_estimates": true,
      "has_api_access": false,
      "is_active": true
    },
    {
      "id": "019a5882-c272-71eb-97c4-3d81aa7a4809",
      "name": "VIP",
      "slug": "vip",
      "description": "Full access with API integration",
      "price": 199.99,
      "billing_interval": "monthly",
      "features": ["Everything in Premium", "API access", "Custom integrations", "Dedicated support", "White-label options"],
      "max_properties": null,
      "max_messages": null,
      "has_ai_estimates": true,
      "has_api_access": true,
      "is_active": true
    }
  ]
}
```

---

### 2. Create Checkout Session

```
POST /api/v1/subscriptions/checkout
```

**Request body:**
```json
{
  "plan_id": "019a5882-c272-71eb-97c4-3d81aa7a4808",
  "success_url": "https://yourfrontend.com/subscription/success?session_id={CHECKOUT_SESSION_ID}",
  "cancel_url": "https://yourfrontend.com/subscription/cancel"
}
```

> `success_url` and `cancel_url` are optional. If omitted, the backend falls back to the configured `FRONTEND_URL`. Always pass them explicitly in production.
>
> Leave `{CHECKOUT_SESSION_ID}` exactly as-is — Stripe replaces it with the real session ID automatically.

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "checkout_url": "https://checkout.stripe.com/c/pay/cs_test_...",
    "session_id": "cs_test_..."
  }
}
```

**Response `400` — user already has active subscription:**
```json
{
  "success": false,
  "message": "You already have an active subscription. Please cancel it before subscribing to a new plan."
}
```

**What to do with the response:**
```js
// Redirect the user to Stripe's hosted checkout page
window.location.href = data.checkout_url;
```

---

### 3. Get Current Subscription

```
GET /api/v1/subscriptions/current
```

**Response `200`:**
```json
{
  "data": {
    "id": "uuid",
    "user_id": "uuid",
    "status": "active",
    "starts_at": "02-21-2026 00:00:00",
    "ends_at": null,
    "cancelled_at": null,
    "is_active": true,
    "plan": {
      "id": "uuid",
      "name": "Premium",
      "slug": "premium",
      "price": 49.99,
      "billing_interval": "monthly",
      "features": [...],
      "has_ai_estimates": true,
      "has_api_access": false
    }
  }
}
```

**Response `404` — no active subscription:**
```json
{
  "success": false,
  "message": "No active subscription found"
}
```

> Use this endpoint on app load or after the success redirect to display the user's current plan.

---

### 4. Cancel Subscription

```
POST /api/v1/subscriptions/cancel
```

**Request body:**
```json
{
  "immediately": false
}
```

| `immediately` | Behaviour |
|---|---|
| `false` (default) | User keeps access until end of billing period, then it stops. Recommended. |
| `true` | Access is cut off immediately. |

**Response `200`:**
```json
{
  "success": true,
  "message": "Subscription will be cancelled at period end",
  "data": {
    "subscription_id": "uuid",
    "status": "active",
    "ends_at": "03-21-2026 00:00:00"
  }
}
```

> After cancellation with `immediately: false`, `status` stays `"active"` and `ends_at` is set to the last day of the billing period. Show this date to the user: *"Your subscription is active until March 21, 2026."*

---

### 5. Subscription History

```
GET /api/v1/subscriptions/history
```

Returns all past and current subscriptions for the user, ordered by most recent.

**Response `200`:**
```json
{
  "data": [
    {
      "id": "uuid",
      "status": "active",
      "starts_at": "02-21-2026 00:00:00",
      "ends_at": null,
      "plan": { ... }
    }
  ]
}
```

---

## Pages to Implement

### `/subscription/success`

The user lands here after completing payment on Stripe's checkout page.

- The URL will contain `?session_id=cs_test_...` — you can display a generic success message; no extra API call is required since the subscription is already saved via webhook.
- Call `GET /api/v1/subscriptions/current` to fetch and display the active plan details if needed.

**Suggested content:**
- Confirmation message: *"You're now subscribed to Premium!"*
- CTA button back to the dashboard or home

---

### `/subscription/cancel`

The user lands here if they click **"Back"** or close Stripe's checkout page without paying.

- No subscription was created — nothing to do on the backend.
- Show a message like *"No payment was made. You can subscribe anytime."*
- Offer a button to go back to the plans page.

> This is NOT the same as cancelling a subscription. This page just means the user abandoned the Stripe checkout form.

---

### `/subscription/manage` (recommended)

A settings page where the user can:
- See their current plan (`GET /api/v1/subscriptions/current`)
- See the `ends_at` date if they've already cancelled
- Click a **"Cancel Subscription"** button → calls `POST /api/v1/subscriptions/cancel`
- See subscription history (`GET /api/v1/subscriptions/history`)

---

## Subscription Status Reference

| `status` | Meaning |
|---|---|
| `active` | Subscription is live and paid |
| `past_due` | Latest renewal payment failed — show a warning to update payment method |
| `cancelled` | Subscription has ended |
| `pending` | Payment initiated but not yet confirmed |

> Always use the `is_active` boolean field from the API for access control checks — it accounts for both `status` and `ends_at`.

---

## Full Flow Diagram

```
[Plans Page]
    │
    │  User clicks "Subscribe to Premium"
    ▼
POST /api/v1/subscriptions/checkout
    │
    │  Returns { checkout_url }
    ▼
window.location.href = checkout_url
    │
    │  User on Stripe's hosted page
    │  Enters card → pays
    ▼
    ├── Success → redirected to /subscription/success?session_id=...
    │               └── show confirmation, call GET /subscriptions/current
    │
    └── Abandoned → redirected to /subscription/cancel
                      └── show "no payment made" message
```

---

## Testing (Development Only)

Use these Stripe test card numbers on the checkout page:

| Scenario | Card Number |
|---|---|
| Successful payment | `4242 4242 4242 4242` |
| Payment requires 3D Secure | `4000 0025 0000 3155` |
| Payment declined | `4000 0000 0000 9995` |

- Expiry: any future date (e.g. `12/29`)
- CVC: any 3 digits
- Name / ZIP: anything
