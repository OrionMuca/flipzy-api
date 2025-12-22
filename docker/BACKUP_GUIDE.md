# Database Backup Guide

## Overview

Simple database backup system that creates daily backups and keeps them for 7 days.

## Backup Script

### `backup-database.sh`

**Features:**
- ✅ Full database backups
- ✅ Incremental backups (binlog)
- ✅ Backup verification & integrity checks
- ✅ Automatic cleanup (7 days retention)
- ✅ Compressed backups (gzip)

**Usage:**
```bash
# Full backup
./scripts/backup-database.sh full

# Incremental backup
./scripts/backup-database.sh incremental
```

**Backup Location:**
- All backups are stored in: `./docker/backups/`
- Format: `full_backup_YYYYMMDD_HHMMSS.sql.gz`

## Setup Automated Backups

### Quick Setup

```bash
# Setup daily automated backups (runs at 2:00 AM)
./scripts/setup-backup-cron.sh
```

This creates a cron job that runs daily at 2:00 AM and keeps backups for 7 days.

## Backup Retention Policy

- **Retention:** 7 days
- **Location:** `./docker/backups/`
- **Automatic cleanup:** Backups older than 7 days are automatically deleted

## Backup Location

All backups are stored in:
```
./docker/backups/
├── full_backup_20250115_020000.sql.gz
├── full_backup_20250116_020000.sql.gz
├── full_backup_20250117_020000.sql.gz
└── ...
```

## Restoring from Backup

```bash
./scripts/restore-database.sh ./docker/backups/full_backup_20250115_020000.sql.gz
```

## Best Practices

### 1. Access Control

✅ **Secure backup directory:**
```bash
# Set proper permissions
chmod 700 ./docker/backups
chown root:root ./docker/backups
```

### 2. Backup Verification

✅ **Test backups regularly:**
```bash
# Verify backup integrity
gzip -t ./docker/backups/full_backup_*.sql.gz

# Test restore on staging server
./scripts/restore-database.sh backup_file.sql.gz
```

### 3. Manual Off-Site Backup (Optional)

✅ **Copy backups to external location:**
```bash
# Copy to external drive
cp ./docker/backups/*.sql.gz /mnt/external-drive/

# Or sync to cloud storage
aws s3 sync ./docker/backups s3://your-bucket/backups/
```

## Automated Backup Setup

### Using Cron (Recommended)

```bash
# Setup automated daily backups
./scripts/setup-backup-cron.sh
```

**Manual cron setup:**
```bash
# Edit crontab
crontab -e

# Add daily backup at 2 AM
0 2 * * * cd /var/www/flipzy-backend && ./scripts/backup-database.sh full >> ./docker/backups/backup.log 2>&1
```

### Using Systemd Timer (Alternative)

Create `/etc/systemd/system/flipzy-backup.service`:
```ini
[Unit]
Description=Flipzy Database Backup
After=network.target

[Service]
Type=oneshot
User=root
WorkingDirectory=/var/www/flipzy-backend
ExecStart=/var/www/flipzy-backend/scripts/backup-database.sh full
```

Create `/etc/systemd/system/flipzy-backup.timer`:
```ini
[Unit]
Description=Run Flipzy Backup Daily
Requires=flipzy-backup.service

[Timer]
OnCalendar=daily
OnCalendar=02:00
Persistent=true

[Install]
WantedBy=timers.target
```

Enable:
```bash
sudo systemctl enable flipzy-backup.timer
sudo systemctl start flipzy-backup.timer
```

## Monitoring Backups

### Check Backup Status

```bash
# View backup log
tail -f ./docker/backups/backup.log

# List recent backups
ls -lh ./docker/backups/ | tail -10

# Check backup sizes
du -sh ./docker/backups/*
```

### Backup Health Check Script

Create `scripts/check-backups.sh`:
```bash
#!/bin/bash
# Check if backups are recent and valid

BACKUP_DIR="./docker/backups/daily"
LATEST_BACKUP=$(ls -t $BACKUP_DIR/*.sql.gz 2>/dev/null | head -1)

if [ -z "$LATEST_BACKUP" ]; then
    echo "❌ No backups found!"
    exit 1
fi

# Check if backup is less than 25 hours old
if [ $(find "$LATEST_BACKUP" -mtime -1) ]; then
    echo "✅ Latest backup is recent: $LATEST_BACKUP"
else
    echo "⚠️  Warning: Latest backup is older than 24 hours: $LATEST_BACKUP"
fi

# Verify backup integrity
if gzip -t "$LATEST_BACKUP" 2>/dev/null; then
    echo "✅ Backup integrity check passed"
else
    echo "❌ Backup integrity check failed!"
    exit 1
fi
```

## Cloud Backup Options

### AWS S3

```bash
# Install AWS CLI
apt-get install awscli

# Configure
aws configure

# Sync backups
aws s3 sync ./docker/backups s3://your-bucket/flipzy-backups/ --delete
```

### Google Cloud Storage

```bash
# Install gsutil
# Sync backups
gsutil -m rsync -r ./docker/backups gs://your-bucket/flipzy-backups/
```

### DigitalOcean Spaces

```bash
# Install s3cmd
apt-get install s3cmd

# Configure
s3cmd --configure

# Sync backups
s3cmd sync ./docker/backups/ s3://your-space/flipzy-backups/
```

## Disaster Recovery Plan

### 1. Regular Backups
- ✅ Daily automated backups
- ✅ Weekly full backups
- ✅ Monthly archives

### 2. Off-Site Storage
- ✅ Remote server backup
- ✅ Cloud storage backup

### 3. Testing
- ✅ Monthly restore test on staging
- ✅ Verify backup integrity weekly

### 4. Documentation
- ✅ Document restore procedures
- ✅ Keep backup passwords secure
- ✅ Document backup locations

## Troubleshooting

### Backup Fails

```bash
# Check MySQL container is running
docker compose ps mysql

# Check disk space
df -h

# Check backup directory permissions
ls -la ./docker/backups/
```

### Restore Fails

```bash
# Verify backup file
gzip -t backup_file.sql.gz

# Check MySQL has space
docker compose exec mysql df -h /var/lib/mysql

# Check MySQL logs
docker compose logs mysql | tail -50
```

## Summary

✅ **Current Setup:**
- Simple backup script (`backup-database.sh`)
- Daily automated backups
- 7-day retention (automatic cleanup)
- Backup location: `./docker/backups/`

✅ **Quick Start:**
1. Setup automated backups: `./scripts/setup-backup-cron.sh`
2. Test manual backup: `./scripts/backup-database.sh full`
3. Check backups: `ls -lh ./docker/backups/`

