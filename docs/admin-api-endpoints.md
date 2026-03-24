# Admin API Endpoints

## Authentication

All admin endpoints require:
- `Authorization: Bearer {token}` header
- User must have the `admin` role

**Base URL:** `/api/v1/admin`

**Error Responses:**
- `401` — Missing or invalid token
- `403` — User is not an admin
- `404` — Resource not found
- `422` — Validation error

**Standard Response Format:**
```json
{
  "success": true,
  "message": "optional message",
  "data": { ... }
}
```

---

## 1. User Management

### List Users

```
GET /api/v1/admin/users
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `role` | string | — | Filter: `investor`, `wholesaler`, `admin` |
| `search` | string | — | Search by name or email |
| `sort_by` | string | `created_at` | Sort field |
| `sort_order` | string | `desc` | `asc` or `desc` |
| `per_page` | integer | 15 | Max: 100 |

### Get User

```
GET /api/v1/admin/users/{id}
```

Returns user with roles, properties, and subscription.

### Update User

```
PUT /api/v1/admin/users/{id}
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "role": "wholesaler",
  "password": "newpassword",
  "password_confirmation": "newpassword"
}
```

All fields optional. Password requires confirmation.

### Delete User

```
DELETE /api/v1/admin/users/{id}
```

Cannot delete your own account (returns `422`).

### Suspend User

```
POST /api/v1/admin/users/{id}/suspend
```

Sets `email_verified_at` to null. Cannot suspend yourself.

### Activate User

```
POST /api/v1/admin/users/{id}/activate
```

Sets `email_verified_at` to current timestamp.

---

## 2. Property Management

### List Properties

```
GET /api/v1/admin/properties
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `status` | string | — | `active`, `pending`, `sold`, `draft` |
| `wholesaler_id` | uuid | — | Filter by wholesaler |
| `city` | string | — | Filter by city |
| `state` | string | — | Filter by state |
| `search` | string | — | Search title, address, description |
| `sort_by` | string | `created_at` | Sort field |
| `sort_order` | string | `desc` | `asc` or `desc` |
| `per_page` | integer | 15 | Max: 100 |

### Get Property

```
GET /api/v1/admin/properties/{id}
```

Returns property with wholesaler, images, analytics, and rehab estimates.

### Update Property

```
PUT /api/v1/admin/properties/{id}
Content-Type: application/json

{
  "title": "Updated Title",
  "status": "active",
  "is_featured": true,
  "is_verified": true,
  "allow_inquiries": false
}
```

### Delete Property

```
DELETE /api/v1/admin/properties/{id}
```

### Approve Property

```
POST /api/v1/admin/properties/{id}/approve
```

Sets `status = 'active'` and `is_verified = true`.

### Feature/Unfeature Property

```
POST /api/v1/admin/properties/{id}/feature
Content-Type: application/json

{
  "featured": true
}
```

### Verify Property

```
POST /api/v1/admin/properties/{id}/verify
```

Sets `is_verified = true`.

---

## 3. Subscription Management

### List Subscriptions

```
GET /api/v1/admin/subscriptions
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `plan` | string | — | Filter by plan slug (`basic`, `free`, `premium`, `vip`) |
| `status` | string | — | `active`, `cancelled`, `expired` |
| `sort_by` | string | `created_at` | Sort field |
| `sort_order` | string | `desc` | `asc` or `desc` |
| `per_page` | integer | 15 | Max: 100 |

### Get Subscription

```
GET /api/v1/admin/subscriptions/{id}
```

Returns subscription with user and plan details.

### List Plans

```
GET /api/v1/admin/subscriptions/plans
```

Returns all subscription plans (active and inactive).

### Subscription Stats

```
GET /api/v1/admin/subscriptions/stats
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total": 150,
    "active": 120,
    "cancelled": 30,
    "by_plan": {
      "basic": 100,
      "free": 10,
      "premium": 8,
      "vip": 2
    }
  }
}
```

---

## 4. Transaction Management

### List Transactions

```
GET /api/v1/admin/transactions
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `status` | string | — | `pending`, `completed`, `failed`, `refunded`, `partially_refunded` |
| `type` | string | — | `payment`, `refund`, `subscription`, `one_time` |
| `user_id` | uuid | — | Filter by user |
| `date_from` | date | — | Format: `YYYY-MM-DD` |
| `date_to` | date | — | Format: `YYYY-MM-DD` |
| `sort_by` | string | `created_at` | Sort field |
| `sort_order` | string | `desc` | `asc` or `desc` |
| `per_page` | integer | 15 | Max: 100 |

