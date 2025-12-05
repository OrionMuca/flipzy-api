# Waiting List Frontend Implementation Guide

This guide provides complete frontend implementation requirements for the waiting list system, including all endpoints, request/response structures, and data requirements.

---

## Table of Contents

1. [Overview](#overview)
2. [Public Endpoints](#public-endpoints)
3. [Admin Endpoints](#admin-endpoints)
4. [Data Structures](#data-structures)
5. [Error Handling](#error-handling)
6. [Email Integration](#email-integration)

---

## Overview

The waiting list system allows users to:
1. Register for early access with their information
2. Verify their email address
3. Check their registration status
4. (Future) Purchase subscriptions with coupon codes

**Base URL:** `/api/v1`

**Authentication:** 
- Public endpoints: No authentication required
- Admin endpoints: Bearer token with admin role required

---

## Public Endpoints

### 1. Validate Coupon Code

**Endpoint:** `POST /api/v1/waiting-list/validate-coupon`

**Description:** Validates a coupon code before registration. Use this to show discount information to users.

**Request Requirements:**
- Method: POST
- Content-Type: application/json
- Body must contain:
  - `code` (string, required): Coupon code to validate
  - `email` (string, optional): User email for user limit checking

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "coupon": {
      "id": "uuid",
      "code": "EARLYBIRD50",
      "name": "Early Bird 50% Off",
      "description": "50% discount for early adopters"
    }
  }
}
```

**Error Responses:**

**400 - Invalid Coupon:**
```json
{
  "success": false,
  "error": "Coupon code not found"
}
```

**400 - Validation Error:**
```json
{
  "success": false,
  "errors": {
    "code": ["The code field is required."]
  }
}
```

**400 - Coupon Expired/Inactive:**
```json
{
  "success": false,
  "error": "This coupon has expired or reached its usage limit"
}
```

**400 - User Limit Reached:**
```json
{
  "success": false,
  "error": "You have already used this coupon the maximum number of times"
}
```

**Frontend Requirements:**
- Display coupon information when validation succeeds
- Show appropriate error messages for each error type
- Handle validation errors by displaying field-specific messages

---

### 2. Register for Waiting List

**Endpoint:** `POST /api/v1/waiting-list/register`

**Description:** Registers a user for the waiting list. Sends welcome email automatically.

**Request Requirements:**
- Method: POST
- Content-Type: application/json
- Body must contain:
  - `email` (string, required): Valid email format, max 255 characters
  - `name` (string, required): User full name, max 255 characters
  - `phone_number` (string, required): Phone number, max 20 characters
  - `company_name` (string, optional): Company name, max 255 characters
  - `selected_roles` (array, optional): Array of strings, each must be "wholesaler" or "investor"
  - `coupon_code` (string, optional): Coupon code, max 50 characters

**Success Response (201):**
```json
{
  "success": true,
  "message": "Successfully registered for waiting list",
  "data": {
    "id": "uuid",
    "email": "user@example.com",
    "name": "John Doe",
    "phone_number": "+1234567890",
    "company_name": "Acme Corp",
    "selected_roles": ["wholesaler", "investor"],
    "status": "pending",
    "coupon_code": "EARLYBIRD50",
    "email_verified": false,
    "email_verified_at": null,
    "account_created": false,
    "account_created_at": null,
    "created_at": "01-15-2025 10:30:00",
    "updated_at": "01-15-2025 10:30:00"
  }
}
```

**Error Responses:**

**400 - Email Already Registered:**
```json
{
  "success": false,
  "error": "This email is already registered on the waiting list"
}
```

**400 - Validation Error:**
```json
{
  "success": false,
  "errors": {
    "email": ["The email field is required."],
    "name": ["The name field is required."],
    "phone_number": ["The phone number field is required."]
  }
}
```

**Frontend Requirements:**
- Validate all required fields before submission
- Display validation errors for each field
- Show success message and redirect to status page or show verification instructions
- Handle duplicate email error appropriately

---

### 3. Verify Email Address

**Endpoint:** `POST /api/v1/waiting-list/verify-email`

**Description:** Verifies email address using token from email link. This marks the email as verified.

**Request Requirements:**
- Method: POST
- Query Parameters:
  - `email` (string, required): User's email address
  - `token` (string, required): Verification token from email link

**Success Response (200):**
```json
{
  "success": true,
  "message": "Email verified successfully",
  "data": {
    "id": "uuid",
    "email": "user@example.com",
    "name": "John Doe",
    "phone_number": "+1234567890",
    "company_name": "Acme Corp",
    "selected_roles": ["wholesaler", "investor"],
    "status": "pending",
    "coupon_code": "EARLYBIRD50",
    "email_verified": true,
    "email_verified_at": "01-15-2025 11:00:00",
    "account_created": false,
    "account_created_at": null,
    "created_at": "2025-01-15 10:30:00",
    "updated_at": "2025-01-15 11:00:00"
  }
}
```

**Error Responses:**

**404 - Entry Not Found:**
```json
{
  "success": false,
  "message": "Waiting list entry not found or invalid verification token"
}
```

**400 - Validation Error:**
```json
{
  "success": false,
  "errors": {
    "email": ["The email field is required."],
    "token": ["The token field is required."]
  }
}
```

**Frontend Requirements:**
- Extract email and token from URL query parameters
- Automatically call this endpoint when user lands on verification page
- Show success message when email is verified
- Show error message if verification fails
- Update UI to reflect verified status

---

### 4. Check Status

**Endpoint:** `GET /api/v1/waiting-list/status`

**Description:** Gets the registration status of a waiting list entry. This endpoint only retrieves status and does not verify the email.

**Request Requirements:**
- Method: GET
- Query Parameters:
  - `email` (string, required): User's email address
  - `token` (string, required): Verification token from email

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "email": "user@example.com",
    "name": "John Doe",
    "phone_number": "+1234567890",
    "company_name": "Acme Corp",
    "selected_roles": ["wholesaler", "investor"],
    "status": "pending",
    "coupon_code": "EARLYBIRD50",
    "email_verified": true,
    "email_verified_at": "01-15-2025 11:00:00",
    "account_created": false,
    "account_created_at": null,
    "created_at": "2025-01-15 10:30:00",
    "updated_at": "2025-01-15 11:00:00"
  }
}
```

**Error Responses:**

**404 - Entry Not Found:**
```json
{
  "success": false,
  "message": "Waiting list entry not found"
}
```

**400 - Validation Error:**
```json
{
  "success": false,
  "errors": {
    "email": ["The email field is required."],
    "token": ["The token field is required."]
  }
}
```

**Frontend Requirements:**
- Extract email and token from URL query parameters
- Display all status information clearly
- Show verification status prominently
- Show account creation status when available
- Handle errors appropriately

---

## Admin Endpoints

**Authentication Required:** All admin endpoints require Bearer token with admin role.

**Authorization Header:**
```http
Authorization: Bearer {admin_access_token}
```

### 1. List Waiting List Entries

**Endpoint:** `GET /api/v1/admin/waiting-list`

**Description:** Get a paginated list of all waiting list entries with filtering and search capabilities.

**Request Requirements:**
- Method: GET
- Authentication: Bearer token with admin role
- Query Parameters (all optional):
  - `status` (string): Filter by status (`pending`, `account_created`, `cancelled`)
  - `search` (string): Search in email, name, phone_number, or company_name
  - `sort_by` (string): Sort field (default: `created_at`)
  - `sort_order` (string): Sort direction (`asc` or `desc`, default: `desc`)
  - `per_page` (integer): Items per page (default: 15, max: 100)

**Success Response (200):**
```json
{
  "data": [
    {
      "id": "uuid",
      "email": "user@example.com",
      "name": "John Doe",
      "phone_number": "+1234567890",
      "company_name": "Acme Corp",
      "selected_roles": ["wholesaler", "investor"],
      "status": "pending",
      "coupon_code": "EARLYBIRD50",
      "email_verified": true,
      "email_verified_at": "01-15-2025 11:00:00",
      "account_created": false,
      "account_created_at": null,
      "metadata": {},
      "created_at": "2025-01-15 10:30:00",
      "updated_at": "2025-01-15 11:00:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 72
  }
}
```

**Error Responses:**

**403 - Forbidden:**
```json
{
  "message": "This action is unauthorized."
}
```

**Frontend Requirements:**
- Implement pagination controls using pagination object
- Provide filter UI for status
- Provide search input
- Provide sort controls
- Display all entry information in a table or list
- Handle pagination navigation

---

### 2. Get Waiting List Entry Details

**Endpoint:** `GET /api/v1/admin/waiting-list/{id}`

**Description:** Get detailed information about a specific waiting list entry.

**Request Requirements:**
- Method: GET
- Authentication: Bearer token with admin role
- Path Parameters:
  - `id` (string, required): UUID of the waiting list entry

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "email": "user@example.com",
    "name": "John Doe",
    "phone_number": "+1234567890",
    "company_name": "Acme Corp",
    "selected_roles": ["wholesaler", "investor"],
    "status": "pending",
    "coupon_code": "EARLYBIRD50",
    "email_verified": true,
    "email_verified_at": "01-15-2025 11:00:00",
    "account_created": false,
    "account_created_at": null,
    "metadata": {},
    "created_at": "2025-01-15 10:30:00",
    "updated_at": "2025-01-15 11:00:00"
  }
}
```

**Error Responses:**

**404 - Not Found:**
```json
{
  "message": "No query results for model [App\\Models\\WaitingListEntry] {id}"
}
```

**403 - Forbidden:**
```json
{
  "message": "This action is unauthorized."
}
```

**Frontend Requirements:**
- Display all entry details
- Show metadata if available
- Handle 404 errors appropriately

---

### 3. Get Waiting List Statistics

**Endpoint:** `GET /api/v1/admin/waiting-list/stats`

**Description:** Get aggregated statistics about the waiting list.

**Request Requirements:**
- Method: GET
- Authentication: Bearer token with admin role

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "total": 150,
    "by_status": {
      "pending": 120,
      "account_created": 25,
      "cancelled": 5
    },
    "with_coupons": 45,
    "by_roles": {
      "wholesaler": 60,
      "investor": 80,
      "both": 10
    }
  }
}
```

**Frontend Requirements:**
- Display statistics in a dashboard format
- Use charts or visualizations for better presentation
- Show all statistics clearly

---

### 4. List Coupons

**Endpoint:** `GET /api/v1/admin/coupons`

**Description:** Get a paginated list of all coupons.

**Request Requirements:**
- Method: GET
- Authentication: Bearer token with admin role
- Query Parameters (all optional):
  - `search` (string): Search in code or name
  - `is_active` (boolean): Filter by active status (`true` or `false`)
  - `per_page` (integer): Items per page (default: 15)

**Success Response (200):**
```json
{
  "data": [
    {
      "id": "uuid",
      "code": "EARLYBIRD50",
      "name": "Early Bird 50% Off",
      "description": "50% discount for early adopters",
      "discount_type": "percentage",
      "discount_value": 50.00,
      "minimum_amount": null,
      "maximum_discount": null,
  "valid_from": "01-01-2025 00:00:00",
  "valid_until": "12-31-2025 23:59:59",
      "usage_limit": 100,
      "usage_count": 25,
      "user_limit": 1,
      "is_active": true,
      "is_valid": true,
      "applicable_plans": null,
      "created_at": "2025-01-01 00:00:00",
      "updated_at": "2025-01-15 10:00:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 2,
    "per_page": 15,
    "total": 20
  }
}
```

**Frontend Requirements:**
- Display coupons in a table or list
- Show usage statistics (usage_count vs usage_limit)
- Indicate active/inactive status
- Show validity dates
- Implement pagination

---

### 5. Create Coupon

**Endpoint:** `POST /api/v1/admin/coupons`

**Description:** Create a new coupon code.

**Request Requirements:**
- Method: POST
- Authentication: Bearer token with admin role
- Content-Type: application/json
- Body must contain:
  - `code` (string, required): Unique coupon code
  - `name` (string, required): Display name
  - `description` (string, optional): Description
  - `discount_type` (string, required): "percentage" or "fixed_amount"
  - `discount_value` (number, required): Percentage (0-100) or fixed amount
  - `minimum_amount` (number, optional): Minimum purchase required
  - `maximum_discount` (number, optional): Max discount for percentage discounts
  - `valid_from` (string, required): ISO datetime string
  - `valid_until` (string, optional): ISO datetime string
  - `usage_limit` (integer, optional): Total uses allowed (null = unlimited)
  - `user_limit` (integer, optional): Uses per user/email (default: 1)
  - `is_active` (boolean, optional): Whether coupon is active (default: true)
  - `applicable_plans` (array, optional): Array of plan UUIDs (null = all plans)

**Success Response (201):**
```json
{
  "success": true,
  "message": "Coupon created successfully",
  "data": {
    "id": "uuid",
    "code": "EARLYBIRD50",
    "name": "Early Bird 50% Off",
    "description": "50% discount for early adopters",
    "discount_type": "percentage",
    "discount_value": 50.00,
    "minimum_amount": null,
    "maximum_discount": null,
  "valid_from": "01-01-2025 00:00:00",
  "valid_until": "12-31-2025 23:59:59",
    "usage_limit": 100,
    "usage_count": 0,
    "user_limit": 1,
    "is_active": true,
    "is_valid": true,
    "applicable_plans": null,
    "created_at": "01-15-2025 10:00:00",
    "updated_at": "01-15-2025 10:00:00"
  }
}
```

**Error Responses:**

**422 - Validation Error:**
```json
{
  "success": false,
  "errors": {
    "code": ["The code field is required."],
    "discount_type": ["The discount type must be percentage or fixed_amount."]
  }
}
```

**Frontend Requirements:**
- Provide form with all required fields
- Validate discount_type and discount_value
- Handle date inputs for valid_from and valid_until
- Show success message and redirect to coupon list
- Display validation errors

---

### 6. Update Coupon

**Endpoint:** `PUT /api/v1/admin/coupons/{id}`

**Description:** Update an existing coupon.

**Request Requirements:**
- Method: PUT
- Authentication: Bearer token with admin role
- Path Parameters:
  - `id` (string, required): UUID of the coupon
- Content-Type: application/json
- Body: Same fields as create coupon, all optional

**Success Response (200):**
```json
{
  "success": true,
  "message": "Coupon updated successfully",
  "data": {
    // Updated coupon object (same structure as create response)
  }
}
```

**Error Responses:**

**404 - Not Found:**
```json
{
  "message": "No query results for model [App\\Models\\Coupon] {id}"
}
```

**422 - Validation Error:**
```json
{
  "success": false,
  "errors": {
    "discount_type": ["The discount type must be percentage or fixed_amount."]
  }
}
```

**Frontend Requirements:**
- Pre-fill form with existing coupon data
- Allow updating any field
- Show success message on update
- Handle validation errors

---

### 7. Delete Coupon

**Endpoint:** `DELETE /api/v1/admin/coupons/{id}`

**Description:** Delete (deactivate) a coupon.

**Request Requirements:**
- Method: DELETE
- Authentication: Bearer token with admin role
- Path Parameters:
  - `id` (string, required): UUID of the coupon

**Success Response (200):**
```json
{
  "success": true,
  "message": "Coupon deleted successfully"
}
```

**Error Responses:**

**404 - Not Found:**
```json
{
  "message": "No query results for model [App\\Models\\Coupon] {id}"
}
```

**Frontend Requirements:**
- Confirm deletion before sending request
- Show success message
- Remove coupon from list or update UI
- Handle errors appropriately

---

## Data Structures

### WaitingListEntry Object

```typescript
interface WaitingListEntry {
  id: string;                    // UUID
  email: string;                 // Email address
  name: string;                  // Full name
  phone_number: string | null;  // Phone number
  company_name: string | null;  // Company name (optional)
  selected_roles: string[];      // Array of "wholesaler" or "investor"
  status: "pending" | "account_created" | "cancelled";
  coupon_code: string | null;   // Coupon code used
  email_verified: boolean;       // Whether email is verified
  email_verified_at: string | null;  // MM-DD-YYYY HH:MM:SS format
  account_created: boolean;      // Whether account was created
  account_created_at: string | null;  // MM-DD-YYYY HH:MM:SS format
  created_at: string;            // MM-DD-YYYY HH:MM:SS format
  updated_at: string;            // MM-DD-YYYY HH:MM:SS format
  metadata?: object;              // Additional data (admin only)
  coupon?: Coupon;               // Coupon object if loaded
}
```

### Coupon Object

```typescript
interface Coupon {
  id: string;                     // UUID
  code: string;                  // Coupon code (e.g., "EARLYBIRD50")
  name: string;                   // Display name
  description: string | null;      // Description
  discount_type: "percentage" | "fixed_amount";
  discount_value: number;         // Percentage (0-100) or fixed amount
  minimum_amount: number | null;  // Minimum purchase required
  maximum_discount: number | null; // Max discount for percentage
  valid_from: string;             // MM-DD-YYYY HH:MM:SS format
  valid_until: string | null;      // MM-DD-YYYY HH:MM:SS format
  usage_limit: number | null;     // Total uses allowed (null = unlimited)
  usage_count: number;            // Current usage count
  user_limit: number;             // Uses per user/email
  is_active: boolean;              // Whether coupon is active
  is_valid: boolean;               // Whether coupon is currently valid
  applicable_plans: string[] | null; // Array of plan UUIDs (null = all plans)
  created_at: string;             // MM-DD-YYYY HH:MM:SS format
  updated_at: string;             // MM-DD-YYYY HH:MM:SS format
}
```

### Pagination Object

```typescript
interface Pagination {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}
```

---

## Error Handling

### Common Error Patterns

1. **Validation Errors (400/422):**
   ```json
   {
     "success": false,
     "errors": {
       "email": ["The email field is required."],
       "name": ["The name field is required."]
     }
   }
   ```

2. **Business Logic Errors (400):**
   ```json
   {
     "success": false,
     "error": "This email is already registered on the waiting list"
   }
   ```

3. **Authentication Errors (401):**
   ```json
   {
     "message": "Unauthenticated."
   }
   ```

4. **Authorization Errors (403):**
   ```json
   {
     "message": "This action is unauthorized."
   }
   ```

5. **Not Found Errors (404):**
   ```json
   {
     "message": "No query results for model [App\\Models\\WaitingListEntry] {id}"
   }
   ```

**Frontend Requirements:**
- Handle validation errors by displaying field-specific messages
- Show business logic errors as general error messages
- Handle authentication errors by redirecting to login
- Handle authorization errors by showing appropriate message
- Handle not found errors appropriately

---

## Email Integration

### Email Links

All email links point to your frontend application. Configure the base URL in backend `.env`:

```env
FRONTEND_URL=https://your-frontend-domain.com
```

### Required Frontend Routes

1. **Email Verification:**
   ```
   /verify-email?email={email}&token={token}
   ```
   Should call `POST /api/v1/waiting-list/verify-email`

2. **Status Check:**
   ```
   /waiting-list/status?email={email}&token={token}
   ```
   Should call `GET /api/v1/waiting-list/status`

### Email Flow

1. User registers → Receives welcome email with verification link
2. User clicks verification link → Frontend verifies email via API
3. User can check status anytime using the status link from email

**Frontend Requirements:**
- Implement verification page that extracts email and token from URL
- Automatically call verify-email endpoint when page loads
- Show success/error messages appropriately
- Implement status page that displays entry information

---

## Summary

### Public Endpoints Summary

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/waiting-list/validate-coupon` | POST | No | Validate coupon code |
| `/waiting-list/register` | POST | No | Register for waiting list |
| `/waiting-list/verify-email` | POST | No | Verify email address |
| `/waiting-list/status` | GET | No | Check registration status |

### Admin Endpoints Summary

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/admin/waiting-list` | GET | Admin | List all entries |
| `/admin/waiting-list/stats` | GET | Admin | Get statistics |
| `/admin/waiting-list/{id}` | GET | Admin | Get entry details |
| `/admin/coupons` | GET | Admin | List coupons |
| `/admin/coupons` | POST | Admin | Create coupon |
| `/admin/coupons/{id}` | PUT | Admin | Update coupon |
| `/admin/coupons/{id}` | DELETE | Admin | Delete coupon |

---

**For backend implementation details, see `PROJECT_GUIDE.md`**
