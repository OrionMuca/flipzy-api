# Flipzy Backend API Documentation

**Base URL:** `/api/v1`  
**Authentication:** Bearer Token (OAuth 2.0)

---

## Table of Contents

1. [Authentication](#authentication)
2. [Properties](#properties)
3. [Buy Box](#buy-box)
4. [Messaging](#messaging)
5. [Analytics](#analytics)
6. [AI Rehab Estimation](#ai-rehab-estimation)
7. [Payments](#payments)
8. [Subscriptions](#subscriptions)
9. [Refunds](#refunds)
10. [Notifications](#notifications)
11. [Waiting List](#waiting-list)
12. [Admin Endpoints](#admin-endpoints)
13. [Webhooks](#webhooks)

---

## Authentication

### Register User
**POST** `/register`

**Description:** Register a new user account

**Request Body:**
```json
{
  "name": "string (required)",
  "email": "string (required, email format)",
  "password": "string (required, min: 8)",
  "password_confirmation": "string (required, must match password)",
  "role": "string (required, enum: investor|wholesaler)"
}
```

**Response:** `201 Created`
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user": "object",
    "access_token": "string",
    "token_type": "Bearer",
    "expires_in": "integer|null"
  }
}
```

---

### Login
**POST** `/login`

**Description:** Authenticate user and receive access token

**Request Body:**
```json
{
  "email": "string (required, email format)",
  "password": "string (required)"
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "string",
  "data": {
    "user": "object",
    "access_token": "string",
    "token_type": "Bearer",
    "expires_in": "integer|null",
    "email_verified": "boolean"
  }
}
```

---

### Get Current User
**GET** `/user`

**Description:** Get authenticated user information

**Authentication:** Required

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "user": "object"
  }
}
```

---

### Logout
**POST** `/logout`

**Description:** Logout user and revoke token

**Authentication:** Required

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Successfully logged out"
}
```

---

### Forgot Password
**POST** `/password/forgot`

**Description:** Request password reset link

**Request Body:**
```json
{
  "email": "string (required, email format)"
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Password reset link has been sent to your email."
}
```

---

### Reset Password
**POST** `/password/reset`

**Description:** Reset password using token from email

**Request Body:**
```json
{
  "email": "string (required, email format)",
  "token": "string (required)",
  "password": "string (required, min: 8)",
  "password_confirmation": "string (required, must match password)"
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Password has been reset successfully."
}
```

---

### Verify Email
**GET** `/email/verify`

**Description:** Verify email address using token

**Query Parameters:**
- `token` (string, required)
- `email` (string, required, email format)

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Email verified successfully"
}
```

---

## Properties

### List Properties
**GET** `/properties`

**Description:** Get paginated list of properties with filters

**Query Parameters:**
- `city` (string, optional)
- `state` (string, optional)
- `property_type` (string, optional)
- `status` (string, optional, enum: active|pending|sold)
- `min_price` (number, optional)
- `max_price` (number, optional)
- `bedrooms` (integer, optional)
- `bathrooms` (number, optional)
- `featured` (boolean, optional)
- `verified` (boolean, optional)
- `search` (string, optional)
- `sort_by` (string, optional)
- `sort_order` (string, optional, enum: asc|desc)
- `per_page` (integer, optional, default: 15)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "properties": "array",
    "pagination": "object"
  }
}
```

---

### Get Property
**GET** `/properties/{property}`

**Description:** Get detailed information about a specific property

**Path Parameters:**
- `property` (UUID, required)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "property": "object"
  }
}
```

---

### Create Property
**POST** `/properties`

**Description:** Create a new property listing (requires authentication and wholesaler role)

**Authentication:** Required

**Request Body (multipart/form-data):**
- `title` (string, required)
- `description` (string, optional)
- `property_type` (string, required, enum: house|condo|townhouse|apartment|land|other)
- `address` (string, required)
- `city` (string, required)
- `state` (string, required)
- `zip_code` (string, optional)
- `bedrooms` (integer, optional)
- `bathrooms` (number, optional)
- `square_feet` (integer, optional)
- `lot_size` (number, optional)
- `year_built` (integer, optional)
- `asking_price` (number, required)
- `arv` (number, optional)
- `status` (string, optional, enum: active|pending|sold, default: active)
- `images` (array of files, optional, max: 10, max size: 5MB each)
- `primary_image_index` (integer, optional)

**Response:** `201 Created`
```json
{
  "success": true,
  "message": "Property created successfully",
  "data": {
    "property": "object"
  }
}
```

---

### Update Property
**PUT** `/properties/{property}`

**Description:** Update a property (only owner can update)

**Authentication:** Required

**Path Parameters:**
- `property` (UUID, required)

**Request Body (multipart/form-data):**
- `title` (string, optional)
- `description` (string, optional)
- `asking_price` (number, optional)
- `status` (string, optional, enum: active|pending|sold)
- `images` (array of files, optional)
- `primary_image_index` (integer, optional)

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Property updated successfully",
  "data": {
    "property": "object"
  }
}
```

---

### Delete Property
**DELETE** `/properties/{property}`

**Description:** Delete a property (only owner can delete)

**Authentication:** Required

**Path Parameters:**
- `property` (UUID, required)

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Property deleted successfully"
}
```

---

### Upload Property Images
**POST** `/properties/{property}/images`

**Description:** Upload images for a property (max 10 images, 5MB each)

**Authentication:** Required

**Path Parameters:**
- `property` (UUID, required)

**Request Body (multipart/form-data):**
- `images` (array of files, required, max: 10)
- `primary_index` (integer, optional, default: 0)

**Response:** `201 Created`
```json
{
  "success": true,
  "message": "Images uploaded successfully",
  "data": {
    "images": "array"
  }
}
```

---

### Delete Property Image
**DELETE** `/properties/{property}/images/{image}`

**Description:** Delete a specific property image

**Authentication:** Required

**Path Parameters:**
- `property` (UUID, required)
- `image` (UUID, required)

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Image deleted successfully"
}
```

---

### Set Primary Image
**PUT** `/properties/{property}/images/{image}/primary`

**Description:** Set a specific image as the primary image

**Authentication:** Required

**Path Parameters:**
- `property` (UUID, required)
- `image` (UUID, required)

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Primary image updated successfully"
}
```

---

### Enrich Property
**POST** `/properties/{property}/enrich`

**Description:** Enrich property data from external APIs (async by default)

**Authentication:** Required

**Path Parameters:**
- `property` (UUID, required)

**Query Parameters:**
- `sync` (boolean, optional, default: false) - If true, waits for enrichment to complete
- `force_fresh` (boolean, optional, default: false) - Force fresh data fetch

**Response:** `202 Accepted` (async) or `200 OK` (sync)
```json
{
  "success": true,
  "message": "Property enrichment queued. Data will be updated shortly.",
  "data": {
    "property_id": "uuid",
    "status": "queued"
  }
}
```

---

## Buy Box

The Buy Box feature allows investors to define and store their property investment preferences. All fields are optional, and investors can update their preferences at any time.

**Note:** Only users with the `investor` or `admin` role can access and manage buy boxes.

---

### Get Buy Box
**GET** `/buy-box`

**Description:** Get the authenticated user's buy box preferences. Creates an empty buy box if one doesn't exist.

**Authentication:** Required (investor or admin role)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "user_id": "uuid",
    "location": {
      "preferred_cities": ["string"],
      "preferred_zip_codes": ["string"],
      "target_counties": ["string"],
      "target_neighborhoods": ["string"],
      "must_have_amenities": ["string"]
    },
    "property_details": {
      "bedrooms": {
        "min": "integer|null",
        "max": "integer|null"
      },
      "bathrooms": {
        "min": "number|null",
        "max": "number|null"
      },
      "square_feet": {
        "min": "integer|null",
        "max": "integer|null"
      },
      "lot_size": {
        "min": "integer|null",
        "max": "integer|null"
      }
    },
    "property_conditions": ["string"],
    "property_types": ["string"],
    "has_adu_potential": "boolean|null",
    "construction_types": ["string"],
    "amenities": {
      "has_pool": "boolean|null",
      "is_waterfront": "boolean|null"
    },
    "layout_types": ["string"],
    "funding_methods": ["string"],
    "rental_investment_criteria": {
      "min_profit": "number|null",
      "min_roi": "number|null",
      "target_cap_rate": "number|null",
      "desired_occupancy_rate": "number|null",
      "expected_monthly_cash_flow": "number|null",
      "expected_annual_cash_flow": "number|null"
    },
    "investment_strategies": ["string"],
    "created_at": "string",
    "updated_at": "string"
  }
}
```

**Error Responses:**
- `401 Unauthorized` - User is not authenticated
- `403 Forbidden` - User does not have investor or admin role

---

### Update Buy Box
**PUT** `/buy-box`

**Description:** Create or update the authenticated user's buy box preferences. All fields are optional. Partial updates are supported.

**Authentication:** Required (investor or admin role)

**Request Body:**
```json
{
  "preferred_cities": ["string (optional, max: 100 each)"],
  "preferred_zip_codes": ["string (optional, max: 10 each)"],
  "target_counties": ["string (optional, max: 100 each)"],
  "target_neighborhoods": ["string (optional, max: 100 each)"],
  "must_have_amenities": ["string (optional, max: 255 each)"],
  "min_bedrooms": "integer (optional, min: 0, max: 50)",
  "max_bedrooms": "integer (optional, min: 0, max: 50, must be >= min_bedrooms)",
  "min_bathrooms": "number (optional, min: 0, max: 50)",
  "max_bathrooms": "number (optional, min: 0, max: 50, must be >= min_bathrooms)",
  "min_square_feet": "integer (optional, min: 0)",
  "max_square_feet": "integer (optional, min: 0, must be >= min_square_feet)",
  "min_lot_size": "integer (optional, min: 0)",
  "max_lot_size": "integer (optional, min: 0, must be >= min_lot_size)",
  "property_conditions": ["string (optional, enum: Turnkey|Retail Ready|Rental Ready)"],
  "property_types": ["string (optional, enum: Single-Family|Land|Multifamily|Commercial)"],
  "has_adu_potential": "boolean (optional)",
  "construction_types": ["string (optional, enum: Brick Built|Stick Built|Block Built|Stucco Exterior|Other)"],
  "has_pool": "boolean (optional)",
  "is_waterfront": "boolean (optional)",
  "layout_types": ["string (optional, enum: Open floor plan|Traditional|Custom)"],
  "funding_methods": ["string (optional, enum: Cash|Hard money|Private money|DSCR loans|Conventional mortgages|FHA loans|VA loans)"],
  "min_profit": "number (optional, min: 0)",
  "min_roi": "number (optional, min: 0, max: 100)",
  "target_cap_rate": "number (optional, min: 0, max: 100)",
  "desired_occupancy_rate": "number (optional, min: 0, max: 100)",
  "expected_monthly_cash_flow": "number (optional)",
  "expected_annual_cash_flow": "number (optional)",
  "investment_strategies": ["string (optional, enum: Fix and Flip|Short-Term Rental|Mid-Term Rental|Long-Term Rental|Lease Option|House Hacking)"]
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Buy box updated successfully",
  "data": {
    "id": "uuid",
    "user_id": "uuid",
    "location": {
      "preferred_cities": ["string"],
      "preferred_zip_codes": ["string"],
      "target_counties": ["string"],
      "target_neighborhoods": ["string"],
      "must_have_amenities": ["string"]
    },
    "property_details": {
      "bedrooms": {
        "min": "integer|null",
        "max": "integer|null"
      },
      "bathrooms": {
        "min": "number|null",
        "max": "number|null"
      },
      "square_feet": {
        "min": "integer|null",
        "max": "integer|null"
      },
      "lot_size": {
        "min": "integer|null",
        "max": "integer|null"
      }
    },
    "property_conditions": ["string"],
    "property_types": ["string"],
    "has_adu_potential": "boolean|null",
    "construction_types": ["string"],
    "amenities": {
      "has_pool": "boolean|null",
      "is_waterfront": "boolean|null"
    },
    "layout_types": ["string"],
    "funding_methods": ["string"],
    "rental_investment_criteria": {
      "min_profit": "number|null",
      "min_roi": "number|null",
      "target_cap_rate": "number|null",
      "desired_occupancy_rate": "number|null",
      "expected_monthly_cash_flow": "number|null",
      "expected_annual_cash_flow": "number|null"
    },
    "investment_strategies": ["string"],
    "created_at": "string",
    "updated_at": "string"
  }
}
```

**Validation Rules:**
- All fields are optional
- `max_bedrooms` must be >= `min_bedrooms` (if both provided)
- `max_bathrooms` must be >= `min_bathrooms` (if both provided)
- `max_square_feet` must be >= `min_square_feet` (if both provided)
- `max_lot_size` must be >= `min_lot_size` (if both provided)
- `min_roi`, `target_cap_rate`, and `desired_occupancy_rate` cannot exceed 100
- Array fields accept empty arrays `[]` to clear preferences

**Error Responses:**
- `401 Unauthorized` - User is not authenticated
- `403 Forbidden` - User does not have investor or admin role
- `422 Validation Error` - Invalid input data

**Example Request:**
```json
{
  "preferred_cities": ["Miami", "Tampa"],
  "min_bedrooms": 3,
  "max_bedrooms": 5,
  "property_types": ["Single-Family", "Multifamily"],
  "has_pool": true,
  "investment_strategies": ["Fix and Flip", "Long-Term Rental"],
  "min_profit": 50000,
  "min_roi": 15.5
}
```

---

## Messaging

### List Conversations
**GET** `/conversations`

**Description:** Get all conversations for authenticated user

**Authentication:** Required

**Query Parameters:**
- `per_page` (integer, optional, default: 15)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "conversations": "array"
  }
}
```

---

### Get Conversation
**GET** `/conversations/{conversation}`

**Description:** Get details of a specific conversation

**Authentication:** Required

**Path Parameters:**
- `conversation` (UUID, required)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "conversation": "object"
  }
}
```

---

### Create or Get Conversation
**POST** `/conversations`

**Description:** Create a new conversation or retrieve existing one

**Authentication:** Required

**Request Body:**
```json
{
  "user_id": "uuid (required)",
  "property_id": "uuid (optional)"
}
```

**Response:** `201 Created`
```json
{
  "success": true,
  "message": "Conversation retrieved or created successfully",
  "data": {
    "conversation": "object"
  }
}
```

---

### Get Conversation Messages
**GET** `/conversations/{conversation}/messages`

**Description:** Get all messages in a conversation

**Authentication:** Required

**Path Parameters:**
- `conversation` (UUID, required)

**Query Parameters:**
- `per_page` (integer, optional, default: 50)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "messages": "array"
  }
}
```

---

### Send Message
**POST** `/conversations/{conversation}/messages`

**Description:** Send a message in a conversation

**Authentication:** Required

**Path Parameters:**
- `conversation` (UUID, required)

**Request Body:**
```json
{
  "body": "string (required, max: 5000)"
}
```

**Response:** `201 Created`
```json
{
  "success": true,
  "message": "Message sent successfully",
  "data": {
    "message": "object"
  }
}
```

---

### Mark Conversation as Read
**PUT** `/conversations/{conversation}/messages/read`

**Description:** Mark all unread messages in a conversation as read

**Authentication:** Required

**Path Parameters:**
- `conversation` (UUID, required)

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Marked X message(s) as read",
  "data": {
    "conversation_id": "uuid",
    "messages_marked": "integer"
  }
}
```

---

### Mark Message as Read
**PUT** `/messages/{message}/read`

**Description:** Mark a specific message as read

**Authentication:** Required

**Path Parameters:**
- `message` (UUID, required)

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Message marked as read",
  "data": {
    "message": "object"
  }
}
```

---

### Get Unread Message Count
**GET** `/messages/unread-count`

**Description:** Get total unread message count for authenticated user

**Authentication:** Required

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "unread_count": "integer"
  }
}
```

---

## Analytics

### Track Property View
**POST** `/properties/{property}/view`

**Description:** Record a view event for a property

**Authentication:** Required

**Path Parameters:**
- `property` (UUID, required)

**Request Body (optional):**
```json
{
  "referrer": "string (optional)",
  "source": "string (optional)"
}
```

**Response:** `201 Created`
```json
{
  "success": true,
  "message": "View tracked successfully",
  "data": {
    "event_id": "uuid",
    "event_type": "view",
    "property_id": "uuid"
  }
}
```

---

### Track Property Save
**POST** `/properties/{property}/save`

**Description:** Save a property to user's favorites

**Authentication:** Required

**Path Parameters:**
- `property` (UUID, required)

**Request Body (optional):**
```json
{
  "notes": "string (optional, max: 500)",
  "list_name": "string (optional, max: 100)"
}
```

**Response:** `201 Created` or `200 OK` (if already saved)
```json
{
  "success": true,
  "message": "Save tracked successfully",
  "data": {
    "event_id": "uuid",
    "event_type": "save",
    "property_id": "uuid"
  }
}
```

---

### Track Property Inquiry
**POST** `/properties/{property}/inquiry`

**Description:** Record an inquiry event for a property

**Authentication:** Required

**Path Parameters:**
- `property` (UUID, required)

**Request Body (optional):**
```json
{
  "message": "string (optional, max: 1000)",
  "contact_method": "string (optional, enum: platform|phone|email)"
}
```

**Response:** `201 Created`
```json
{
  "success": true,
  "message": "Inquiry tracked successfully",
  "data": {
    "event_id": "uuid",
    "event_type": "inquiry",
    "property_id": "uuid"
  }
}
```

---

### Get Property Analytics
**GET** `/properties/{property}/analytics`

**Description:** Get analytics data for a property

**Authentication:** Required

**Path Parameters:**
- `property` (UUID, required)

**Query Parameters:**
- `days` (integer, optional, enum: 7|30|90|365, default: 30)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "analytics": "object"
  }
}
```