### Get Transaction

```
GET /api/v1/admin/transactions/{id}
```

### Transaction Stats

```
GET /api/v1/admin/transactions/stats
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `date_from` | date | Optional, `YYYY-MM-DD` |
| `date_to` | date | Optional, `YYYY-MM-DD` |

**Response:**
```json
{
  "success": true,
  "data": {
    "total_revenue": 15000.00,
    "total_refunded": 500.00,
    "net_revenue": 14500.00,
    "total_transactions": 200,
    "completed_transactions": 180,
    "failed_transactions": 10,
    "refunded_transactions": 5,
    "pending_transactions": 5,
    "by_status": {
      "completed": { "count": 180, "total": 15000.00 }
    },
    "by_type": {
      "subscription": { "count": 100, "total": 9900.00 },
      "one_time": { "count": 80, "total": 5100.00 }
    }
  }
}
```

---

## 5. Analytics & Statistics

### Overview

```
GET /api/v1/admin/analytics/overview
```

System-wide statistics (user counts, property counts, engagement metrics).

### User Stats

```
GET /api/v1/admin/analytics/users?days=30
```

| Parameter | Type | Default | Options |
|-----------|------|---------|---------|
| `days` | integer | 30 | 7, 30, 90, 365 |

### Property Stats

```
GET /api/v1/admin/analytics/properties?days=30
```

Same `days` parameter as above.

### Engagement Stats

```
GET /api/v1/admin/analytics/engagement?days=30
```

Views, saves, inquiries metrics.

### Trends

```
GET /api/v1/admin/analytics/trends?days=30
```

Trend data showing changes over time.

### Top Properties

```
GET /api/v1/admin/analytics/top-properties?limit=10&days=30
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | integer | 10 | Range: 1-50 |
| `days` | integer | 30 | 7, 30, 90, 365 |

### Top Wholesalers

```
GET /api/v1/admin/analytics/top-wholesalers?limit=10
```

Ranked by credibility score.

### Geographic Distribution

```
GET /api/v1/admin/analytics/geographic
```

Property distribution by state/city.

---

## 6. System Management

### Health Check

```
GET /api/v1/admin/system/health
```

**Response:**
```json
{
  "success": true,
  "data": {
    "database": { "status": "healthy", "message": "Connected" },
    "cache": { "status": "healthy", "message": "Working", "driver": "redis" },
    "queue": { "status": "healthy", "message": "Working", "driver": "redis" }
  },
  "overall_status": "healthy"
}
```

Returns `503` if any service is degraded.

### System Stats

```
GET /api/v1/admin/system/stats
```

Database table counts, cache/queue driver info.

### Error Logs

```
GET /api/v1/admin/system/logs
```

Returns last 50 lines from `laravel.log`.

### Queue Stats

```
GET /api/v1/admin/system/queue
```

**Response:**
```json
{
  "success": true,
  "data": {
    "pending": 5,
    "failed": 0,
    "status": "normal"
  }
}
```

---

## 7. Waiting List Management

### List Entries

```
GET /api/v1/admin/waiting-list
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `status` | string | — | `pending`, `account_created`, `cancelled` |
| `user_type` | string | `all` | `investor`, `wholesaler`, `both`, `all` |
| `sign_up_month` | integer | — | 1-12 |
| `sign_up_year` | integer | — | Year |
| `search` | string | — | Search email, name, phone, company |
| `sort_by` | string | `created_at` | Sort field |
| `sort_order` | string | `desc` | `asc` or `desc` |
| `per_page` | integer | 15 | Max: 100 |

Response includes `summary` with total records and breakdown by type.

### Get Entry

```
GET /api/v1/admin/waiting-list/{id}
```

### Create Entry

```
POST /api/v1/admin/waiting-list
Content-Type: application/json

{
  "email": "user@example.com",
  "name": "John Doe",
  "phone_number": "+1234567890",
  "company_name": "Acme Inc",
  "selected_roles": ["wholesaler", "investor"],
  "coupon_code": "DISCOUNT10",
  "state": "FL"
}
```

### Update Entry

```
PUT /api/v1/admin/waiting-list/{id}
Content-Type: application/json

