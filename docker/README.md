# Docker Deployment Guide

This directory contains Docker configuration files for deploying the Flipzy backend.

## Quick Start

### Production Deployment

1. **Create `.env` file** with your production environment variables:
```bash
cp .env.example .env
# Edit .env with your production values
```

2. **Build and start containers:**
```bash
docker-compose up -d --build
```

3. **Run migrations:**
```bash
docker-compose exec app php artisan migrate --force
```

4. **Generate application key (if needed):**
```bash
docker-compose exec app php artisan key:generate
```

5. **Create storage link:**
```bash
docker-compose exec app php artisan storage:link
```

### Development

1. **Start development environment:**
```bash
docker-compose -f docker-compose.dev.yml up -d --build
```

2. **Install dependencies:**
```bash
docker-compose -f docker-compose.dev.yml exec app composer install
docker-compose -f docker-compose.dev.yml exec app npm install
```

3. **Run migrations:**
```bash
docker-compose -f docker-compose.dev.yml exec app php artisan migrate
```

## Services

- **app**: Laravel application (PHP-FPM + Nginx)
- **mysql**: MySQL database
- **redis**: Redis for caching and queues

## Useful Commands

### View logs
```bash
docker-compose logs -f app
docker-compose logs -f mysql
docker-compose logs -f redis
```

### Execute artisan commands
```bash
docker-compose exec app php artisan [command]
```

### Access container shell
```bash
docker-compose exec app sh
```

### Stop containers
```bash
docker-compose down
```

### Rebuild containers
```bash
docker-compose up -d --build --force-recreate
```

### Clear cache
```bash
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
```

## Environment Variables

Key environment variables to set in `.env`:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://yourdomain.com`
- `DB_CONNECTION=mysql`
- `DB_HOST=mysql`
- `DB_DATABASE=flipzy`
- `DB_USERNAME=flipzy`
- `DB_PASSWORD=your_secure_password`
- `REDIS_HOST=redis`
- `QUEUE_CONNECTION=redis`

## DigitalOcean Deployment

### Option 1: Docker on Droplet

1. Create a Droplet (Ubuntu 22.04)
2. Install Docker and Docker Compose
3. Clone your repository
4. Copy `.env` file
5. Run `docker-compose up -d --build`

### Option 2: App Platform

1. Connect your GitHub repository
2. Select "Docker" as build method
3. Use `Dockerfile` for production
4. Configure environment variables
5. Deploy!

## Troubleshooting

### Permission issues
```bash
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
docker-compose exec app chmod -R 775 storage bootstrap/cache
```

### Database connection issues
```bash
# Check if database is running
docker-compose ps mysql

# Test connection
docker-compose exec app php artisan db:monitor
```

### Queue workers not running
```bash
# Check supervisor status
docker-compose exec app supervisorctl status

# Restart workers
docker-compose exec app supervisorctl restart laravel-worker:*
```

