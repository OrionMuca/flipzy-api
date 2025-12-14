# DigitalOcean Volume Setup Guide

This guide explains how to use your DigitalOcean 100GB volume for database storage and visualize your data.

## Current Setup vs DigitalOcean Volume

### Current Setup (Docker Local Volumes)
- **Location**: `/var/lib/docker/volumes/` on your Droplet
- **Storage**: Uses Droplet's local disk space
- **Backup**: Manual backups only
- **Persistence**: Data persists as long as the Droplet exists

### DigitalOcean Volume (Recommended)
- **Location**: Separate block storage volume (100GB)
- **Storage**: Dedicated volume, separate from Droplet
- **Backup**: Can be snapshotted easily
- **Persistence**: Survives Droplet recreation
- **Performance**: Better I/O performance

## Step 1: Attach DigitalOcean Volume to Your Droplet

### In DigitalOcean Dashboard:
1. Go to **Volumes** → Select your 100GB volume
2. Click **More** → **Configure**
3. Attach to your Droplet (`flipzy-staging`)
4. Note the device name (usually `/dev/sda` or `/dev/sdb`)

### On Your Server:
```bash
# Check if volume is attached
lsblk

# You should see your volume (e.g., /dev/sda or /dev/sdb)
# Format it if needed (WARNING: This erases data!)
# sudo mkfs.ext4 /dev/sda

# Create mount point
sudo mkdir -p /mnt/digitalocean-volume

# Mount the volume
sudo mount /dev/sda /mnt/digitalocean-volume

# Check mount
df -h | grep digitalocean

# Make it permanent (add to /etc/fstab)
echo '/dev/sda /mnt/digitalocean-volume ext4 defaults,nofail 0 2' | sudo tee -a /etc/fstab
```

## Step 2: Configure Docker to Use DigitalOcean Volume

### Option A: Use Bind Mount (Simple)

Update `docker-compose.staging.yml`:

```yaml
mysql:
  volumes:
    - /mnt/digitalocean-volume/mysql:/var/lib/mysql  # Use DO volume
    - ./docker/backups:/backups
    - ./docker/mysql/conf.d:/etc/mysql/conf.d
```

### Option B: Use Docker Volume Driver (Advanced)

Create a Docker volume that uses the DigitalOcean volume:

```bash
# Create directory structure
sudo mkdir -p /mnt/digitalocean-volume/docker-volumes

# Create Docker volume pointing to DO volume
docker volume create \
  --driver local \
  --opt type=none \
  --opt device=/mnt/digitalocean-volume/mysql \
  --opt o=bind \
  mysql_staging_data_do
```

Then update `docker-compose.staging.yml`:

```yaml
volumes:
  mysql_staging_data:
    external: true
    name: mysql_staging_data_do
```

## Step 3: Migrate Existing Data (If Needed)

```bash
# Stop containers
cd /var/www/flipzy-backend
docker-compose -f docker-compose.staging.yml down

# Create directory on DO volume
sudo mkdir -p /mnt/digitalocean-volume/mysql

# Copy existing data (if you have any)
sudo docker run --rm \
  -v flipzy-backend_mysql_staging_data:/source \
  -v /mnt/digitalocean-volume/mysql:/dest \
  alpine sh -c "cp -a /source/. /dest/"

# Update docker-compose.staging.yml to use new path
# Then start containers
docker-compose -f docker-compose.staging.yml up -d
```

## Step 4: Verify Volume Usage

```bash
# Check volume usage
df -h /mnt/digitalocean-volume

# Check MySQL data size
du -sh /mnt/digitalocean-volume/mysql

# Check what's using space
du -h --max-depth=1 /mnt/digitalocean-volume | sort -hr
```

## Database Visualization Tools

### 1. phpMyAdmin (Web Interface) - Already Added!

**Access:**
- URL: `http://your-server-ip:8080`
- Username: `flipzy` (or your DB_USERNAME)
- Password: `flipzy_password` (or your DB_PASSWORD)

**Features:**
- Visual table browser
- SQL query interface
- Data export/import
- Table structure viewer
- Relationship diagrams

**Start phpMyAdmin:**
```bash
cd /var/www/flipzy-backend
docker-compose -f docker-compose.staging.yml up -d phpmyadmin
```

