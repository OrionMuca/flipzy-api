# Identity Verification Frontend Implementation Guide

## Overview

ID verification uses **Stripe Identity** — a Stripe-hosted modal that handles everything: camera access, document scanning, selfie + liveness detection. The frontend writes zero verification UI.

The flow:
1. Frontend calls the backend to create a verification session
2. Backend returns a `client_secret`
3. Frontend passes it to `stripe.verifyIdentity()` — Stripe opens a modal
4. User scans their ID + takes a selfie inside the modal
5. Stripe processes it and fires a webhook to the backend
6. Backend updates the user's `id_verification_status`
7. Frontend polls or re-fetches the status to reflect the result

---

## Installation

Install the Stripe.js library (if not already installed for subscriptions):

```bash
npm install @stripe/stripe-js
```

Or via CDN:
```html
<script src="https://js.stripe.com/v3/"></script>
```

---

## Base URL & Auth

All endpoints require a `Bearer` token.

```
Authorization: Bearer <access_token>
Content-Type: application/json
```

---

## Endpoints

### 1. Create Verification Session

```
POST /api/v1/identity/create-session
```

No request body needed — the user is identified from their token.

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "client_secret": "vs_test_a1B2c3...secret_xyz"
  }
}
```

**Response `400` — already verified:**
```json
{
  "success": false,
  "message": "Your identity is already verified."
}
```

---

### 2. Get Verification Status

```
GET /api/v1/identity/status
```

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "status": "verified",
    "verified": true,
    "verified_at": "2026-02-21T00:00:00.000000Z"
  }
}
```

| `status` value | Meaning |
|---|---|
| `unverified` | User has never started verification |
| `pending` | Session created, user hasn't completed the modal yet |
| `processing` | User submitted, Stripe is processing (usually seconds) |
| `verified` | Identity confirmed — use `verified: true` for access control |
| `failed` | Verification rejected (bad document, liveness fail, etc) — user can retry |

---

## Implementation

### Step 1 — Load Stripe

```js
import { loadStripe } from '@stripe/stripe-js'

const stripe = await loadStripe(process.env.STRIPE_PUBLIC_KEY)
```

---

### Step 2 — Trigger the verification flow

Call this when the user clicks "Verify my identity":

```js
async function startVerification() {
  // 1. Create session on the backend
  const response = await fetch('/api/v1/identity/create-session', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${accessToken}`,
      'Content-Type': 'application/json',
    },
  })

  const { success, data, message } = await response.json()

  if (!success) {
    // Handle already verified or server error
    showMessage(message)
    return
  }

  // 2. Open the Stripe Identity modal
  const { error } = await stripe.verifyIdentity(data.client_secret)

  if (error) {
    // User closed the modal or something went wrong
    // error.code === 'session_cancelled' means user dismissed it
    console.error(error)
    showMessage('Verification was cancelled. You can try again anytime.')
    return
  }

  // 3. Modal completed — Stripe is now processing
  // The result comes via webhook (usually within seconds)
  // Poll the status endpoint to get the final result
  pollVerificationStatus()
}
```

---

### Step 3 — Poll for the result

After the modal closes successfully, Stripe needs a moment to process. Poll the status endpoint:

```js
async function pollVerificationStatus(maxAttempts = 10, intervalMs = 2000) {
  for (let i = 0; i < maxAttempts; i++) {
    await new Promise(resolve => setTimeout(resolve, intervalMs))

    const response = await fetch('/api/v1/identity/status', {
      headers: { 'Authorization': `Bearer ${accessToken}` },
    })

    const { data } = await response.json()

    if (data.status === 'verified') {
      showSuccess('Your identity has been verified!')
      updateUserState({ id_verification_status: 'verified' })
      return
    }

    if (data.status === 'failed') {
      showError('Verification failed. Please try again with a valid document.')
      return
    }

    // Still processing — continue polling
  }

  // Timed out — show a pending message
  showMessage('Verification is still processing. Check back in a few minutes.')
}
```

---

## Where to Place Verification in the App

The recommended approach is **deferred verification** — don't block registration, but prompt when the user tries to do something that requires trust.

### Option A — Prompt during onboarding
After the user registers and lands on the dashboard for the first time, show a banner:

```
"Verify your identity to unlock full access →  [Verify Now]"
```

Check `GET /api/v1/identity/status` on app load and store the result globally.

### Option B — Gate a specific action
When a user tries to contact a wholesaler or make an offer, check their status first:

```js
if (!currentUser.id_verification_status === 'verified') {
  openVerificationModal()
  return
}
// proceed with the action
```

---

## Status Badge Component

Show the verification status wherever relevant (profile page, settings, etc):

```js
function VerificationBadge({ status }) {
  const config = {
    verified:   { label: 'ID Verified',      color: 'green'  },
    pending:    { label: 'Verification Pending', color: 'yellow' },
    processing: { label: 'Processing...',     color: 'yellow' },
    failed:     { label: 'Verification Failed',  color: 'red'    },
    unverified: { label: 'Not Verified',      color: 'gray'   },
  }

  const { label, color } = config[status] ?? config.unverified

  return <Badge color={color}>{label}</Badge>
}
```

---

## Testing (Development Only)

In test mode, Stripe's Identity modal shows a **"Use test document"** button — click it instead of scanning a real ID.

You can simulate different outcomes:

| Outcome | How to trigger |
|---|---|
| Verified | Select "Use test document" → complete the flow normally |
| Failed (document unreadable) | Select "Use test document" → choose a failing test scenario |

No real ID is ever needed in test mode.

---

## Full Flow Diagram

```
User clicks "Verify my identity"
        │
        ▼
POST /api/v1/identity/create-session
        │
        │  Returns { client_secret }
        ▼
stripe.verifyIdentity(client_secret)
        │
        │  Stripe modal opens
        │  User scans ID + selfie
        │  Modal closes
        ▼
Poll GET /api/v1/identity/status
        │
        ├── status: "verified"   → show success, unlock features
        ├── status: "failed"     → show error, allow retry
        └── status: "processing" → keep polling
```
