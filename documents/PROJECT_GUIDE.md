# Flipzy Backend - Project Guide

**Property Management Platform Backend** - Laravel 12 RESTful API

[![Laravel](https://img.shields.io/badge/Laravel-12-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)

---

## 🚀 Quick Start

### Prerequisites
- PHP 8.2+
- PostgreSQL 14+ or MySQL 8+
- Redis
- Composer
- Node.js & NPM

### Installation

```bash
# Clone repository
git clone <repository-url>
cd flipzy-backend

# Install dependencies
composer install
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Install Passport
php artisan passport:install
php artisan passport:ensure-client

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Generate Swagger documentation
php artisan l5-swagger:generate

# Start development server
php artisan serve
```

### Environment Configuration

Required environment variables in `.env`:

```env
APP_NAME=Flipzy
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=flipzy
DB_USERNAME=your_username
DB_PASSWORD=your_password

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# External APIs
ATTOM_API_KEY=your_attom_key
ATTOM_API_URL=https://api.gateway.attomdata.com/propertyapi/v1.0.0

# OpenAI (for AI Rehab Estimation)
OPENAI_API_KEY=your_openai_key
OPENAI_MODEL=gpt-3.5-turbo

# Broadcasting (Pusher/Soketi)
BROADCAST_DRIVER=redis
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=mt1

# Frontend URL (for email links)
FRONTEND_URL=http://localhost:3000

# Email Configuration
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourapp.com
MAIL_FROM_NAME="${APP_NAME}"

# Stripe (for payments)
STRIPE_KEY=your_stripe_key
STRIPE_SECRET=your_stripe_secret
STRIPE_WEBHOOK_SECRET=your_webhook_secret
```

---

## 📚 API Documentation

- **Swagger UI:** `/api/documentation` - Interactive API documentation
- **Waiting List Frontend Guide:** See `WAITING_LIST_FRONTEND_GUIDE.md`

---

## 🎯 Features

### ✅ Implemented
- 🔐 OAuth2 Authentication (Laravel Passport)
- 👥 Role-based Access Control (Spatie Permissions)
- 🏠 Property CRUD Operations
- 📸 Image Management
- 🔍 Advanced Filtering & Search
- 🌐 External API Integration (ATTOM, Geocoding)
- 💬 Real-time Messaging
- 📊 Analytics & Credibility Scoring
- 🤖 AI Rehab Cost Estimation
- 👨‍💼 Admin Dashboard
- 📈 System Statistics
- 🔄 Queue Management (Laravel Horizon)
- 📋 Waiting List System with Coupons
- 💳 Stripe Payment Integration

---

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --filter=PropertyTest
php artisan test --filter=WaitingListTest
php artisan test --filter=AdminTest

# With coverage
php artisan test --coverage
```

**Test Coverage:** 120+ tests covering:
- Authentication
- Property CRUD
- Messaging
- Analytics
- Admin functionality
- AI Rehab Estimation
- Waiting List System

---

## 🔧 Commands

### Property Enrichment
```bash
# Enrich all properties
php artisan properties:enrich-all

# Enrich with fresh data
php artisan properties:enrich-all --force-fresh

# Run synchronously (no queue)
php artisan properties:enrich-all --sync
```

### Waiting List
```bash
# Create accounts from waiting list
php artisan waiting-list:create-accounts

# Dry run (preview changes)
php artisan waiting-list:create-accounts --dry-run

# Limit number of accounts
php artisan waiting-list:create-accounts --limit=10

# Create account for specific email
php artisan waiting-list:create-accounts --email=user@example.com
```

### Swagger Documentation
```bash
# Generate/regenerate API docs
php artisan l5-swagger:generate
```

### Queue Management
```bash
# Start queue worker
php artisan queue:work

# Start Horizon (queue dashboard)
php artisan horizon
```

---

## 📊 API Endpoints Overview

### Authentication
- `POST /api/v1/register` - Register user
- `POST /api/v1/login` - Login
- `GET /api/v1/user` - Get current user
- `POST /api/v1/logout` - Logout

### Properties
- `GET /api/v1/properties` - List properties (public)
- `GET /api/v1/properties/{id}` - Get property (public)
- `POST /api/v1/properties` - Create property (auth)
- `PUT /api/v1/properties/{id}` - Update property (owner)
- `DELETE /api/v1/properties/{id}` - Delete property (owner)
- `POST /api/v1/properties/{id}/enrich` - Enrich property data

### Waiting List (Public)
- `POST /api/v1/waiting-list/validate-coupon` - Validate coupon code
- `POST /api/v1/waiting-list/register` - Register for waiting list
- `POST /api/v1/waiting-list/verify-email` - Verify email address
- `GET /api/v1/waiting-list/status` - Get waiting list status

### Waiting List (Admin)
- `GET /api/v1/admin/waiting-list` - List all entries
- `GET /api/v1/admin/waiting-list/stats` - Get statistics
- `GET /api/v1/admin/waiting-list/{id}` - Get entry details
- `GET /api/v1/admin/coupons` - List coupons
- `POST /api/v1/admin/coupons` - Create coupon
- `PUT /api/v1/admin/coupons/{id}` - Update coupon
- `DELETE /api/v1/admin/coupons/{id}` - Delete coupon

### Messaging
- `GET /api/v1/conversations` - List conversations
- `POST /api/v1/conversations` - Create conversation
- `GET /api/v1/conversations/{id}/messages` - Get messages
- `POST /api/v1/conversations/{id}/messages` - Send message

### Analytics
- `POST /api/v1/properties/{id}/view` - Track view
- `POST /api/v1/properties/{id}/save` - Track save
- `GET /api/v1/properties/{id}/analytics` - Get analytics
- `GET /api/v1/users/{id}/credibility` - Get credibility score

### AI Rehab Estimation
- `POST /api/v1/properties/{id}/estimate` - Generate estimate (Premium/VIP/Admin)
- `GET /api/v1/properties/{id}/estimates` - Get estimate history

### Admin (Admin only)
- `GET /api/v1/admin/users` - Manage users
- `GET /api/v1/admin/properties` - Manage properties
- `GET /api/v1/admin/analytics/overview` - System overview
- `GET /api/v1/admin/system/health` - System health

**Full API documentation:** `/api/documentation`

---

## 🔐 Authentication

All protected endpoints require a Bearer token:

```http
Authorization: Bearer {access_token}
```

**Token Expiration:**
- Access Token: 15 days
- Refresh Token: 30 days

---

## 📦 Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/          # Admin controllers
│   │   ├── AuthController.php
│   │   ├── PropertyController.php
│   │   ├── WaitingListController.php
│   │   └── ...
│   ├── Middleware/
│   ├── Requests/           # Form requests
│   └── Resources/           # API resources
├── Models/                  # Eloquent models
├── Services/                # Business logic
├── Jobs/                    # Queue jobs
└── Events/                  # Event classes

routes/
├── api.php                  # API routes
├── channels.php             # Broadcasting channels
└── web.php                  # Web routes

database/
├── migrations/              # Database migrations
├── seeders/                 # Database seeders
└── factories/               # Model factories

tests/
├── Feature/                 # Feature tests
└── Unit/                    # Unit tests
```

---

## 🚀 Deployment

### Production Checklist
- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Generate application key
- [ ] Run migrations
- [ ] Install Passport keys
- [ ] Configure Redis
- [ ] Set up queue workers
- [ ] Configure Horizon
- [ ] Set up SSL certificates
- [ ] Configure CORS
- [ ] Set up monitoring
- [ ] Configure email service
- [ ] Set up Stripe webhooks

### Queue Workers
```bash
# Production queue worker
php artisan queue:work --tries=3 --timeout=90

# Or use Horizon
php artisan horizon
```

### Docker Deployment

See `docker-compose.yml` for Docker setup. Use `deploy.sh` script for automated deployment.

```bash
# Build and start containers
docker compose build
docker compose up -d

# Run migrations
docker compose exec app php artisan migrate --force

# Cache configuration
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
```

---

## 🗄️ Database

### Migrations
```bash
# Run migrations
php artisan migrate

# Rollback
php artisan migrate:rollback

# Fresh migration with seeding
php artisan migrate:fresh --seed
```

### Seeders
```bash
# Seed all data
php artisan db:seed

# Seed specific seeder
php artisan db:seed --class=UserSeeder
```

#### Test Users (Created by UserSeeder)

After running `php artisan db:seed`, the following test users are available:

**Admin User:**
- Email: `admin@flipzy.com`
- Password: `password`
- Role: `admin`
- Email Verified: ✅ Yes

**Wholesaler Users:**
- Email: `wholesaler@flipzy.com` | Password: `password`
- Email: `sarah@flipzy.com` | Password: `password`
- Role: `wholesaler`
- Email Verified: ✅ Yes

**Investor Users:**
- Email: `investor@flipzy.com` | Password: `password`
- Email: `emma@flipzy.com` | Password: `password`
- Email: `david@flipzy.com` | Password: `password`
- Role: `investor`
- Email Verified: ✅ Yes

> **⚠️ Security Note:** These are development/test credentials. Change all passwords before deploying to production!

---

## 📝 Key Features Details

### Waiting List System
- Users can register for early access
- Coupon code validation and application
- Email verification system
- Stripe payment integration
- Automatic account creation when ready
- See `WAITING_LIST_FRONTEND_GUIDE.md` for frontend implementation

### Property Management
- CRUD operations for properties
- Image upload and management
- Property enrichment via ATTOM API
- Advanced filtering and search
- Analytics tracking

### Messaging System
- Real-time messaging between users
- Conversation management
- Read receipts
- Unread message counts

### Analytics
- Property view tracking
- Save tracking
- Credibility scoring
- Admin analytics dashboard

---

## 🔧 Troubleshooting

### Common Issues

**Migration Errors:**
```bash
php artisan migrate:fresh
```

**Permission Issues:**
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

**Queue Not Processing:**
```bash
php artisan queue:work
# Or use Horizon
php artisan horizon
```

**Cache Issues:**
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

---

## 📝 License

This project is proprietary software. All rights reserved.

---

## 🤝 Support

For issues and questions:
- Check API documentation: `/api/documentation`
- Review `WAITING_LIST_FRONTEND_GUIDE.md` for waiting list frontend implementation
- Check Laravel logs: `storage/logs/laravel.log`

---

**Built with ❤️ using Laravel 12**

---

# API Standards & Requirements

## API Response Consistency

**CRITICAL:** All API endpoints MUST follow consistent response formats based on their operation type. This ensures predictable frontend integration and better developer experience.

### CREATE Endpoints (POST)

**Standard Response Format:**
```json
{
  "success": true,
  "message": "Resource created successfully",
  "data": {
    // Created resource object
  }
}
```

**HTTP Status Code:** `201 Created`

**Examples:**
- `POST /api/v1/waiting-list/register`
- `POST /api/v1/admin/coupons`
- `POST /api/v1/properties`
- `POST /api/v1/conversations`

**Requirements:**
- Always include `success: true`
- Always include a descriptive `message` field
- Always include the created resource in `data` field
- Use HTTP status code 201
- Return the complete resource object (not just ID)

---

### INDEX Endpoints (GET - List)

**Standard Response Format:**
```json
{
  "data": [
    // Array of resource objects
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 72
  }
}
```

**HTTP Status Code:** `200 OK`

**Examples:**
- `GET /api/v1/admin/waiting-list`
- `GET /api/v1/admin/coupons`
- `GET /api/v1/properties`
- `GET /api/v1/conversations`

**Requirements:**
- Always include `data` array containing resource objects
- Always include `pagination` object with pagination metadata
- Use HTTP status code 200
- Pagination object must include: `current_page`, `last_page`, `per_page`, `total`
- For non-paginated lists, still include pagination object with all items counted

---

### SHOW Endpoints (GET - Single Resource)

**Standard Response Format:**
```json
{
  "success": true,
  "data": {
    // Resource object
  }
}
```

**HTTP Status Code:** `200 OK`

**Examples:**
- `GET /api/v1/admin/waiting-list/{id}`
- `GET /api/v1/admin/coupons/{id}`
- `GET /api/v1/properties/{id}`
- `GET /api/v1/conversations/{id}`

**Requirements:**
- Always include `success: true`
- Always include the resource in `data` field
- Use HTTP status code 200
- Return complete resource object with all relationships loaded if needed

---

### UPDATE Endpoints (PUT/PATCH)

**Standard Response Format:**
```json
{
  "success": true,
  "message": "Resource updated successfully",
  "data": {
    // Updated resource object
  }
}
```

**HTTP Status Code:** `200 OK`

**Examples:**
- `PUT /api/v1/admin/coupons/{id}`
- `PUT /api/v1/properties/{id}`
- `PATCH /api/v1/users/{id}`

**Requirements:**
- Always include `success: true`
- Always include a descriptive `message` field
- Always include the updated resource in `data` field
- Use HTTP status code 200
- Return the complete updated resource object

---

### DELETE Endpoints (DELETE)

**Standard Response Format:**
```json
{
  "success": true,
  "message": "Resource deleted successfully"
}
```

**HTTP Status Code:** `200 OK`

**Examples:**
- `DELETE /api/v1/admin/coupons/{id}`
- `DELETE /api/v1/properties/{id}`
- `DELETE /api/v1/users/{id}`

**Requirements:**
- Always include `success: true`
- Always include a descriptive `message` field
- Do NOT include `data` field (resource is deleted)
- Use HTTP status code 200

---

## Error Response Consistency

**CRITICAL:** All error responses MUST follow consistent formats based on error type.

### Validation Errors (400/422)

**Standard Response Format:**
```json
{
  "success": false,
  "errors": {
    "field_name": ["Error message 1", "Error message 2"],
    "another_field": ["Error message"]
  }
}
```

**HTTP Status Code:** `400 Bad Request` or `422 Unprocessable Entity`

**Requirements:**
- Always include `success: false`
- Always include `errors` object with field names as keys
- Each field error is an array of strings (multiple validation rules can fail)
- Use 400 for general validation errors
- Use 422 for form validation errors (Laravel standard)

---

### Business Logic Errors (400)

**Standard Response Format:**
```json
{
  "success": false,
  "error": "Descriptive error message"
}
```

**HTTP Status Code:** `400 Bad Request`

**Examples:**
- "This email is already registered on the waiting list"
- "Coupon code not found"
- "Insufficient permissions"

**Requirements:**
- Always include `success: false`
- Always include `error` field with descriptive message
- Use HTTP status code 400
- Do NOT include `errors` field (use `error` singular)

---

### Authentication Errors (401)

**Standard Response Format:**
```json
{
  "message": "Unauthenticated."
}
```

**HTTP Status Code:** `401 Unauthorized`

**Requirements:**
- Use Laravel's default authentication error format
- Use HTTP status code 401
- Message should be clear and consistent

---

### Authorization Errors (403)

**Standard Response Format:**
```json
{
  "message": "This action is unauthorized."
}
```

**HTTP Status Code:** `403 Forbidden`

**Requirements:**
- Use Laravel's default authorization error format
- Use HTTP status code 403
- Message should be clear and consistent

---

### Not Found Errors (404)

**Standard Response Format:**
```json
{
  "message": "No query results for model [App\\Models\\ResourceName] {id}"
}
```

**HTTP Status Code:** `404 Not Found`

**Requirements:**
- Use Laravel's default model not found error format
- Use HTTP status code 404
- Message should include model name and identifier

---

## Additional Requirements

### Date/Time Format

**Standard:** All date and datetime fields MUST be returned in US format (MM-DD-YYYY).

**Format:**
- **Date only:** `MM-DD-YYYY` (e.g., `"01-15-2025"`)
- **DateTime:** `MM-DD-YYYY HH:MM:SS` (e.g., `"01-15-2025 10:30:00"`)

**Examples:**
- `"01-15-2025 10:30:00"` (datetime with time)
- `"01-15-2025"` (date only)

**Requirements:**
- Consistent format across all endpoints
- Use US date format (MM-DD-YYYY) for all date/datetime fields
- DateTime fields must include time in 24-hour format (HH:MM:SS)
- Date-only fields (without time) use MM-DD-YYYY format
- All timestamps are stored in UTC but displayed in MM-DD-YYYY format

---

### UUID Format

**Standard:** All IDs MUST be UUIDs (v4).

**Format:** `xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx`

**Requirements:**
- Use UUID v4 format
- Always return as string
- Validate UUID format in all endpoints accepting IDs

---

### Pagination

**Standard:** All paginated endpoints MUST include pagination metadata.

**Format:**
```json
{
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 72
  }
}
```

**Requirements:**
- Always include pagination object
- Default `per_page`: 15
- Maximum `per_page`: 100
- Always include all four fields: `current_page`, `last_page`, `per_page`, `total`

---

### Nullable Fields

**Standard:** Nullable fields MUST be returned as `null` (not omitted).

**Requirements:**
- Always include nullable fields in response
- Use `null` value, not empty string or empty array
- Document nullable fields in API documentation

---

### Resource Relationships

**Standard:** Related resources SHOULD be loaded when needed, but not always.

**Requirements:**
- Use Laravel Resource classes for consistent formatting
- Load relationships explicitly when needed
- Document which relationships are included in responses
- Use `whenLoaded()` in Resource classes to conditionally include relationships

---

## Code Standards

### Controller Response Methods

**Standard:** Use consistent response methods in controllers.

**Examples:**
```php
// CREATE
return response()->json([
    'success' => true,
    'message' => 'Resource created successfully',
    'data' => new ResourceResource($resource),
], 201);

// INDEX
return ResourceResource::collection($resources)->additional([
    'pagination' => [
        'current_page' => $resources->currentPage(),
        'last_page' => $resources->lastPage(),
        'per_page' => $resources->perPage(),
        'total' => $resources->total(),
    ],
]);

// SHOW
return response()->json([
    'success' => true,
    'data' => new ResourceResource($resource),
]);

// UPDATE
return response()->json([
    'success' => true,
    'message' => 'Resource updated successfully',
    'data' => new ResourceResource($resource),
]);

// DELETE
return response()->json([
    'success' => true,
    'message' => 'Resource deleted successfully',
]);
```

---

## Testing Requirements

**Standard:** All endpoints MUST have tests that verify response format consistency.

**Requirements:**
- Test success responses match standard format
- Test error responses match standard format
- Test pagination structure
- Test nullable fields return null
- Test authentication/authorization errors

---

## Documentation Requirements

**Standard:** All endpoints MUST be documented with OpenAPI/Swagger annotations.

**Requirements:**
- Document request/response formats
- Include all possible error responses
- Document query parameters
- Document authentication requirements
- Keep documentation up to date

---

## Versioning

**Standard:** All API endpoints MUST be versioned.

**Format:** `/api/v1/...`

**Requirements:**
- Use version prefix in all routes
- Maintain backward compatibility within version
- Document breaking changes
- Plan for future versions

---

## Summary Checklist

When creating or updating endpoints, verify:

- [ ] Response format matches operation type (CREATE/INDEX/SHOW/UPDATE/DELETE)
- [ ] HTTP status code is correct
- [ ] Error responses follow standard format
- [ ] Pagination included for list endpoints
- [ ] Datetime fields use ISO format
- [ ] UUIDs are properly formatted
- [ ] Nullable fields return null (not omitted)
- [ ] Tests verify response format
- [ ] OpenAPI documentation is updated
- [ ] Version prefix is included in route

---

**Last Updated:** 2025-01-15

**Note:** This document should be updated as new requirements are identified or standards evolve.