{
  "status": "account_created",
  "name": "Updated Name"
}
```

When status changes to `account_created`, `account_created_at` is set automatically.

### Delete Entry

```
DELETE /api/v1/admin/waiting-list/{id}
```

### Stats

```
GET /api/v1/admin/waiting-list/stats
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total": 500,
    "by_status": {
      "pending": 400,
      "account_created": 80,
      "cancelled": 20
    },
    "with_coupons": 50,
    "by_roles": {
      "wholesaler": 200,
      "investor": 250,
      "both": 50
    }
  }
}
```

### Daily Signups

```
GET /api/v1/admin/waiting-list/daily-signups
```

Returns last 30 days breakdown with daily change indicators. Cached for 10 minutes.

### Geographic Distribution

```
GET /api/v1/admin/waiting-list/geographic-distribution
```

State-level distribution with percentages. Cached for 1 hour.

### Export CSV

```
GET /api/v1/admin/waiting-list/export/csv
```

Accepts same filter parameters as list. Downloads CSV file.

### Export Excel

```
GET /api/v1/admin/waiting-list/export/excel
```

Same filters. Downloads `.xlsx` file.

### Export PDF

```
GET /api/v1/admin/waiting-list/export/pdf
```

Same filters. Downloads PDF file.

---

## 8. Coupon Management

### List Coupons

```
GET /api/v1/admin/coupons
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `is_active` | boolean | — | Filter by active status |
| `sort_by` | string | `created_at` | Sort field |
| `sort_order` | string | `desc` | `asc` or `desc` |
| `per_page` | integer | 15 | Max: 100 |

### Get Coupon

```
GET /api/v1/admin/coupons/{id}
```

Includes associated waiting list entries.

### Create Coupon

```
POST /api/v1/admin/coupons
Content-Type: application/json

{
  "code": "SAVE20",
  "name": "20% Off",
  "description": "Launch discount",
  "discount_type": "percentage",
  "discount_value": 20,
  "minimum_amount": 50,
  "maximum_discount": 100,
  "valid_from": "2026-01-01",
  "valid_until": "2026-12-31",
  "usage_limit": 100,
  "user_limit": 1,
  "is_active": true,
  "applicable_plans": ["plan-uuid-1"]
}
```

Code is auto-uppercased. Percentage discounts max at 100%.

### Update Coupon

```
PUT /api/v1/admin/coupons/{id}
```

Same fields as create, all optional.

### Delete Coupon

```
DELETE /api/v1/admin/coupons/{id}
```

Soft-deletes by setting `is_active = false`.

---

## 9. Notifications

### Send Notification

```
POST /api/v1/admin/notifications/send
Content-Type: application/json
```

**To all users:**
```json
{
  "subject": "System Update",
  "message": "We've added new features!",
  "action_url": "https://app.com/features",
  "action_text": "See What's New",
  "recipient_type": "all"
}
```

**To specific users:**
```json
{
  "subject": "Account Notice",
  "message": "Your account needs attention",
  "recipient_type": "selected",
  "user_ids": ["uuid-1", "uuid-2"]
}
```

**To a single user:**
```json
{
  "subject": "Welcome",
  "message": "Welcome to the platform!",
  "recipient_type": "single",
  "user_id": "uuid"
}
```

**To filtered users:**
```json
{
  "subject": "Investor Update",
  "message": "New properties available",
  "recipient_type": "filtered",
  "filters": {
    "role": "investor",
    "search": "john"
  }
}
```

**Response:**
```json
{
  "success": true,
  "message": "Notifications sent successfully",
  "data": {
    "total_users": 50,
    "sent": 48,
    "failed": 2
  }
}
```

---

## 10. Email Campaigns

### List Campaigns

```
GET /api/v1/admin/email-campaigns
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `status` | string | — | Filter by status |
| `sort_by` | string | `created_at` | Sort field |
| `sort_order` | string | `desc` | `asc` or `desc` |
| `per_page` | integer | 15 | Max: 100 |

### Get Campaign

```
GET /api/v1/admin/email-campaigns/{id}
```

Includes campaign statistics.

### Create Campaign

```
POST /api/v1/admin/email-campaigns
Content-Type: application/json

{
  "name": "March Newsletter",
  "subject": "What's new in March",
  "content": "<h1>Hello!</h1><p>Check out our updates...</p>",
  "scheduled_at": "2026-04-01"
}
```

### Update Campaign

```
PUT /api/v1/admin/email-campaigns/{id}
```

Cannot update campaigns that have already been sent (returns `400`).

### Delete Campaign

```
DELETE /api/v1/admin/email-campaigns/{id}
```

### Send Campaign

```
POST /api/v1/admin/email-campaigns/{id}/send
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string | Filter waiting list recipients by status |
| `user_type` | string | Filter by user type |

Sends to waiting list members matching the filters.

### Campaign Stats

```
GET /api/v1/admin/email-campaigns/stats
```

Aggregated statistics across all campaigns.
