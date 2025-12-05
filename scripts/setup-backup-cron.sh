#!/bin/bash

# Setup automated backups with cron
# Usage: ./scripts/setup-backup-cron.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
BACKUP_SCRIPT="$SCRIPT_DIR/backup-database.sh"

echo "⏰ Setting up automated database backups..."
echo "==========================================="

# Create cron job
CRON_JOB="0 2 * * * cd $PROJECT_DIR && $BACKUP_SCRIPT full >> $PROJECT_DIR/docker/backups/backup.log 2>&1"

# Check if cron job already exists
if crontab -l 2>/dev/null | grep -q "$BACKUP_SCRIPT"; then
    echo "⚠️  Cron job already exists. Updating..."
    crontab -l 2>/dev/null | grep -v "$BACKUP_SCRIPT" | crontab -
fi

# Add new cron job
(crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -

echo "✅ Automated backup configured!"
echo ""
echo "📅 Backup schedule:"
echo "   - Full backup: Daily at 2:00 AM"
echo "   - Backup location: $PROJECT_DIR/docker/backups"
echo ""
echo "📋 Current cron jobs:"
crontab -l | grep "$BACKUP_SCRIPT" || echo "   (none found)"
echo ""
echo "💡 To view backup logs:"
echo "   tail -f $PROJECT_DIR/docker/backups/backup.log"