---

### Get User Credibility Score
**GET** `/users/{user}/credibility`

**Description:** Get credibility score for a wholesaler

**Authentication:** Required

**Path Parameters:**
- `user` (UUID, required, must be wholesaler)

**Query Parameters:**
- `days` (integer, optional, enum: 7|30|90|365, default: 90)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "credibility": "object"
  }
}
```

---

### Get My Analytics
**GET** `/analytics/my-analytics`

**Description:** Get analytics summary for authenticated wholesaler

**Authentication:** Required (wholesaler role)

**Query Parameters:**
- `days` (integer, optional, enum: 7|30|90|365, default: 30)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "analytics": "object"
  }
}
```

---

## AI Rehab Estimation

### Generate Rehab Estimate
**POST** `/properties/{property}/estimate`

**Description:** Generate AI-powered rehabilitation cost estimate (requires Premium/VIP subscription or admin)

**Authentication:** Required

**Path Parameters:**
- `property` (UUID, required)

**Query Parameters:**
- `force_refresh` (boolean, optional, default: false)
- `model` (string, optional, enum: gpt-3.5-turbo|gpt-4|gpt-4-turbo|gpt-4o, default: gpt-3.5-turbo)
- `use_calculations` (boolean, optional, default: false)

**Response:** `201 Created`
```json
{
  "success": true,
  "message": "Rehab estimate generated successfully",
  "data": {
    "estimate": "object"
  }
}
```

