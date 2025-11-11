# Flipzy Backend API

**Property Management Platform Backend** - Laravel 12 RESTful API

[![Laravel](https://img.shields.io/badge/Laravel-12-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-14+-blue.svg)](https://postgresql.org)

---

## 🚀 Quick Start

### **Prerequisites**
- PHP 8.2+
- PostgreSQL 14+
- Redis
- Composer
- Node.js & NPM

### **Installation**

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

# Ensure personal access client exists (for API token generation)
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

### **Environment Configuration**

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
```

---

## 📚 Documentation

### **API Documentation**
- **Swagger UI:** `/api/documentation` - Interactive API documentation
- **API Reference:** See `API_DOCUMENTATION.md`
- **Frontend Guide:** See `FRONTEND_IMPLEMENTATION.md`
- **System Overview:** See `SYSTEM_FUNCTIONALITY.md`

---

## 🎯 Features

### ✅ **Implemented**
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

### ⏸️ **Deferred**
- 💳 Subscription & Billing (Stripe integration)

---

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --filter=PropertyTest
php artisan test --filter=AdminTest
php artisan test --filter=MessageTest

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

---

## 🔧 Commands

### **Property Enrichment**
```bash
# Enrich all properties
php artisan properties:enrich-all

# Enrich with fresh data
php artisan properties:enrich-all --force-fresh

# Run synchronously (no queue)
php artisan properties:enrich-all --sync
```

### **Swagger Documentation**
```bash
# Generate/regenerate API docs
php artisan l5-swagger:generate
```

### **Queue Management**
```bash
# Start queue worker
php artisan queue:work

# Start Horizon (queue dashboard)
php artisan horizon
```

---

## 📊 API Endpoints

### **Authentication**
- `POST /api/v1/register` - Register user
- `POST /api/v1/login` - Login
- `GET /api/v1/user` - Get current user
- `POST /api/v1/logout` - Logout

### **Properties**
- `GET /api/v1/properties` - List properties (public)
- `GET /api/v1/properties/{id}` - Get property (public)
- `POST /api/v1/properties` - Create property (auth)
- `PUT /api/v1/properties/{id}` - Update property (owner)
- `DELETE /api/v1/properties/{id}` - Delete property (owner)
- `POST /api/v1/properties/{id}/enrich` - Enrich property data

### **Messaging**
- `GET /api/v1/conversations` - List conversations
- `POST /api/v1/conversations` - Create conversation
- `GET /api/v1/conversations/{id}/messages` - Get messages
- `POST /api/v1/conversations/{id}/messages` - Send message

### **Analytics**
- `POST /api/v1/properties/{id}/view` - Track view
- `POST /api/v1/properties/{id}/save` - Track save
- `GET /api/v1/properties/{id}/analytics` - Get analytics
- `GET /api/v1/users/{id}/credibility` - Get credibility score

### **AI Rehab Estimation**
- `POST /api/v1/properties/{id}/estimate` - Generate estimate (Premium/VIP/Admin)
- `GET /api/v1/properties/{id}/estimates` - Get estimate history

### **Admin** (Admin only)
- `GET /api/v1/admin/users` - Manage users
- `GET /api/v1/admin/properties` - Manage properties
- `GET /api/v1/admin/analytics/overview` - System overview
- `GET /api/v1/admin/system/health` - System health

**Full API documentation:** `/api/documentation`

---

## 🗄️ Database

### **Migrations**
```bash
# Run migrations
php artisan migrate

# Rollback
php artisan migrate:rollback

# Fresh migration with seeding
php artisan migrate:fresh --seed
```

### **Seeders**
```bash
# Seed all data
php artisan db:seed

# Seed specific seeder
php artisan db:seed --class=UserSeeder
```

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

### **Production Checklist**
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

### **Queue Workers**
```bash
# Production queue worker
php artisan queue:work --tries=3 --timeout=90

# Or use Horizon
php artisan horizon
```

---

## 📝 License

This project is proprietary software. All rights reserved.

---

## 🤝 Support

For issues and questions:
- Check API documentation: `/api/documentation`
- Review `SYSTEM_FUNCTIONALITY.md`
- Review `FRONTEND_IMPLEMENTATION.md`

---

**Built with ❤️ using Laravel 12**
