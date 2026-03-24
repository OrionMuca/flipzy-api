# Frontend Billing Integration Guide

## Overview

New pricing model:
- **$99/month** base subscription (required before publishing any property)
- **$199 one-time fee** per property to publish it
- Once paid, a property can be freely published/unpublished at no extra cost

---

## Flow Diagram

```
1. User signs up
2. User subscribes to $99/month plan (required)
3. User creates a property → status: "draft", payment_status: "unpaid"
4. User clicks "Publish" → API returns Stripe Checkout URL ($199)
5. User completes payment on Stripe → webhook fires → property becomes "active" + "paid"
6. User can unpublish/re-publish freely (no additional payment)
```

---

## API Endpoints

### 1. Get Available Plans

```
GET /api/v1/subscriptions/plans
Authorization: Bearer {token}
```

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Basic",
      "slug": "basic",
      "description": "Base subscription for wholesalers...",
      "price": "99.00",
      "billing_interval": "monthly",
      "features": ["Unlimited property listings", "..."],
      "is_active": true
    }
  ]
}
```

> Only active plans are returned. Currently only "Basic" ($99/month) is active.

---

### 2. Subscribe to Base Plan ($99/month)

```
POST /api/v1/subscriptions/checkout
Authorization: Bearer {token}
Content-Type: application/json

{
  "plan_id": "uuid-of-basic-plan",
  "success_url": "https://yourapp.com/subscription/success?session_id={CHECKOUT_SESSION_ID}",
  "cancel_url": "https://yourapp.com/subscription/cancel"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "checkout_url": "https://checkout.stripe.com/c/pay/cs_...",
    "session_id": "cs_..."
  }
}
```

**Frontend action:** Redirect the user to `checkout_url`. Stripe handles the payment UI. After payment, user is redirected to your `success_url`.

---

### 3. Check Current Subscription

```
GET /api/v1/subscriptions/current
Authorization: Bearer {token}
```

**Response (has subscription):**
```json
{
  "data": {
    "id": "uuid",
    "status": "active",
    "plan": {
      "id": "uuid",
      "name": "Basic",
      "slug": "basic",
      "price": "99.00"
    },
    "starts_at": "2026-03-24T...",
    "ends_at": null
  }
}
```

**Response (no subscription):** `404` with `"No active subscription found"`

> Use this to determine if the user can publish properties. Show a "Subscribe first" prompt if 404.

---

### 4. Create a Property

```
POST /api/v1/wholesaler/properties
Authorization: Bearer {token}
Content-Type: multipart/form-data

{
  "title": "Beautiful Investment Property",
  "address": "123 Main St",
  "city": "Denver",
  "state": "CO",
  "zip_code": "80202",
  "property_type": "house",
  "asking_price": 250000,
  "images[]": [file1, file2],
  ...
}
```

**Response:**
```json
{
  "success": true,
  "message": "Property created successfully",
  "data": {
    "id": "uuid",
    "title": "Beautiful Investment Property",
    "status": "draft",
    "payment_status": "unpaid",
    ...
  }
}
```

> Properties are always created as `status: "draft"` and `payment_status: "unpaid"`. The `status` field is no longer user-controllable on create.

---

### 5. Publish a Property (Payment Required)

```
POST /api/v1/wholesaler/properties/{property_id}/publish
Authorization: Bearer {token}
Content-Type: application/json

