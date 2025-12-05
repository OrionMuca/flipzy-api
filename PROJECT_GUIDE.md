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

