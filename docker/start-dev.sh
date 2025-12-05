#!/bin/sh

# Development startup script for Laravel
# Automatically configures the application on first run

set -e

echo "=========================================="
echo "Starting Laravel application (Development)"
echo "=========================================="

# Wait for database to be ready
echo "⏳ Waiting for database..."
until php -r "try { \$pdo = new PDO('mysql:host=${DB_HOST:-mysql};port=${DB_PORT:-3306};dbname=${DB_DATABASE:-flipzy_dev}', '${DB_USERNAME:-flipzy}', '${DB_PASSWORD:-flipzy_password}'); \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); \$pdo->query('SELECT 1'); exit(0); } catch (Exception \$e) { exit(1); }" > /dev/null 2>&1; do
    echo "   Database is unavailable - sleeping"
    sleep 2
done
echo "✅ Database is ready!"

# Install dependencies if vendor doesn't exist
if [ ! -d "vendor" ]; then
    echo "📦 Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist
fi

# Create .env from .env.example if .env doesn't exist
if [ ! -f ".env" ]; then
    echo "📝 Creating .env file from .env.example..."
    if [ -f ".env.example" ]; then
        cp .env.example .env
        echo "✅ .env file created"
    else
        echo "⚠️  Warning: .env.example not found, creating basic .env..."
        cat > .env <<EOF
APP_NAME=Flipzy
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=${DB_DATABASE:-flipzy_dev}
DB_USERNAME=${DB_USERNAME:-flipzy}
DB_PASSWORD=${DB_PASSWORD:-flipzy_password}
REDIS_HOST=redis
REDIS_PORT=6379
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis
EOF
    fi
fi

# Generate application key if not set
if ! grep -q "APP_KEY=base64:" .env 2>/dev/null || grep -q "APP_KEY=$" .env 2>/dev/null; then
    echo "🔑 Generating application key..."
    php artisan key:generate --force --no-interaction || true
fi

# Install Passport keys if they don't exist
if [ ! -f "storage/oauth-private.key" ] || [ ! -f "storage/oauth-public.key" ]; then
    echo "🔐 Installing Laravel Passport keys..."
    php artisan passport:keys --force --no-interaction || true
    php artisan passport:install --force --no-interaction || true
    php artisan passport:client --personal --name="Personal Access Client" --no-interaction || true
fi

# Run migrations
echo "🗄️  Running database migrations..."
php artisan migrate --force --no-interaction || true

# Seed database for development
if [ "${SEED_DATABASE:-true}" = "true" ]; then
    echo "🌱 Seeding database..."
    php artisan db:seed --force --no-interaction || true
fi

# Generate Swagger documentation
if [ "${GENERATE_SWAGGER:-true}" = "true" ]; then
    echo "📚 Generating API documentation..."
    php artisan l5-swagger:generate --no-interaction || true
fi

# Clear caches (development - no caching)
echo "🧹 Clearing caches..."
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan cache:clear || true

# Create storage link if it doesn't exist
if [ ! -L "public/storage" ]; then
    echo "🔗 Creating storage symlink..."
    php artisan storage:link || true
fi

# Set permissions
echo "🔒 Setting permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

# Start supervisor (manages PHP-FPM, Nginx, and workers)
echo "🚀 Starting services..."
echo "=========================================="
exec /usr/bin/supervisord -c /etc/supervisord.conf
