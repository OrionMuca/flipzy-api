# DigitalOcean Volume Setup Guide

## Overview

This guide configures your **100GB DigitalOcean volume** to store **only images** (user uploads, property photos, etc.). MySQL data will remain on your **32GB local disk** using Docker volumes.

## Storage Strategy

### 100GB DigitalOcean Volume (`/mnt/volume_atl1_01`)
- ✅ **Images & User Uploads** → `/mnt/volume_atl1_01/storage/app/public`
- ✅ **Private Files** → `/mnt/volume_atl1_01/storage/app/private`

### 32GB Local Disk
- ✅ **MySQL Database** → Docker volume (managed automatically)
- ✅ **Redis Data** → Docker volume (managed automatically)
- ✅ **Application Logs** → `./storage/logs`
- ✅ **Framework Cache** → `./storage/framework`
- ✅ **Bootstrap Cache** → `./bootstrap/cache`

## Setup Steps

### Step 1: Create Directory Structure on Volume

**On your server:**

```bash
# Create directories for images only
sudo mkdir -p /mnt/volume_atl1_01/storage/app/public
sudo mkdir -p /mnt/volume_atl1_01/storage/app/private

# Set proper ownership
sudo chown -R $USER:$USER /mnt/volume_atl1_01/storage

# Set permissions
sudo chmod -R 755 /mnt/volume_atl1_01/storage
```

### Step 2: Migrate Existing Images (If Any)

**On your server:**

```bash
cd /var/www/flipzy-backend

# Stop containers
docker-compose -f docker-compose.staging.yml down

# Migrate existing images if they exist
if [ -d "storage/app/public" ] && [ "$(ls -A storage/app/public 2>/dev/null)" ]; then
    echo "Migrating existing images..."
    sudo cp -a storage/app/public/* /mnt/volume_atl1_01/storage/app/public/ 2>/dev/null || true
    sudo cp -a storage/app/private/* /mnt/volume_atl1_01/storage/app/private/ 2>/dev/null || true
    sudo chown -R $USER:$USER /mnt/volume_atl1_01/storage
    echo "✅ Images migrated"
else
    echo "No existing images to migrate"
fi
```

### Step 3: Update docker-compose.staging.yml

**Pull the updated file:**

```bash
cd /var/www/flipzy-backend

# Pull updated file
git pull origin staging

# Or copy from local machine
# scp docker-compose.staging.yml root@your-server-ip:/var/www/flipzy-backend/
```

### Step 4: Start Containers

**On your server:**

```bash
cd /var/www/flipzy-backend

# Start containers with new volume mounts
docker-compose -f docker-compose.staging.yml up -d

# Wait for services
sleep 15

# Fix permissions inside container
docker-compose -f docker-compose.staging.yml exec app chown -R www-data:www-data /var/www/html/storage || true
docker-compose -f docker-compose.staging.yml exec app chmod -R 775 /var/www/html/storage/app || true
```

### Step 5: Verify Everything

**On your server:**

```bash
# Check volume usage
df -h /mnt/volume_atl1_01

# Check images directory
ls -la /mnt/volume_atl1_01/storage/app/public/

# Test creating a file
docker-compose -f docker-compose.staging.yml exec app touch /var/www/html/storage/app/public/test.txt
ls -la /mnt/volume_atl1_01/storage/app/public/test.txt

# Verify MySQL is using local Docker volume
docker volume inspect flipzy-backend_mysql_staging_data
```

## Complete Setup Script

**Run this all-in-one script:**

```bash
cat > /tmp/setup-volume.sh << 'EOF'
#!/bin/bash
set -e

cd /var/www/flipzy-backend

echo "=========================================="
echo "Setting up DigitalOcean Volume for Images"
echo "=========================================="

# 1. Create directories
echo "1. Creating directories..."
sudo mkdir -p /mnt/volume_atl1_01/storage/app/{public,private}

# 2. Set ownership
echo "2. Setting ownership..."
sudo chown -R $USER:$USER /mnt/volume_atl1_01/storage

# 3. Set permissions
echo "3. Setting permissions..."
sudo chmod -R 755 /mnt/volume_atl1_01/storage

# 4. Stop containers
echo "4. Stopping containers..."
docker-compose -f docker-compose.staging.yml down

# 5. Migrate images (if exist)
echo "5. Migrating images..."
if [ -d "storage/app/public" ] && [ "$(ls -A storage/app/public 2>/dev/null)" ]; then
    echo "   Copying images..."
    sudo cp -a storage/app/public/* /mnt/volume_atl1_01/storage/app/public/ 2>/dev/null || true
    sudo cp -a storage/app/private/* /mnt/volume_atl1_01/storage/app/private/ 2>/dev/null || true
    sudo chown -R $USER:$USER /mnt/volume_atl1_01/storage
    echo "   ✅ Images migrated"
else
    echo "   ℹ️  No existing images to migrate"
fi

# 6. Start containers
echo "6. Starting containers..."
docker-compose -f docker-compose.staging.yml up -d

# 7. Wait for services
echo "7. Waiting for services..."
sleep 15

# 8. Fix permissions
echo "8. Fixing container permissions..."
docker-compose -f docker-compose.staging.yml exec app chown -R www-data:www-data /var/www/html/storage || true
docker-compose -f docker-compose.staging.yml exec app chmod -R 775 /var/www/html/storage/app || true

# 9. Verify
echo "9. Verifying setup..."
echo ""
echo "Volume usage:"
df -h /mnt/volume_atl1_01
echo ""
echo "Storage directories:"
ls -la /mnt/volume_atl1_01/storage/app/public/ | head -5
echo ""
echo "MySQL volume (should be on local disk):"
docker volume inspect flipzy-backend_mysql_staging_data | grep Mountpoint
echo ""
echo "✅ Setup complete!"
echo ""
echo "Images will now be stored at: /mnt/volume_atl1_01/storage/app/public"
echo "MySQL data is stored on local disk via Docker volume"
EOF

chmod +x /tmp/setup-volume.sh
/tmp/setup-volume.sh
```

