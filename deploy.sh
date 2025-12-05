#!/bin/bash

# Deployment script for Flipzy Backend
# Usage: ./deploy.sh [production|development]

set -e

ENVIRONMENT=${1:-production}
COMPOSE_FILE="docker-compose.yml"

if [ "$ENVIRONMENT" = "development" ]; then
    COMPOSE_FILE="docker-compose.dev.yml"
fi

echo "🚀 Deploying Flipzy Backend ($ENVIRONMENT mode)"
echo "=============================================="

# Check if .env exists
if [ ! -f .env ]; then
    echo "❌ .env file not found!"
    echo "Please create .env file from .env.example"
    exit 1
fi

# Build and start containers
echo "📦 Building Docker images..."
docker compose -f $COMPOSE_FILE build

echo "🚀 Starting containers..."
docker compose -f $COMPOSE_FILE up -d

# Wait for services to be ready
echo "⏳ Waiting for services to be ready..."
sleep 10

# Check if database is ready
echo "🔍 Checking database connection..."
until docker compose -f $COMPOSE_FILE exec -T app php -r "try { \$pdo = new PDO('mysql:host=mysql;port=3306;dbname=${DB_DATABASE:-flipzy}', '${DB_USERNAME:-flipzy}', '${DB_PASSWORD:-flipzy_password}'); \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); \$pdo->query('SELECT 1'); exit(0); } catch (Exception \$e) { exit(1); }" > /dev/null 2>&1; do
    echo "   Database not ready yet, waiting..."
    sleep 2
done
echo "✅ Database is ready!"

# Run Laravel setup commands
echo "🔧 Setting up Laravel..."

# Generate key if needed
if ! docker compose -f $COMPOSE_FILE exec -T app php artisan key:generate --show > /dev/null 2>&1; then
    echo "   Generating application key..."
    docker compose -f $COMPOSE_FILE exec -T app php artisan key:generate --force || true
fi

# Run migrations
echo "   Running migrations..."
docker compose -f $COMPOSE_FILE exec -T app php artisan migrate --force --no-interaction || true

# Create storage link
echo "   Creating storage link..."
docker compose -f $COMPOSE_FILE exec -T app php artisan storage:link || true

# Cache configuration (production only)
if [ "$ENVIRONMENT" = "production" ]; then
    echo "   Caching configuration..."
    docker compose -f $COMPOSE_FILE exec -T app php artisan config:cache || true
    docker compose -f $COMPOSE_FILE exec -T app php artisan route:cache || true
    docker compose -f $COMPOSE_FILE exec -T app php artisan view:cache || true
else
    echo "   Clearing caches (development mode)..."
    docker compose -f $COMPOSE_FILE exec -T app php artisan config:clear || true
    docker compose -f $COMPOSE_FILE exec -T app php artisan route:clear || true
    docker compose -f $COMPOSE_FILE exec -T app php artisan view:clear || true
fi

# Set permissions
echo "   Setting permissions..."
docker compose -f $COMPOSE_FILE exec -T app chown -R www-data:www-data storage bootstrap/cache || true
docker compose -f $COMPOSE_FILE exec -T app chmod -R 775 storage bootstrap/cache || true

echo ""
echo "✅ Deployment complete!"
echo ""
echo "📊 Container status:"
docker compose -f $COMPOSE_FILE ps

echo ""
echo "🌐 Application should be available at:"
if [ "$ENVIRONMENT" = "production" ]; then
    echo "   http://localhost:${APP_PORT:-8000}"
else
    echo "   http://localhost:8000"
fi

echo ""
echo "📝 Useful commands:"
echo "   View logs:        docker compose -f $COMPOSE_FILE logs -f"
echo "   Stop containers:  docker compose -f $COMPOSE_FILE down"
echo "   Restart:          docker compose -f $COMPOSE_FILE restart"
echo ""