**Rate Limits:**
- Premium: 10 estimates per day
- VIP: 100 estimates per day
- Admin: Unlimited

---

### Get Estimate History
**GET** `/properties/{property}/estimates`

**Description:** Get history of all rehab estimates for a property

**Authentication:** Required

**Path Parameters:**
- `property` (UUID, required)

**Query Parameters:**
- `limit` (integer, optional, default: 10, max: 50)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "estimates": "array"
  }
}
```

---

### Get Estimate Details
**GET** `/estimates/{estimate}`

**Description:** Get detailed information about a specific estimate

**Authentication:** Required (admin or estimate requester)

**Path Parameters:**
- `estimate` (UUID, required)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "estimate": "object"
  }
}
```

---

## Payments

### Create Payment Intent
**POST** `/payments/intent`

**Description:** Create a Stripe payment intent for one-time payment

**Authentication:** Required

**Request Body:**
```json
{
  "amount": "number (required, min: 0.50, max: 999999.99)",
  "currency": "string (optional, default: usd)",
  "description": "string (optional, max: 500)",
  "metadata": "object (optional)"
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "transaction_id": "uuid",
    "payment_intent_id": "string",
    "client_secret": "string",
    "amount": "number",
    "currency": "string"
  }
}
```

---

### Confirm Payment Intent
**POST** `/payments/confirm`

