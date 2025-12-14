# Deployment & Volume Setup Guide

This guide covers DigitalOcean volume setup for both image storage and database storage options.

---

## Overview

This guide explains how to configure your **100GB DigitalOcean volume** for storage. You can use it for:
- **Option 1:** Images only (recommended for most setups)
- **Option 2:** Database storage (for persistence and backups)

---

## Option 1: Images Only Storage (Recommended)

This configuration stores **only images** (user uploads, property photos, etc.) on the 100GB volume. MySQL data remains on your **32GB local disk** using Docker volumes.

### Storage Strategy

#### 100GB DigitalOcean Volume ()
- ✅ **Images & User Uploads** → 
- ✅ **Private Files** → 

#### 32GB Local Disk
- ✅ **MySQL Database** → Docker volume (managed automatically)
- ✅ **Redis Data** → Docker volume (managed automatically)
- ✅ **Application Logs** → 
- ✅ **Framework Cache** → 
- ✅ **Bootstrap Cache** → 

### Setup Steps

#### Step 1: Create Directory Structure on Volume

**On your server:**



#### Step 2: Migrate Existing Images (If Any)

**On your server:**



#### Step 3: Update docker-compose.staging.yml

Update your  to mount the volume for images:



#### Step 4: Start Containers

**On your server:**



### Storage Breakdown

#### What's on the 100GB Volume:
- **Images**: Property photos, user avatars, document uploads
- **Public Files**: Any files served publicly via Laravel's storage link

#### What's on the 32GB Local Disk:
- **MySQL Database**: ~1-5GB typically (grows with data)
- **Redis Data**: ~100-500MB (in-memory cache, persisted)
- **Application Logs**: ~100MB-1GB (rotates automatically)
- **Framework Cache**: ~50-200MB (cleared on deploy)
- **Bootstrap Cache**: ~10-50MB (cleared on deploy)
- **System Files**: ~5-10GB (OS, Docker, etc.)

**Total Estimated Usage**: ~10-20GB on local disk, leaving plenty of room for growth.

---

## Option 2: Database Storage on DigitalOcean Volume

This configuration uses the DigitalOcean volume for database storage, providing better persistence and easier backups.

### Current Setup vs DigitalOcean Volume

#### Current Setup (Docker Local Volumes)
- **Location**:  on your Droplet
- **Storage**: Uses Droplet's local disk space
- **Backup**: Manual backups only
- **Persistence**: Data persists as long as the Droplet exists

#### DigitalOcean Volume (Recommended)
- **Location**: Separate block storage volume (100GB)
- **Storage**: Dedicated volume, separate from Droplet
- **Backup**: Can be snapshotted easily
- **Persistence**: Survives Droplet recreation
- **Performance**: Better I/O performance

### Setup Steps

#### Step 1: Attach DigitalOcean Volume to Your Droplet

**In DigitalOcean Dashboard:**
1. Go to **Volumes** → Select your 100GB volume
2. Click **More** → **Configure**
3. Attach to your Droplet ()
4. Note the device name (usually  or )

**On Your Server:**


#### Step 2: Configure Docker to Use DigitalOcean Volume

**Option A: Use Bind Mount (Simple)**

Update :



**Option B: Use Docker Volume Driver (Advanced)**



Then update :



#### Step 3: Migrate Existing Data (If Needed)



### Database Visualization Tools

#### 1. phpMyAdmin (Web Interface)

**Access:**
- URL: 
- Username:  (or your DB_USERNAME)
- Password:  (or your DB_PASSWORD)

**Start phpMyAdmin:**


#### 2. MySQL Workbench (Desktop Client)

**Connect from your local machine:**
- Host: 
- Port:  (as configured in docker-compose.staging.yml)
- Username: 
- Password: 
- Database: 

---

## Monitoring Storage

### Check Volume Usage



### Check Image Storage Size



### Check MySQL Size



### Database Size Query



---

## Backup Strategy

### Backup Images



### Backup MySQL



### Automated Backups to DigitalOcean Volume



---

## Troubleshooting

### Permission Issues



### Volume Not Mounted



### Low Disk Space on Local Disk

WARNING! This will remove:
  - all stopped containers
  - all networks not used by at least one container
  - all anonymous volumes not used by at least one container
  - all images without at least one container associated to them
  - all build cache

Are you sure you want to continue? [y/N] 

---

## Security Notes

1. **phpMyAdmin Access**: Consider restricting access via firewall:
   

2. **SSH Tunnel** (More Secure):
   

3. **Change Default Ports**: Update  in  to a non-standard port.

---

## Summary

### Option 1: Images Only (Recommended)
✅ **100GB Volume**: Only for images and user uploads  
✅ **32GB Local Disk**: MySQL, Redis, logs, and cache  
✅ **Better Separation**: Images can grow independently  
✅ **Easier Management**: Database backups stay local  

### Option 2: Database on Volume
✅ **100GB Volume**: Database storage with persistence  
✅ **Easy Backups**: Volume snapshots  
✅ **Survives Droplet Recreation**: Data persists independently  
✅ **Better Performance**: Dedicated I/O for database  

Choose the option that best fits your needs. For most setups, **Option 1 (Images Only)** is recommended as it maximizes volume space for content while keeping database operations fast on local disk.
