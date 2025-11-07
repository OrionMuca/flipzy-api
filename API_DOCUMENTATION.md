# Flipzy API Documentation

**Base URL:** `/api/v1`  
**Authentication:** Bearer Token (OAuth2 via Laravel Passport)  
**API Documentation:** Available at `/api/documentation` (Swagger UI)

---

## 🔐 Authentication

All protected endpoints require a Bearer token in the Authorization header:
```
Authorization: Bearer {your_access_token}
```

### Register
```http
POST /api/v1/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "investor"
}
```

### Login
```http
POST /api/v1/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password123"
}
```

### Get User Info
```http
GET /api/v1/user
Authorization: Bearer {token}
```

### Logout
```http
POST /api/v1/logout
Authorization: Bearer {token}
```

---

## 🏠 Properties

### List Properties (Public)
```http
GET /api/v1/properties?city=Denver&state=CO&min_price=100000&max_price=500000
```

**Query Parameters:**
- `city` - Filter by city
- `state` - Filter by state
- `property_type` - Filter by type (house, condo, townhouse, etc.)
- `status` - Filter by status (active, pending, sold)
- `min_price` - Minimum asking price
- `max_price` - Maximum asking price
- `bedrooms` - Number of bedrooms
- `bathrooms` - Number of bathrooms
- `search` - Search in title, address, description
- `per_page` - Items per page (default: 15)

### Get Property Details (Public)
```http
GET /api/v1/properties/{id}
```

### Create Property (Auth Required - Wholesaler)
```http
POST /api/v1/properties
Authorization: Bearer {token}
Content-Type: multipart/form-data

{
  "title": "Beautiful House",
  "description": "Great investment opportunity",
  "property_type": "house",
  "address": "123 Main St",
  "city": "Denver",
  "state": "CO",
  "zip_code": "80202",
  "bedrooms": 3,
  "bathrooms": 2,
  "square_feet": 2000,
  "asking_price": 250000,
  "arv": 350000,
  "images": [file1, file2, ...],
  "primary_image_index": 0
}
```

### Update Property (Auth Required - Owner Only)
```http
PUT /api/v1/properties/{id}
Authorization: Bearer {token}
```

### Delete Property (Auth Required - Owner Only)
```http
DELETE /api/v1/properties/{id}
Authorization: Bearer {token}
```

### Enrich Property Data
```http
POST /api/v1/properties/{id}/enrich?sync=true&force_fresh=true
Authorization: Bearer {token}
```

**Query Parameters:**
- `sync` - Run synchronously (default: false, uses queue)
- `force_fresh` - Force fresh data from APIs (default: false)

---

## 💬 Messaging

### List Conversations
```http
GET /api/v1/conversations
Authorization: Bearer {token}
```

### Get Conversation
```http
GET /api/v1/conversations/{id}
Authorization: Bearer {token}
```

### Create Conversation
```http
POST /api/v1/conversations
Authorization: Bearer {token}
Content-Type: application/json

{
  "property_id": "uuid",
  "participant_two_id": "uuid"
}
```

### Get Messages
```http
GET /api/v1/conversations/{id}/messages
Authorization: Bearer {token}
```

### Send Message
```http
POST /api/v1/conversations/{id}/messages
Authorization: Bearer {token}
Content-Type: application/json

{
  "body": "I'm interested in this property"
}
```

### Mark Messages as Read
```http
PUT /api/v1/conversations/{id}/messages/read
Authorization: Bearer {token}
```

### Get Unread Count
```http
GET /api/v1/messages/unread-count
Authorization: Bearer {token}
```

---

## 📊 Analytics

### Track Property View
```http
POST /api/v1/properties/{id}/view
Authorization: Bearer {token}
```

### Track Property Save
```http
POST /api/v1/properties/{id}/save
Authorization: Bearer {token}
Content-Type: application/json

{
  "notes": "Potential flip",
  "list_name": "Favorites"
}
```