**Description:** Confirm a payment intent after client-side confirmation

**Authentication:** Required

**Request Body:**
```json
{
  "payment_intent_id": "string (required)"
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "transaction": "object"
  }
}
```

---

### Get Transactions
**GET** `/payments/transactions`

**Description:** Get paginated list of user transactions

**Authentication:** Required

**Query Parameters:**
- `page` (integer, optional, default: 1)
- `per_page` (integer, optional, default: 15)
- `status` (string, optional, enum: pending|completed|failed|refunded|partially_refunded)
- `type` (string, optional, enum: payment|refund|subscription|one_time)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "transactions": "array",
    "pagination": "object"
  }
}
```

---

### Get Transaction Details
**GET** `/payments/transactions/{transaction}`

**Description:** Get detailed information about a specific transaction

**Authentication:** Required

**Path Parameters:**
- `transaction` (UUID, required)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "transaction": "object"
  }
}
```

---

## Subscriptions

### Get Subscription Plans
**GET** `/subscriptions/plans`

**Description:** Get all active subscription plans

**Authentication:** Required

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "plans": "array"
  }
}
```

---

### Create Checkout Session
**POST** `/subscriptions/checkout`

**Description:** Create Stripe Checkout Session for subscription

**Authentication:** Required

**Request Body:**
```json
{
  "plan_id": "uuid (required)",
  "success_url": "string (optional, URL)",
  "cancel_url": "string (optional, URL)"
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "checkout_url": "string",
    "session_id": "string"
  }
}
```

---

### Create Subscription
**POST** `/subscriptions`

**Description:** Create subscription directly using payment method ID

**Authentication:** Required

**Request Body:**
```json
{
  "plan_id": "uuid (required)",
  "payment_method_id": "string (required)"
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "subscription_id": "uuid",
    "status": "string",
    "plan": "object",
    "starts_at": "datetime",
    "ends_at": "datetime|null"
  }
}
```

---

### Get Current Subscription
**GET** `/subscriptions/current`

**Description:** Get current active subscription for authenticated user

**Authentication:** Required

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "subscription": "object"
  }
}
```

