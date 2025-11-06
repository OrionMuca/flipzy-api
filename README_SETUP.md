# Flipzy Backend Setup Guide

## Prerequisites

- PHP 8.2+
- Composer
- PostgreSQL 15+ (or SQLite for development)
- Redis 7+ (for cache and queues)
- Node.js & NPM (for frontend assets)

## Installation Steps

### 1. Install Dependencies

```bash
composer install
npm install
```

### 2. Environment Configuration

Copy `.env.example` to `.env`:

```bash
cp .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

### 3. Database Setup

**For PostgreSQL (Production):**

Update `.env`:
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=flipzy
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

**For SQLite (Development):**

The default `.env` uses SQLite. Ensure the database file exists:
```bash
touch database/database.sqlite
```

### 4. Run Migrations

```bash
php artisan migrate
```

### 5. Install Passport OAuth2

Generate encryption keys for Passport:

```bash
php artisan passport:install
```

This creates OAuth2 client credentials. Note the client ID and secret for your frontend application.

### 6. Seed Database

Seed roles, permissions, and test users:

```bash
php artisan db:seed
```

This creates:
- **Roles:** admin, investor, wholesaler
- **Permissions:** Full permission set
- **Test Users:**
  - admin@flipzy.com (admin role)
  - wholesaler@flipzy.com (wholesaler role)
  - investor@flipzy.com (investor role)

Default password: `password` (from factory)

### 7. Configure Redis (Optional but Recommended)

Update `.env`:
```
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### 8. External API Configuration

Add your API keys to `.env`:

```env
# ATTOM Data API
ATTOM_API_KEY=your_attom_key
ATTOM_API_URL=https://api.gateway.attomdata.com

# Estated API
ESTATED_API_KEY=your_estated_key
ESTATED_API_URL=https://apis.estated.com

# Mapbox (for geocoding)
MAPBOX_ACCESS_TOKEN=your_mapbox_token
GEO_SERVICE=mapbox

# OpenAI (for rehab estimation)
OPENAI_API_KEY=your_openai_key
OPENAI_MODEL=gpt-4

# Pusher (for real-time messaging)
PUSHER_APP_ID=your_pusher_id
PUSHER_APP_KEY=your_pusher_key
PUSHER_APP_SECRET=your_pusher_secret
PUSHER_APP_CLUSTER=mt1
BROADCAST_DRIVER=pusher
```

### 9. Start Development Server

```bash
php artisan serve
```

Or use the included dev script:

```bash
composer run dev
```

This starts:
- Laravel server (port 8000)
- Queue worker
- Log viewer
- Vite dev server

## API Authentication

### Register a User

```bash
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

Roles: `investor`, `wholesaler`

### Login

```bash
POST /api/v1/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password123"
}
```

Response:
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "roles": [...]
    },
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "Bearer"
  }
}
```

### Using the Token

Include the token in the Authorization header:

```
Authorization: Bearer {access_token}
```

### Get Authenticated User

```bash
GET /api/v1/user
Authorization: Bearer {access_token}
```

### Logout

```bash
POST /api/v1/logout
Authorization: Bearer {access_token}
```

## Roles & Permissions

### Roles

- **admin:** Full system access
- **investor:** Can view properties, send messages, view analytics
- **wholesaler:** Can create/manage properties, send messages, view analytics

### Permissions

Use Spatie's permission system:

```php
// Check if user has permission
$user->can('properties.create');

// Check if user has role
$user->hasRole('wholesaler');

// Assign role
$user->assignRole('wholesaler');

// Give permission
$user->givePermissionTo('properties.create');
```

### Middleware Usage

In routes:
```php
Route::middleware(['auth:api', 'role:wholesaler'])->group(function () {
    Route::post('/properties', [PropertyController::class, 'store']);
});

Route::middleware(['auth:api', 'permission:properties.view'])->group(function () {
    Route::get('/properties', [PropertyController::class, 'index']);
});
```

## Next Steps

1. **Phase 2:** Set up database schema for properties, messages, analytics
2. **Phase 3:** Implement property CRUD operations
3. **Phase 4:** Integrate external APIs (ATTOM, Estated)
4. **Phase 5:** Build real-time messaging system
5. **Phase 6:** Implement analytics and credibility scoring

See `DEVELOPMENT_STRATEGY.md` for the complete roadmap.

## Troubleshooting

### Passport Keys Not Found

Run:
```bash
php artisan passport:keys
```

### Permission Cache Issues

Clear cache:
```bash
php artisan permission:cache-reset
```

### Database Connection Issues

Check your `.env` database configuration and ensure PostgreSQL is running:

```bash
# PostgreSQL
psql -U postgres -c "CREATE DATABASE flipzy;"
```

### Redis Connection Issues

Ensure Redis is running:

```bash
redis-cli ping
# Should return: PONG
```