### Track Property Inquiry
```http
POST /api/v1/properties/{id}/inquiry
Authorization: Bearer {token}
Content-Type: application/json

{
  "message": "I'm interested in this property"
}
```

### Get Property Analytics
```http
GET /api/v1/properties/{id}/analytics?days=30
Authorization: Bearer {token}
```

### Get Credibility Score
```http
GET /api/v1/users/{id}/credibility?days=90
Authorization: Bearer {token}
```

### Get My Analytics (Wholesaler)
```http
GET /api/v1/analytics/my-analytics?days=30
Authorization: Bearer {token}
```

---

## 🤖 AI Rehab Estimation

### Generate Estimate (Premium/VIP/Admin)
```http
POST /api/v1/properties/{id}/estimate?force_refresh=false
Authorization: Bearer {token}
```

**Query Parameters:**
- `force_refresh` - Force new estimate (default: false, uses cache)
- `model` - OpenAI model (default: gpt-3.5-turbo)

### Get Estimate History
```http
GET /api/v1/properties/{id}/estimates?limit=10
Authorization: Bearer {token}
```

### Get Specific Estimate
```http
GET /api/v1/estimates/{id}
Authorization: Bearer {token}
```

---

## 👨‍💼 Admin Endpoints

**All admin endpoints require admin role and Bearer token**

### User Management
- `GET /api/v1/admin/users` - List users (filters: role, search)
- `GET /api/v1/admin/users/{id}` - Get user details
- `PUT /api/v1/admin/users/{id}` - Update user
- `DELETE /api/v1/admin/users/{id}` - Delete user
- `POST /api/v1/admin/users/{id}/suspend` - Suspend user
- `POST /api/v1/admin/users/{id}/activate` - Activate user

### Property Management
- `GET /api/v1/admin/properties` - List properties (filters: status, wholesaler, city, state)
- `GET /api/v1/admin/properties/{id}` - Get property details
- `PUT /api/v1/admin/properties/{id}` - Update property
- `DELETE /api/v1/admin/properties/{id}` - Delete property
- `POST /api/v1/admin/properties/{id}/approve` - Approve property
- `POST /api/v1/admin/properties/{id}/feature` - Feature/unfeature property
- `POST /api/v1/admin/properties/{id}/verify` - Verify property

### Statistics
- `GET /api/v1/admin/analytics/overview` - System overview
- `GET /api/v1/admin/analytics/users?days=30` - User statistics
- `GET /api/v1/admin/analytics/properties?days=30` - Property statistics
- `GET /api/v1/admin/analytics/engagement?days=30` - Engagement statistics
- `GET /api/v1/admin/analytics/trends?days=30` - Trends over time
- `GET /api/v1/admin/analytics/top-properties?limit=10&days=30` - Top properties
- `GET /api/v1/admin/analytics/top-wholesalers?limit=10` - Top wholesalers
- `GET /api/v1/admin/analytics/geographic` - Geographic distribution

### System Management
- `GET /api/v1/admin/system/health` - System health check
- `GET /api/v1/admin/system/stats` - System statistics
- `GET /api/v1/admin/system/logs` - Recent error logs
- `GET /api/v1/admin/system/queue` - Queue statistics

### Subscriptions
- `GET /api/v1/admin/subscriptions` - List subscriptions
- `GET /api/v1/admin/subscriptions/{id}` - Get subscription details
- `GET /api/v1/admin/subscriptions/plans` - List plans
- `GET /api/v1/admin/subscriptions/stats` - Subscription statistics

---

## 📝 Response Format

All responses follow this format:

**Success:**
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

**Error:**
```json
{
  "success": false,
  "message": "Error message",
  "errors": { ... }
}
```

---

## 🔗 Swagger UI

Interactive API documentation is available at:
```
http://your-domain.com/api/documentation
```

You can test all endpoints directly from the Swagger UI interface.

---

## 📚 Additional Resources

- **Frontend Implementation Guide:** See `FRONTEND_IMPLEMENTATION.md`
- **System Functionality:** See `SYSTEM_FUNCTIONALITY.md`