---

### Cancel Subscription
**POST** `/subscriptions/cancel`

**Description:** Cancel current subscription

**Authentication:** Required

**Request Body:**
```json
{
  "immediately": "boolean (optional, default: false)"
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "string",
  "data": {
    "subscription_id": "uuid",
    "status": "string",
    "ends_at": "datetime|null"
  }
}
```

---

### Get Subscription History
**GET** `/subscriptions/history`

**Description:** Get all subscriptions (active and past) for authenticated user

**Authentication:** Required

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "subscriptions": "array"
  }
}
```

---

## Refunds

### Create Refund
**POST** `/refunds`

**Description:** Create a full or partial refund for a completed transaction

**Authentication:** Required

**Request Body:**
```json
{
  "transaction_id": "uuid (required)",
  "amount": "number (optional, min: 0.50, defaults to full refund)",
  "reason": "string (optional, enum: duplicate|fraudulent|requested_by_customer)"
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "refund": "object"
  }
}
```

---

### Get Refund Details
**GET** `/refunds/{refund}`

**Description:** Get detailed information about a specific refund

**Authentication:** Required

**Path Parameters:**
- `refund` (UUID, required)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "refund": "object"
  }
}
```

---

## Notifications

### Get Notifications
**GET** `/notifications`

**Description:** Get paginated list of notifications for authenticated user