## Storage Breakdown

### What's on the 100GB Volume:
- **Images**: Property photos, user avatars, document uploads
- **Public Files**: Any files served publicly via Laravel's storage link

### What's on the 32GB Local Disk:
- **MySQL Database**: ~1-5GB typically (grows with data)
- **Redis Data**: ~100-500MB (in-memory cache, persisted)
- **Application Logs**: ~100MB-1GB (rotates automatically)
- **Framework Cache**: ~50-200MB (cleared on deploy)
- **Bootstrap Cache**: ~10-50MB (cleared on deploy)
- **System Files**: ~5-10GB (OS, Docker, etc.)

**Total Estimated Usage**: ~10-20GB on local disk, leaving plenty of room for growth.

## Monitoring Storage

### Check Volume Usage

```bash
# Check 100GB volume usage
df -h /mnt/volume_atl1_01

# Check local disk usage
df -h /

# Check Docker volumes (MySQL, Redis)
docker system df -v
```

### Check Image Storage Size

```bash
# Check total size of images
du -sh /mnt/volume_atl1_01/storage/app/public

# Count files
find /mnt/volume_atl1_01/storage/app/public -type f | wc -l

# List largest files
find /mnt/volume_atl1_01/storage/app/public -type f -exec du -h {} + | sort -rh | head -20
```

### Check MySQL Size

```bash
# Check MySQL Docker volume size
docker volume inspect flipzy-backend_mysql_staging_data | grep Mountpoint
VOLUME_PATH=$(docker volume inspect flipzy-backend_mysql_staging_data | grep Mountpoint | cut -d'"' -f4)
du -sh $VOLUME_PATH
```

## Troubleshooting

### Permission Issues

```bash
# Fix volume permissions
sudo chown -R $USER:$USER /mnt/volume_atl1_01/storage
sudo chmod -R 755 /mnt/volume_atl1_01/storage

# Fix container permissions
docker-compose -f docker-compose.staging.yml exec app chown -R www-data:www-data /var/www/html/storage
docker-compose -f docker-compose.staging.yml exec app chmod -R 775 /var/www/html/storage/app
```

### Volume Not Mounted

```bash
# Check if volume is mounted
mount | grep volume_atl1_01

# Check if directory exists
ls -la /mnt/volume_atl1_01/storage/app/

# Check container mounts
docker-compose -f docker-compose.staging.yml exec app ls -la /var/www/html/storage/app/
```

### Low Disk Space on Local Disk

```bash
# Clean Docker system
docker system prune -a --volumes

# Clean old logs
docker-compose -f docker-compose.staging.yml exec app php artisan log:clear

# Check what's using space
du -sh /var/lib/docker/volumes/*
```

## Backup Strategy

### Backup Images

```bash
# Backup images to local disk
tar -czf /tmp/images-backup-$(date +%Y%m%d).tar.gz /mnt/volume_atl1_01/storage/app/

# Or sync to another location
rsync -av /mnt/volume_atl1_01/storage/app/ /backup/location/images/
```

### Backup MySQL

```bash
# Backup MySQL database
docker-compose -f docker-compose.staging.yml exec mysql mysqldump -u root -proot_password flipzy_staging > /tmp/mysql-backup-$(date +%Y%m%d).sql

# Or use Laravel's backup command
docker-compose -f docker-compose.staging.yml exec app php artisan backup:run
```

## Summary

✅ **100GB Volume**: Only for images and user uploads  
✅ **32GB Local Disk**: MySQL, Redis, logs, and cache  
✅ **Better Separation**: Images can grow independently  
✅ **Easier Management**: Database backups stay local  

This setup maximizes your 100GB volume for the content that needs it most (images) while keeping database operations fast on local disk.