### 2. Adminer (Lightweight Alternative)

Add to `docker-compose.staging.yml`:

```yaml
adminer:
  image: adminer:latest
  container_name: flipzy-adminer-staging
  restart: unless-stopped
  ports:
    - "8082:8080"
  environment:
    - ADMINER_DEFAULT_SERVER=mysql
  depends_on:
    - mysql
  networks:
    - flipzy-network
```

Access at: `http://your-server-ip:8082`

### 3. MySQL Workbench (Desktop Client)

**Connect from your local machine:**
- Host: `your-server-ip`
- Port: `3307` (as configured in docker-compose.staging.yml)
- Username: `flipzy`
- Password: `flipzy_password`
- Database: `flipzy_staging`

### 4. TablePlus / DBeaver (Desktop Clients)

Same connection details as MySQL Workbench.

## Volume Structure Overview

```
/mnt/digitalocean-volume/
├── mysql/                    # MySQL database files
│   ├── flipzy_staging/      # Your database
│   ├── mysql/               # MySQL system tables
│   ├── performance_schema/ # Performance data
│   └── ...
├── redis/                   # Redis data (optional)
└── backups/                 # Database backups
```

## Monitoring Volume Usage

### Check Space Usage:
```bash
# Overall volume usage
df -h /mnt/digitalocean-volume

# Detailed breakdown
du -h --max-depth=2 /mnt/digitalocean-volume | sort -hr | head -20

# MySQL specific
du -sh /mnt/digitalocean-volume/mysql/*
```

### Database Size Query:
```sql
-- Run in phpMyAdmin or MySQL client
SELECT 
    table_schema AS 'Database',
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.TABLES
WHERE table_schema = 'flipzy_staging'
GROUP BY table_schema;

-- Per table breakdown
SELECT 
    table_name AS 'Table',
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)',
    table_rows AS 'Rows'
FROM information_schema.TABLES
WHERE table_schema = 'flipzy_staging'
ORDER BY (data_length + index_length) DESC;
```

## Backup Strategy

### Automated Backups to DigitalOcean Volume:

```bash
# Create backup script
cat > /var/www/flipzy-backend/backup-db.sh << 'EOF'
#!/bin/bash
BACKUP_DIR="/mnt/digitalocean-volume/backups"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR

docker-compose -f docker-compose.staging.yml exec -T mysql \
  mysqldump -u root -proot_password flipzy_staging > \
  $BACKUP_DIR/flipzy_staging_$DATE.sql

# Keep only last 7 days
find $BACKUP_DIR -name "flipzy_staging_*.sql" -mtime +7 -delete

echo "Backup completed: $BACKUP_DIR/flipzy_staging_$DATE.sql"
EOF

chmod +x /var/www/flipzy-backend/backup-db.sh

# Add to crontab (daily at 2 AM)
crontab -e
# Add: 0 2 * * * /var/www/flipzy-backend/backup-db.sh
```

## Quick Reference Commands

```bash
# Check volume status
df -h /mnt/digitalocean-volume

# Check MySQL data location
docker volume inspect flipzy-backend_mysql_staging_data

# Access phpMyAdmin
# http://your-server-ip:8080

# Connect via MySQL client
docker-compose -f docker-compose.staging.yml exec mysql mysql -u flipzy -pflipzy_password flipzy_staging

# View all tables
docker-compose -f docker-compose.staging.yml exec mysql mysql -u flipzy -pflipzy_password flipzy_staging -e "SHOW TABLES;"

# Check database size
docker-compose -f docker-compose.staging.yml exec mysql mysql -u flipzy -pflipzy_password -e "
SELECT 
    table_schema AS 'Database',
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.TABLES
WHERE table_schema = 'flipzy_staging';"
```

## Security Notes

1. **phpMyAdmin Access**: Consider restricting access via firewall:
   ```bash
   # Only allow your IP
   sudo ufw allow from YOUR_IP to any port 8080
   ```

2. **SSH Tunnel** (More Secure):
   ```bash
   # From your local machine
   ssh -L 8080:localhost:8080 root@your-server-ip
   # Then access phpMyAdmin at http://localhost:8080
   ```

3. **Change Default Ports**: Update `PHPMYADMIN_PORT` in `.env` to a non-standard port.