**Authentication:** Required

**Query Parameters:**
- `per_page` (integer, optional, default: 15, max: 100)
- `unread_only` (boolean, optional, default: false)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "notifications": "array",
    "pagination": "object"
  }
}
```

---

### Mark Notification as Read
**PUT** `/notifications/{id}/read`

**Description:** Mark a specific notification as read

**Authentication:** Required

**Path Parameters:**
- `id` (UUID, required)

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Notification marked as read"
}
```

---

### Mark All Notifications as Read
**PUT** `/notifications/read-all`

**Description:** Mark all notifications as read

**Authentication:** Required

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "All notifications marked as read",
  "data": {
    "marked_count": "integer"
  }
}
```

---

### Get Unread Notification Count
**GET** `/notifications/unread-count`

**Description:** Get count of unread notifications

**Authentication:** Required

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "unread_count": "integer"
  }
}
```

---

### Delete Notification
**DELETE** `/notifications/{id}`

**Description:** Delete a specific notification

**Authentication:** Required

**Path Parameters:**
- `id` (UUID, required)

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Notification deleted successfully"
}
```

---

## Waiting List

### Validate Coupon
**POST** `/waiting-list/validate-coupon`

**Description:** Validate a coupon code for waiting list registration

**Request Body:**
```json
{
  "code": "string (required)",
  "email": "string (optional, email format)"
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "coupon": {
      "id": "uuid",
      "code": "string",
      "name": "string",
      "description": "string|null"
    }
  }
}
```

---

### Register for Waiting List
**POST** `/waiting-list/register`

**Description:** Register email and name for waiting list

**Request Body:**
```json
{
  "email": "string (required, email format)",
  "name": "string (required)",
  "phone_number": "string (required)",
  "company_name": "string (optional)",
  "selected_roles": "array (optional, enum: wholesaler|investor)",
  "coupon_code": "string (optional)"
}
```

**Response:** `201 Created`
```json
{
  "success": true,
  "message": "Successfully registered for waiting list",
  "data": {
    "entry": "object"
  }
}
```

---

### Verify Email
**POST** `/waiting-list/verify-email`

**Description:** Verify email address using token from email link

**Query Parameters:**
- `email` (string, required, email format)
- `token` (string, required)

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Email verified successfully",
  "data": {
    "entry": "object"
  }
}
```

---

### Get Waiting List Status
**GET** `/waiting-list/status`

**Description:** Get registration status by email and verification token

