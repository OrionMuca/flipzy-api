# Resource Allocation Guide - 8GB RAM Server

## Overview

This guide shows how resources are allocated on an 8GB RAM server running both frontend and backend applications.

## Total Memory: 8GB (8192MB)

### Recommended Allocation (with proper headroom)

| Component | Memory Allocation | Percentage | Notes |
|-----------|------------------|------------|-------|
| **System/OS** | ~1.5GB | 18.75% | Operating system, kernel, system processes (MUST leave free) |
| **Frontend App** | ~800MB-1GB | 10-12.5% | Node.js/Next.js frontend application |
| **Backend (Laravel)** | ~1.2GB | 15% | PHP-FPM, Nginx, Horizon workers (limit: 1.2GB) |
| **MySQL Database** | ~2.5GB | 31.25% | InnoDB buffer pool (1.5GB) + connections/buffers (limit: 2.5GB) |
| **Redis** | ~400MB | 5% | Caching and queue storage (limit: 500MB) |
| **Free Buffer** | ~1.5GB | 18.75% | Headroom for traffic spikes, OS caching, temporary processes |

**Total Allocated: ~5.5GB (68.75%)**  
**Free for System: ~2.5GB (31.25%)**

## Detailed Breakdown

### 1. MySQL Database (2.5GB total)

**Configuration in `docker/mysql/conf.d/custom.cnf`:**

```ini
innodb_buffer_pool_size = 1.5G    # Main memory cache (18.75% of total RAM)
tmp_table_size = 64M              # Temporary tables
max_heap_table_size = 64M         # In-memory tables
max_connections = 200             # Concurrent connections
```

**Memory Usage:**
- InnoDB Buffer Pool: 1.5GB (main cache)
- Connection buffers: ~200MB (200 connections × ~1MB)
- Query buffers: ~200MB (sort, read, join buffers)
- Binary logs: ~100MB
- MySQL overhead: ~500MB
- **Total: ~2GB reserved, 2.5GB limit**

### 2. Backend Application (1.2GB total)

**Components:**
- PHP-FPM workers: ~300MB (3-5 workers × ~60MB)
- Laravel Horizon: ~640MB (5 workers × ~128MB per worker)
- Nginx: ~50MB
- Laravel base: ~200MB
- PHP opcache: ~100MB
- **Total: ~1GB reserved, 1.2GB limit**

**Horizon Configuration:**
- `maxProcesses: 5` (optimized for 8GB server with headroom)
- `memory: 128MB` per worker
- Total Horizon memory: ~640MB (5 workers × 128MB)

### 3. Redis (400MB)

**Configuration:**
```yaml
maxmemory: 400mb
maxmemory-policy: allkeys-lru
```

**Usage:**
- Cache storage: ~300MB
- Queue storage: ~80MB
- Overhead: ~20MB
- **Total: ~400MB reserved, 500MB limit**

### 4. Frontend Application (~1GB)

**Typical Usage:**
- Node.js runtime: ~200-300MB
- Application code: ~200-300MB
- Build cache: ~200-300MB
- Static assets: ~100-200MB
- **Total: ~1GB**

## Docker Memory Limits

Memory limits are set in `docker-compose.yml`:

```yaml
mysql:
  deploy:
    resources:
      limits:
        memory: 2.5G  # Reduced from 3G to leave headroom
      reservations:
        memory: 2G

app:
  deploy:
    resources:
      limits:
        memory: 1.2G  # Reduced from 1.5G to leave headroom
      reservations:
        memory: 800M

redis:
  deploy:
    resources:
      limits:
        memory: 500M  # Reduced from 600M to leave headroom
      reservations:
        memory: 400M
```

## Monitoring Memory Usage

### Check Current Usage

```bash
# Overall system memory
free -h

# Docker container memory
docker stats

# MySQL memory usage
docker exec -it flipzy-mysql mysql -u root -p -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size';"

# Redis memory usage
docker exec -it flipzy-redis redis-cli INFO memory

# PHP-FPM processes
docker exec -it flipzy-backend ps aux | grep php-fpm

# Horizon workers
docker exec -it flipzy-backend supervisorctl status
```

### Check for Memory Issues

```bash
# Check if containers are hitting limits
docker stats --no-stream

# Check system swap usage
swapon --show

# Check OOM (Out of Memory) kills
dmesg | grep -i "out of memory"
```

## Optimization Tips

### If Running Out of Memory

1. **Reduce MySQL buffer pool:**
   ```ini
   innodb_buffer_pool_size = 1G  # Reduce from 1.5G
   ```

2. **Reduce Horizon workers:**
   ```php
   'maxProcesses' => 3,  // Reduce from 5
   ```

3. **Reduce Redis memory:**
   ```yaml
   maxmemory: 256mb  // Reduce from 400mb
   ```

4. **Reduce PHP-FPM workers:**
   Edit `docker/supervisor/php-fpm.conf`:
   ```ini
   numprocs = 3  // Reduce from 5
   ```

### If You Have Extra Memory

1. **Increase MySQL buffer pool:**
   ```ini
   innodb_buffer_pool_size = 2G  # Increase from 1.5G
   ```

2. **Increase Horizon workers:**
   ```php
   'maxProcesses' => 6,  // Increase from 5
   ```

3. **Increase Redis memory:**
   ```yaml
   maxmemory: 512mb  // Increase from 400mb
   ```

## Frontend Considerations

If your frontend is also running on the same server:

### Node.js/Next.js Frontend
- **Development:** ~500MB-1GB
- **Production:** ~200-500MB (if using PM2 or similar)

### Nginx (if separate)
- **Static files:** ~50-100MB
- **Process:** ~10-20MB

## Recommended Server Specs

For optimal performance with both frontend and backend:

- **Minimum:** 8GB RAM (current setup)
- **Recommended:** 16GB RAM (allows more headroom)
- **CPU:** 4+ cores
- **Storage:** SSD recommended for MySQL

## Troubleshooting

### High Memory Usage

1. Check which container is using most memory:
   ```bash
   docker stats --no-stream --format "table {{.Name}}\t{{.MemUsage}}\t{{.MemPerc}}"
   ```

2. Check MySQL connections:
   ```bash
   docker exec -it flipzy-mysql mysql -u root -p -e "SHOW PROCESSLIST;"
   ```

3. Check Horizon worker count:
   ```bash
   docker exec -it flipzy-backend supervisorctl status laravel-horizon
   ```

### Out of Memory Errors

If you see OOM errors:

1. Reduce MySQL `innodb_buffer_pool_size`
2. Reduce Horizon `maxProcesses`
3. Reduce Redis `maxmemory`
4. Consider upgrading to 16GB RAM

## Summary

With 8GB RAM, this allocation provides:
- ✅ Stable MySQL performance (1.5GB buffer pool)
- ✅ Good queue processing (5 Horizon workers)
- ✅ Adequate caching (400MB Redis)
- ✅ Room for frontend application (~1GB)
- ✅ **Proper headroom: ~2.5GB free for system + spikes**
- ✅ Prevents OOM (Out of Memory) kills
- ✅ Allows OS caching and temporary processes

Monitor your actual usage and adjust as needed!

