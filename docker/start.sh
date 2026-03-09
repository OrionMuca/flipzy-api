#!/bin/sh

# Production/Staging startup script for Laravel
# Automatically configures the application on first run

set -e

ENV_TYPE=${APP_ENV:-production}
echo "=========================================="
echo "Starting Laravel application (${ENV_TYPE})"
echo "=========================================="

# Wait for database to be ready
echo "⏳ Waiting for database..."
until php -r "try { \$pdo = new PDO('mysql:host=${DB_HOST:-mysql};port=${DB_PORT:-3306};dbname=${DB_DATABASE:-flipzy}', '${DB_USERNAME:-flipzy}', '${DB_PASSWORD:-flipzy_password}'); \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); \$pdo->query('SELECT 1'); exit(0); } catch (Exception \$e) { exit(1); }" > /dev/null 2>&1; do
    echo "   Database is unavailable - sleeping"
    sleep 2
done
echo "✅ Database is ready!"

# Create .env from .env.example if .env doesn't exist (only for staging)
if [ "$ENV_TYPE" = "staging" ] && [ ! -f ".env" ]; then
    echo "📝 Creating .env file from .env.example..."
    if [ -f ".env.example" ]; then
        cp .env.example .env
        echo "✅ .env file created"
    fi
fi

# Generate application key if not set
if ! grep -q "APP_KEY=base64:" .env 2>/dev/null || grep -q "APP_KEY=$" .env 2>/dev/null; then
    echo "🔑 Generating application key..."
    php artisan key:generate --force --no-interaction || true
fi

# Generate Passport keys if they don't exist
if [ ! -f "storage/oauth-private.key" ] || [ ! -f "storage/oauth-public.key" ]; then
    echo "🔐 Generating Laravel Passport keys..."
    php artisan passport:keys --force --no-interaction || true
fi

# Ensure password grant client exists
echo "🔐 Ensuring password grant client exists..."
php artisan passport:ensure-password-grant-client --no-interaction || true

# Run migrations
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "🗄️  Running database migrations..."
    php artisan migrate --force --no-interaction || true
fi

# Seed database for staging only
if [ "$ENV_TYPE" = "staging" ] && [ "${SEED_DATABASE:-true}" = "true" ]; then
    echo "🌱 Seeding database..."
    php artisan db:seed --force --no-interaction || true
fi

# Generate Swagger documentation
if [ "${GENERATE_SWAGGER:-true}" = "true" ]; then
    echo "📚 Generating API documentation..."
    php artisan l5-swagger:generate --no-interaction || true
fi

# Optimize application (production)
if [ "$ENV_TYPE" = "production" ]; then
    echo "⚡ Optimizing application..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
    php artisan event:cache || true
else
    # Clear caches for staging
    echo "🧹 Clearing caches..."
    php artisan config:clear || true
    php artisan route:clear || true
    php artisan view:clear || true
    php artisan cache:clear || true
fi

# Create storage link if it doesn't exist
if [ ! -L "public/storage" ]; then
    echo "🔗 Creating storage symlink..."
    php artisan storage:link || true
fi

# Set permissions
echo "🔒 Setting permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

# Re-apply OAuth key permissions (in case chmod -R changed them)
if [ -f "storage/oauth-private.key" ] && [ -f "storage/oauth-public.key" ]; then
    chmod 600 storage/oauth-private.key
    chmod 600 storage/oauth-public.key
fi

# Start supervisor (manages PHP-FPM, Nginx, and workers)
echo "🚀 Starting services..."
echo "=========================================="
exec /usr/bin/supervisord -c /etc/supervisord.conf
