#!/bin/bash

# Database Backup Script for Flipzy Backend
# Creates daily backups and keeps them for 7 days
# Usage: ./scripts/backup-database.sh [full|incremental]

set -e

BACKUP_TYPE=${1:-full}
BACKUP_DIR="./docker/backups"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=7  # Keep backups for 1 week

# Load environment variables
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
fi

DB_ROOT_PASS=${DB_ROOT_PASSWORD}

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

echo "🗄️  Starting database backup ($BACKUP_TYPE)..."
echo "=============================================="

if [ "$BACKUP_TYPE" = "full" ]; then
    BACKUP_FILE="$BACKUP_DIR/full_backup_${DATE}.sql.gz"
    echo "📦 Creating full backup..."
    
    docker compose exec -T mysql mysqldump \
        -u root \
        -p"$DB_ROOT_PASS" \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        --all-databases | gzip > "$BACKUP_FILE"
    
    # Verify backup file was created and is not empty
    if [ ! -s "$BACKUP_FILE" ]; then
        echo "❌ Error: Backup file is empty or was not created!"
        exit 1
    fi
    
    # Test backup integrity
    if ! gzip -t "$BACKUP_FILE" 2>/dev/null; then
        echo "❌ Error: Backup file is corrupted!"
        exit 1
    fi
    
    echo "✅ Full backup created: $BACKUP_FILE"
    
elif [ "$BACKUP_TYPE" = "incremental" ]; then
    echo "📦 Creating incremental backup..."
    
    # Flush logs to create new binlog file
    docker compose exec -T mysql mysqladmin -u root -p"$DB_ROOT_PASS" flush-logs
    
    # Get list of binlog files
    BINLOGS=$(docker compose exec -T mysql mysqlbinlog --list-binlogs | grep -v "Log_name" | awk '{print $1}')
    
    # Backup binlog files
    for binlog in $BINLOGS; do
        if [ -f "./docker/mysql/data/$binlog" ]; then
            gzip -c "./docker/mysql/data/$binlog" > "$BACKUP_DIR/${binlog}.gz"
        fi
    done
    
    echo "✅ Incremental backup created"
    
else
    echo "❌ Invalid backup type. Use 'full' or 'incremental'"
    exit 1
fi

# Cleanup old backups (older than 7 days)
echo "🧹 Cleaning up backups older than $RETENTION_DAYS days..."
find "$BACKUP_DIR" -name "*.sql.gz" -type f -mtime +$RETENTION_DAYS -delete 2>/dev/null || true
find "$BACKUP_DIR" -name "*.binlog.gz" -type f -mtime +$RETENTION_DAYS -delete 2>/dev/null || true

echo ""
echo "📊 Backup Summary:"
if [ "$BACKUP_TYPE" = "full" ]; then
    echo "   Backup file: $BACKUP_FILE"
    echo "   Size: $(du -h "$BACKUP_FILE" | cut -f1)"
fi
echo "   Location: $BACKUP_DIR"
echo "   Retention: $RETENTION_DAYS days"
echo ""
echo "✅ Backup completed successfully!"
