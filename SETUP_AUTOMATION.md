# Automated Setup Guide

This guide explains the fully automated setup process for Development, Staging, and Production environments.

## 🚀 Quick Start

### Option 1: Automated Setup Script (Recommended)

```bash
./setup.sh
```

The script will:
- Check Docker is running
- Let you select environment (dev/staging/production)
- Create `.env` file from `.env.example`
- Build and start containers
- Automatically configure the application

### Option 2: Manual Setup

#### Development
```bash
# 1. Create .env file
cp .env.example .env
# Edit .env with your values

# 2. Start containers
docker-compose -f docker-compose.dev.yml up -d --build
```

#### Staging
```bash
# 1. Create .env file
cp .env.example .env
# Edit .env with staging values

# 2. Start containers
docker-compose -f docker-compose.staging.yml up -d --build
```

#### Production
```bash
# 1. Create .env file
cp .env.example .env
# Edit .env with production values

# 2. Start containers
docker-compose -f docker-compose.yml up -d --build
```

## 🔄 What Happens Automatically

When containers start, the startup scripts automatically:

### ✅ All Environments
- **Wait for database** to be ready
- **Generate application key** if missing
- **Install Passport keys** if missing
- **Run database migrations**
- **Create storage symlink**
- **Set proper file permissions**

### ✅ Development & Staging
- **Install Composer dependencies** if vendor doesn't exist
- **Create .env from .env.example** if missing
- **Seed database** with test data
- **Generate Swagger documentation**
- **Clear all caches** (for development)

### ✅ Production Only
- **Cache configuration** for performance
- **Cache routes** for performance
- **Cache views** for performance
- **No database seeding** (for security)

## 📋 Required Environment Variables

### Essential (Required)
```env
APP_NAME=Flipzy
APP_ENV=local|staging|production
APP_KEY=                    # Auto-generated if empty
APP_DEBUG=true|false
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=flipzy
DB_USERNAME=flipzy
DB_PASSWORD=flipzy_password

REDIS_HOST=redis
REDIS_PORT=6379
```

### External APIs (Optional but Recommended)
```env
# ATTOM Data API (Property data)
ATTOM_API_KEY=your_key_here

# OpenAI (AI Rehab Estimation)
OPENAI_API_KEY=your_key_here
OPENAI_MODEL=gpt-3.5-turbo

# Stripe (Payments)
STRIPE_SECRET_KEY=your_key_here
STRIPE_PUBLIC_KEY=your_key_here
STRIPE_WEBHOOK_SECRET=your_secret_here

# Pusher (Real-time features)
PUSHER_APP_ID=your_id_here
PUSHER_APP_KEY=your_key_here
PUSHER_APP_SECRET=your_secret_here
PUSHER_APP_CLUSTER=mt1
```

## 🎯 Environment-Specific Configuration

### Development (`docker-compose.dev.yml`)
- **Database**: `flipzy_dev`
- **Ports**: 8000 (app), 3306 (mysql), 6379 (redis)
- **Features**: Hot reload, database seeding, debug mode
- **Volumes**: Full codebase mounted for live changes

### Staging (`docker-compose.staging.yml`)
- **Database**: `flipzy_staging`
- **Ports**: 8000 (app), 3307 (mysql), 6380 (redis)
- **Features**: Database seeding, debug mode enabled
- **Volumes**: Only storage and cache directories

### Production (`docker-compose.yml`)
- **Database**: `flipzy`
- **Ports**: Configurable via environment variables
- **Features**: Optimized, cached, no seeding
- **Volumes**: Only storage and cache directories

## 🔧 Manual Configuration Steps

If you need to run commands manually:

```bash
# Access container
docker-compose -f docker-compose.dev.yml exec app sh

# Generate app key
docker-compose -f docker-compose.dev.yml exec app php artisan key:generate

# Install Passport
docker-compose -f docker-compose.dev.yml exec app php artisan passport:install

# Run migrations
docker-compose -f docker-compose.dev.yml exec app php artisan migrate

# Seed database
docker-compose -f docker-compose.dev.yml exec app php artisan db:seed

# Generate Swagger docs
docker-compose -f docker-compose.dev.yml exec app php artisan l5-swagger:generate
```

## 📊 Container Management

### View Logs
```bash
# All services
docker-compose -f docker-compose.dev.yml logs -f

# Specific service
docker-compose -f docker-compose.dev.yml logs -f app
docker-compose -f docker-compose.dev.yml logs -f mysql
docker-compose -f docker-compose.dev.yml logs -f redis
```

### Stop Containers
```bash
docker-compose -f docker-compose.dev.yml down
```

### Restart Containers
```bash
docker-compose -f docker-compose.dev.yml restart
```

### Rebuild Containers
```bash
docker-compose -f docker-compose.dev.yml up -d --build --force-recreate
```

## 🛠️ Troubleshooting

### Containers won't start
1. Check Docker is running: `docker ps`
2. Check logs: `docker-compose -f docker-compose.dev.yml logs`
3. Check ports aren't in use: `lsof -i :8000`

### Database connection errors
1. Wait for MySQL to be healthy: `docker-compose ps`
2. Check database credentials in `.env`
3. Check database container logs: `docker-compose logs mysql`

### Application key errors
- The script auto-generates keys, but if issues persist:
  ```bash
  docker-compose exec app php artisan key:generate --force
  ```

### Passport errors
- The script auto-installs Passport, but if issues persist:
  ```bash
  docker-compose exec app php artisan passport:install --force
  ```

## 🔐 Security Notes

### Development
- Debug mode enabled
- Database seeded with test data
- All caches cleared

### Staging
- Debug mode can be enabled
- Database seeded (optional)
- Caches cleared

### Production
- Debug mode **must be false**
- Database **never seeded**
- All caches enabled for performance
- Use strong passwords
- Use HTTPS
- Set proper file permissions

## 📝 Next Steps

After setup:
1. **Configure API keys** in `.env` file
2. **Access the application** at `http://localhost:8000`
3. **View API docs** at `http://localhost:8000/api/documentation`
4. **Check container status**: `docker-compose ps`

## 🎉 Success Indicators

You'll know setup is complete when:
- ✅ All containers show "Up" status
- ✅ Application responds at configured URL
- ✅ No errors in container logs
- ✅ Database migrations completed
- ✅ Passport keys installed
- ✅ Swagger documentation generated

