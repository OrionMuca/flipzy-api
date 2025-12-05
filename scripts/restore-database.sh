#!/bin/bash

# Database Restore Script for Flipzy Backend
# Usage: ./scripts/restore-database.sh <backup_file.sql.gz>

set -e

if [ -z "$1" ]; then
    echo "❌ Error: Backup file required"
    echo "Usage: ./scripts/restore-database.sh <backup_file.sql.gz>"
    exit 1
fi

BACKUP_FILE=$1

if [ ! -f "$BACKUP_FILE" ]; then
    echo "❌ Error: Backup file not found: $BACKUP_FILE"
    exit 1
fi

# Load environment variables
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
fi

DB_ROOT_PASS=${DB_ROOT_PASSWORD:-root_password}

echo "🔄 Restoring database from backup..."
echo "===================================="
echo "Backup file: $BACKUP_FILE"
echo ""
read -p "⚠️  This will overwrite the current database. Continue? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo "❌ Restore cancelled"
    exit 1
fi

echo "📦 Restoring database..."

# Decompress and restore
gunzip -c "$BACKUP_FILE" | docker compose exec -T mysql mysql -u root -p"$DB_ROOT_PASS"

echo "✅ Database restored successfully!"
echo ""
echo "💡 Tip: You may need to run migrations:"
echo "   docker compose exec app php artisan migrate --force"