**Query Parameters:**
- `email` (string, required, email format)
- `token` (string, required)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "entry": "object"
  }
}
```

---

## Admin Endpoints

All admin endpoints require authentication and admin role.

**Base Path:** `/admin`

### User Management

#### List Users
**GET** `/admin/users`

**Query Parameters:**
- `page` (integer, optional)
- `per_page` (integer, optional)
- `search` (string, optional)
- `role` (string, optional)
- `status` (string, optional)

---

#### Get User
**GET** `/admin/users/{user}`

**Path Parameters:**
- `user` (UUID, required)

---

#### Update User
**PUT** `/admin/users/{user}`

**Path Parameters:**
- `user` (UUID, required)

**Request Body:**
```json
{
  "name": "string (optional)",
  "email": "string (optional)",
  "roles": "array (optional)"
}
```

---

#### Delete User
**DELETE** `/admin/users/{user}`

**Path Parameters:**
- `user` (UUID, required)

---

#### Suspend User
**POST** `/admin/users/{user}/suspend`

**Path Parameters:**
- `user` (UUID, required)

---

#### Activate User
**POST** `/admin/users/{user}/activate`

**Path Parameters:**
- `user` (UUID, required)

---

### Property Management

#### List Properties (Admin)
**GET** `/admin/properties`

**Query Parameters:**
- `page` (integer, optional)
- `per_page` (integer, optional)
- `status` (string, optional)
- `verified` (boolean, optional)

---

#### Get Property (Admin)
**GET** `/admin/properties/{property}`

**Path Parameters:**
- `property` (UUID, required)

---

#### Update Property (Admin)
**PUT** `/admin/properties/{property}`

**Path Parameters:**
- `property` (UUID, required)

---

#### Delete Property (Admin)
**DELETE** `/admin/properties/{property}`

**Path Parameters:**
- `property` (UUID, required)

---

#### Approve Property
**POST** `/admin/properties/{property}/approve`

**Path Parameters:**
- `property` (UUID, required)

---

#### Feature Property
**POST** `/admin/properties/{property}/feature`

**Path Parameters:**
- `property` (UUID, required)

---

#### Verify Property
**POST** `/admin/properties/{property}/verify`

**Path Parameters:**
- `property` (UUID, required)

---

### Analytics & Statistics

#### Get Analytics Overview
**GET** `/admin/analytics/overview`

---

#### Get User Analytics
**GET** `/admin/analytics/users`

---

#### Get Property Analytics
**GET** `/admin/analytics/properties`

---

#### Get Engagement Analytics
**GET** `/admin/analytics/engagement`

---

#### Get Trends
**GET** `/admin/analytics/trends`

---

#### Get Top Properties
**GET** `/admin/analytics/top-properties`

---

#### Get Top Wholesalers
**GET** `/admin/analytics/top-wholesalers`

---

#### Get Geographic Distribution
**GET** `/admin/analytics/geographic`

---

### Subscription Management

#### List Subscriptions
**GET** `/admin/subscriptions`

---

#### Get Subscription
**GET** `/admin/subscriptions/{subscription}`

**Path Parameters:**
- `subscription` (UUID, required)

---

#### Get Subscription Plans
**GET** `/admin/subscriptions/plans`

---

#### Get Subscription Stats
**GET** `/admin/subscriptions/stats`

---

### Transaction Management

#### List Transactions
**GET** `/admin/transactions`

**Query Parameters:**
- `page` (integer, optional)
- `per_page` (integer, optional)
- `status` (string, optional)
- `type` (string, optional)

---

#### Get Transaction Stats
**GET** `/admin/transactions/stats`

---

#### Get Transaction
**GET** `/admin/transactions/{transaction}`

**Path Parameters:**
- `transaction` (UUID, required)

---

### System Management

#### Get System Health
**GET** `/admin/system/health`

---

#### Get System Stats
**GET** `/admin/system/stats`

---

#### Get System Logs
**GET** `/admin/system/logs`

---

#### Get Queue Status
**GET** `/admin/system/queue`

---

### Waiting List Management

#### List Waiting List Entries
**GET** `/admin/waiting-list`

**Query Parameters:**
- `page` (integer, optional)
- `per_page` (integer, optional)
- `status` (string, optional)
- `email_verified` (boolean, optional)

---

#### Create Waiting List Entry
**POST** `/admin/waiting-list`

**Request Body:**
```json
{
  "email": "string (required)",
  "name": "string (required)",
  "phone_number": "string (required)",
  "company_name": "string (optional)",
  "selected_roles": "array (optional)"
}
```

---

#### Get Waiting List Stats
**GET** `/admin/waiting-list/stats`

---

#### Get Daily Signups
**GET** `/admin/waiting-list/daily-signups`

---

#### Get Geographic Distribution
**GET** `/admin/waiting-list/geographic-distribution`

---

#### Export CSV
**GET** `/admin/waiting-list/export/csv`

---

#### Export Excel
**GET** `/admin/waiting-list/export/excel`

---

#### Export PDF
**GET** `/admin/waiting-list/export/pdf`

---

#### Get Waiting List Entry
**GET** `/admin/waiting-list/{id}`

**Path Parameters:**
- `id` (UUID, required)

---

#### Update Waiting List Entry
**PUT** `/admin/waiting-list/{id}`

**Path Parameters:**
- `id` (UUID, required)

---

#### Delete Waiting List Entry
**DELETE** `/admin/waiting-list/{id}`

**Path Parameters:**
- `id` (UUID, required)

---

### Coupon Management

#### List Coupons
**GET** `/admin/coupons`

---

#### Create Coupon
**POST** `/admin/coupons`

**Request Body:**
```json
{
  "code": "string (required)",
  "name": "string (required)",
  "description": "string (optional)",
  "is_active": "boolean (optional)",
  "valid_from": "datetime (optional)",
  "valid_until": "datetime (optional)",
  "usage_limit": "integer (optional)",
  "user_limit": "integer (optional)"
}
```

---

#### Get Coupon
**GET** `/admin/coupons/{id}`

**Path Parameters:**
- `id` (UUID, required)

---

#### Update Coupon
**PUT** `/admin/coupons/{id}`

**Path Parameters:**
- `id` (UUID, required)

---

#### Delete Coupon
**DELETE** `/admin/coupons/{id}`

**Path Parameters:**
- `id` (UUID, required)

---

### Notification Management

#### Send Notification
**POST** `/admin/notifications/send`

**Request Body:**
```json
{
  "user_ids": "array (optional)",
  "role": "string (optional)",
  "title": "string (required)",
  "body": "string (required)",
  "type": "string (optional)",
  "data": "object (optional)"
}
```

---

### Email Campaign Management

#### List Email Campaigns
**GET** `/admin/email-campaigns`

---

#### Create Email Campaign
**POST** `/admin/email-campaigns`

**Request Body:**
```json
{
  "name": "string (required)",
  "subject": "string (required)",
  "body": "string (required)",
  "target_audience": "string (optional)",
  "scheduled_at": "datetime (optional)"
}
```

---

#### Get Campaign Stats
**GET** `/admin/email-campaigns/stats`

---

#### Get Email Campaign
**GET** `/admin/email-campaigns/{id}`

**Path Parameters:**
- `id` (UUID, required)

---

#### Update Email Campaign
**PUT** `/admin/email-campaigns/{id}`

**Path Parameters:**
- `id` (UUID, required)

---

#### Delete Email Campaign
**DELETE** `/admin/email-campaigns/{id}`

**Path Parameters:**
- `id` (UUID, required)

---

#### Send Email Campaign
**POST** `/admin/email-campaigns/{id}/send`

**Path Parameters:**
- `id` (UUID, required)

---

## Webhooks

### Stripe Webhook
**POST** `/webhooks/stripe`

**Description:** Handle Stripe webhook events (signature verified)

**Authentication:** Not required (signature verified)

**Note:** This endpoint is called by Stripe, not by frontend applications.

---

## Error Responses

All endpoints may return the following error responses:

### 400 Bad Request
```json
{
  "success": false,
  "message": "string",
  "errors": "object (optional)"
}
```

### 401 Unauthorized
```json
{
  "success": false,
  "message": "Unauthenticated"
}
```

### 403 Forbidden
```json
{
  "success": false,
  "message": "Unauthorized"
}
```

### 404 Not Found
```json
{
  "success": false,
  "message": "Resource not found"
}
```

### 422 Validation Error
```json
{
  "success": false,
  "message": "Validation errors",
  "errors": {
    "field": ["error message"]
  }
}
```

### 429 Too Many Requests
```json
{
  "success": false,
  "message": "Rate limit exceeded"
}
```

### 500 Internal Server Error
```json
{
  "success": false,
  "message": "Internal server error"
}
```

---

## Authentication

All protected endpoints require a Bearer token in the Authorization header:

```
Authorization: Bearer {access_token}
```

The access token is obtained from the `/login` or `/register` endpoints.

---

## Notes

- All UUIDs are in standard UUID format
- All dates are in ISO 8601 format (YYYY-MM-DDTHH:mm:ssZ)
- File uploads use `multipart/form-data` content type
- Pagination defaults: `per_page=15`, `page=1`
- Maximum file size for images: 5MB per file
- Maximum number of images per property: 10