{
  "success_url": "https://yourapp.com/property/{property_id}/published?session_id={CHECKOUT_SESSION_ID}",
  "cancel_url": "https://yourapp.com/property/{property_id}"
}
```

#### Case A: Property NOT yet paid — returns checkout URL

```json
{
  "success": true,
  "message": "Payment required to publish property",
  "data": {
    "requires_payment": true,
    "checkout_url": "https://checkout.stripe.com/c/pay/cs_...",
    "session_id": "cs_...",
    "amount": 199.00
  }
}
```

**Frontend action:** Redirect user to `checkout_url`. After payment completes, the webhook automatically sets the property to `status: "active"` and `payment_status: "paid"`. Redirect the user back to the property page.

#### Case B: Property already paid — publishes immediately

```json
{
  "success": true,
  "message": "Property published successfully",
  "data": {
    "id": "uuid",
    "status": "active",
    "payment_status": "paid",
    ...
  }
}
```

#### Error: No subscription

```json
{
  "success": false,
  "message": "You need an active subscription before publishing properties. Please subscribe to the $99/month plan first."
}
```
Status code: `403`

---

### 6. Unpublish a Property

```
POST /api/v1/wholesaler/properties/{property_id}/unpublish
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "message": "Property unpublished successfully",
  "data": {
    "id": "uuid",
    "status": "draft",
    "payment_status": "paid",
    ...
  }
}
```

> `payment_status` stays `"paid"` — the user can re-publish for free by calling the publish endpoint again.

---

### 7. List My Properties

```
GET /api/v1/wholesaler/properties/my?status=draft&per_page=15
Authorization: Bearer {token}
```

You can filter by status: `draft`, `active`, `pending`, `sold`, `inactive`.

Each property in the response includes `status` and `payment_status` fields.

---

## Frontend UI Recommendations

### Property Card States

| `status` | `payment_status` | What to show |
|----------|------------------|--------------|
| `draft` | `unpaid` | "Unpublished" badge + "Publish ($199)" button |
| `draft` | `paid` | "Unpublished" badge + "Publish" button (free) |
| `active` | `paid` | "Published" badge + "Unpublish" button |
| `sold` | `paid` | "Sold" badge |

### Publish Button Logic

```javascript
async function handlePublish(propertyId) {
  // 1. Check subscription first
  try {
    await api.get('/subscriptions/current');
  } catch (e) {
    if (e.status === 404) {
      // Redirect to subscription page
      showModal("You need a $99/month subscription to publish properties.");
      return;
    }
  }

  // 2. Call publish endpoint
  const response = await api.post(`/wholesaler/properties/${propertyId}/publish`, {
    success_url: `${window.location.origin}/property/${propertyId}/published?session_id={CHECKOUT_SESSION_ID}`,
    cancel_url: `${window.location.origin}/property/${propertyId}`,
  });

  if (response.data.data.requires_payment) {
    // Redirect to Stripe Checkout
    window.location.href = response.data.data.checkout_url;
  } else {
    // Already paid — published immediately
    showSuccess("Property published!");
    refreshPropertyData();
  }
}
```

### After Stripe Payment Redirect

When the user returns to your `success_url` after paying, the property may take a moment to update via webhook. You can:

1. **Poll the property** — Fetch `GET /api/v1/properties/{id}` every 2 seconds until `status === "active"`
2. **Show optimistic UI** — Show "Publishing..." and refresh the page after a short delay

```javascript
// On success_url page
async function waitForPublish(propertyId) {
  const maxAttempts = 10;
  for (let i = 0; i < maxAttempts; i++) {
    const res = await api.get(`/properties/${propertyId}`);
    if (res.data.data.status === 'active') {
      showSuccess("Property published successfully!");
      return;
    }
    await new Promise(r => setTimeout(r, 2000));
  }
  showInfo("Payment received! Your property will be published shortly.");
}
```

---

## Stripe Test Cards

For testing in development/staging:

| Card Number | Scenario |
|-------------|----------|
| `4242 4242 4242 4242` | Successful payment |
| `4000 0000 0000 3220` | Requires 3D Secure authentication |
| `4000 0000 0000 9995` | Payment declined |

Use any future expiry date (e.g., `12/34`) and any 3-digit CVC.

---

## Property Status Reference

| Status | Description |
|--------|-------------|
| `draft` | Created but not published (default for new properties) |
| `active` | Published and visible to investors |
| `pending` | Under review or pending action |
| `sold` | Property has been sold |
| `inactive` | Manually deactivated |

| Payment Status | Description |
|----------------|-------------|
| `unpaid` | Listing fee not yet paid |
| `paid` | Listing fee paid — can publish/unpublish freely |
