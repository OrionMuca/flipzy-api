# Database Backup & Data Persistence Strategy

## Data Storage Strategy

### ✅ Using Docker Volumes (Recommended)

**Yes, it's a good strategy to store the database in a container**, but with proper volume management:

1. **Docker Volumes**: Data persists even if container is removed
2. **Bind Mounts**: Data stored on host filesystem (easier backups)
3. **Named Volumes**: Managed by Docker (better performance)

### Current Setup

We use **bind mounts** for easier backup access:
- Database data: `./docker/mysql/data` (on host)
- Backups: `./docker/backups` (on host)
- Redis data: Docker volume (can be changed to bind mount)

### Why This Works

✅ **Data Persistence**: Data survives container restarts/removals  
✅ **Easy Backups**: Direct access to data files  
✅ **Portability**: Can move data between servers  
✅ **Performance**: Good for development and small-medium production  

### For Production at Scale

Consider:
- **Managed Database**: DigitalOcean Managed MySQL ($15+/month)
- **External Storage**: Network-attached storage (NFS, EBS)
- **Database Replication**: Master-slave setup for high availability

## Backup Strategy

### 1. Automated Daily Backups

```bash
# Setup automated backups
./scripts/setup-backup-cron.sh
```

**Schedule:**
- Full backup: Daily at 2:00 AM
- Retention: 30 days
- Location: `./docker/backups/`

### 2. Manual Backups

```bash
# Full backup
./scripts/backup-database.sh full

# Incremental backup (uses binlogs)
./scripts/backup-database.sh incremental
```

### 3. Backup Types

#### Full Backup
- Complete database dump
- Includes all tables, data, routines, triggers
- Compressed with gzip
- Format: `full_backup_YYYYMMDD_HHMMSS.sql.gz`

#### Incremental Backup
- Uses MySQL binary logs
- Only backs up changes since last full backup
- Faster, smaller files
- Requires full backup + all incremental backups to restore

### 4. Backup Storage Locations

**Local (Development):**
```
./docker/backups/
├── full_backup_20241201_020000.sql.gz
├── full_backup_20241202_020000.sql.gz
└── ...
```

**Production Recommendations:**
1. **Local + Remote**: Keep local + sync to cloud storage
2. **Cloud Storage**: AWS S3, DigitalOcean Spaces, Google Cloud Storage
3. **Multiple Locations**: Don't rely on single backup location

### 5. Restore Process

```bash
# Restore from backup
./scripts/restore-database.sh docker/backups/full_backup_20241201_020000.sql.gz
```

## Monitoring & Visualization Tools

### Access Management Tools

Start with profile:
```bash
docker compose --profile tools up -d
```

#### 1. phpMyAdmin (Port 8080)
- Web-based MySQL administration
- Full-featured database management
- Import/export, query execution, user management
- **Access**: http://localhost:8080

#### 2. Adminer (Port 8082)
- Lightweight alternative to phpMyAdmin
- Single PHP file, faster
- **Access**: http://localhost:8082

#### 3. Redis Commander (Port 8081)
- Redis key management
- View/edit Redis data
- Monitor Redis performance
- **Access**: http://localhost:8081

#### 4. Portainer (Port 9000)
- Docker container management UI
- Monitor containers, logs, resources
- **Access**: http://localhost:9000

### Production Security

⚠️ **Important**: These tools should NOT be exposed in production!

**Options:**
1. **VPN Access**: Only accessible via VPN
2. **SSH Tunnel**: `ssh -L 8080:localhost:8080 user@server`
3. **IP Whitelist**: Nginx with IP restrictions
4. **Authentication**: Add basic auth or OAuth

## Backup Best Practices

### 1. 3-2-1 Backup Rule
- **3** copies of data
- **2** different media types
- **1** off-site backup

### 2. Test Restores
- Regularly test backup restoration
- Verify backup integrity
- Document restore procedures

### 3. Backup Verification
```bash
# Verify backup file
gunzip -t docker/backups/full_backup_*.sql.gz

# Check backup size (should be > 0)
ls -lh docker/backups/
```

### 4. Automated Cloud Sync

Add to your backup script:
```bash
# Sync to DigitalOcean Spaces
s3cmd sync docker/backups/ s3://your-backup-bucket/ --delete-removed

# Or use rclone
rclone sync docker/backups/ remote:backups/
```

## Data Persistence Checklist

- [x] Database data stored in bind mount (`./docker/mysql/data`)
- [x] Backups stored in accessible directory
- [x] Automated daily backups configured
- [x] Backup retention policy (30 days)
- [x] Restore script tested
- [ ] Cloud backup sync configured (production)
- [ ] Backup restoration tested
- [ ] Monitoring alerts configured
- [ ] Documentation updated

## Production Recommendations

### Option 1: Keep Docker + Enhanced Backups
- Use current setup
- Add cloud backup sync
- Add monitoring alerts
- Regular backup testing

### Option 2: Managed Database Service
- DigitalOcean Managed MySQL
- Automatic backups
- Point-in-time recovery
- High availability
- **Cost**: ~$15-60/month

### Option 3: Hybrid Approach
- Development: Docker MySQL
- Production: Managed MySQL
- Easy migration path

## Quick Commands

```bash
# View backup files
ls -lh docker/backups/

# Create manual backup
./scripts/backup-database.sh full

# Restore database
./scripts/restore-database.sh docker/backups/full_backup_YYYYMMDD_HHMMSS.sql.gz

# Start management tools
docker compose --profile tools up -d

# View backup logs
tail -f docker/backups/backup.log

# Check database size
docker compose exec mysql du -sh /var/lib/mysql
```

## Troubleshooting

### Backup fails
```bash
# Check MySQL container
docker compose ps mysql

# Check disk space
df -h

# Check permissions
ls -la docker/backups/
```

### Restore fails
```bash
# Check backup file integrity
gunzip -t docker/backups/backup_file.sql.gz

# Check database connection
docker compose exec mysql mysql -u root -p
```

### Data not persisting
```bash
# Check volume mount
docker compose exec mysql ls -la /var/lib/mysql

# Check host directory
ls -la docker/mysql/data/
```

