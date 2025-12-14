# DataGrip Connection Guide

This guide explains how to connect DataGrip (JetBrains IDE) to your staging database on DigitalOcean.

## Connection Details

Based on your `docker-compose.staging.yml` configuration:

- **Host**: `your-server-ip` (your DigitalOcean Droplet IP)
- **Port**: `3307` (mapped from container port 3306)
- **Database**: `flipzy_staging`
- **Username**: `flipzy`
- **Password**: `flipzy_password`
- **Root Username**: `root`
- **Root Password**: `root_password`

## Step 1: Configure Firewall (On Your Server)

**Allow MySQL connections from your IP:**

```bash
# Replace YOUR_IP with your actual IP address
# Find your IP: https://whatismyipaddress.com/

# Allow MySQL port from your IP only (more secure)
sudo ufw allow from YOUR_IP to any port 3307

# Or allow from anywhere (less secure, but easier for testing)
sudo ufw allow 3307/tcp

# Check firewall status
sudo ufw status
```

## Step 2: Verify MySQL is Accessible

**On your server:**

```bash
cd /var/www/flipzy-backend

# Check if MySQL port is exposed
docker-compose -f docker-compose.staging.yml ps mysql

# Test connection from server itself
docker-compose -f docker-compose.staging.yml exec mysql mysql -u flipzy -pflipzy_password -e "SELECT 1;"
```

**From your local machine (test connection):**

```bash
# Test if port is accessible
telnet your-server-ip 3307

# Or use MySQL client if installed
mysql -h your-server-ip -P 3307 -u flipzy -pflipzy_password flipzy_staging
```

## Step 3: Connect with DataGrip

### Initial Setup:

1. **Open DataGrip**
2. **Click** `+` → `Data Source` → `MySQL`
3. **Fill in connection details:**
   - **Host**: `your-server-ip`
   - **Port**: `3307`
   - **Database**: `flipzy_staging`
   - **User**: `flipzy`
   - **Password**: `flipzy_password`
   - **Authentication**: `Native`

4. **Click** `Test Connection`
   - If it fails, check firewall and MySQL container status

5. **Click** `OK` to save

### Advanced Settings:

**For better performance and features:**

1. **Go to** `Advanced` tab:
   - **useSSL**: `false` (for staging)
   - **allowPublicKeyRetrieval**: `true`
   - **serverTimezone**: `UTC`

2. **Go to** `Options` tab:
   - **Connect to database**: `flipzy_staging`
   - **Read only**: Uncheck (if you want to modify data)

3. **Go to** `SSH/SSL` tab (if you want SSH tunnel):
   - Check `Use SSH tunnel`
   - **Host**: `your-server-ip`
   - **Port**: `22`
   - **User**: `root`
   - **Authentication**: Use your SSH key or password

## Step 4: Verify Connection

Once connected, you should see:

- **Database**: `flipzy_staging`
- **Tables**: All your Laravel tables
- **Views**: Any database views
- **Procedures**: Stored procedures (if any)

## Troubleshooting

### Connection Refused

**Check firewall:**
```bash
# On server
sudo ufw status
sudo ufw allow 3307/tcp
```

**Check MySQL container:**
```bash
docker-compose -f docker-compose.staging.yml ps mysql
docker-compose -f docker-compose.staging.yml logs mysql | tail -20
```

### Authentication Failed

**Verify credentials:**
```bash
# Test on server
docker-compose -f docker-compose.staging.yml exec mysql mysql -u flipzy -pflipzy_password flipzy_staging -e "SELECT 1;"
```

**Check .env file:**
```bash
docker-compose -f docker-compose.staging.yml exec app cat .env | grep DB_
```

### Can't Connect from Outside

**Use SSH Tunnel (More Secure):**

In DataGrip:
1. Go to connection settings
2. **SSH/SSL** tab
3. Check `Use SSH tunnel`
4. Configure:
   - **Host**: `your-server-ip`
   - **Port**: `22`
   - **User**: `root`
   - **Authentication**: SSH key or password
5. Then MySQL connection uses:
   - **Host**: `localhost` (or `127.0.0.1`)
   - **Port**: `3307`

This way MySQL doesn't need to be exposed publicly.

## Security Best Practices

### Option 1: SSH Tunnel (Recommended)
- MySQL port stays closed to public
- All traffic goes through encrypted SSH
- More secure

### Option 2: IP Whitelist
```bash
# Only allow your IP
sudo ufw delete allow 3307/tcp
sudo ufw allow from YOUR_IP to any port 3307
```

### Option 3: Change Default Port
Update `docker-compose.staging.yml`:
```yaml
ports:
  - "${DB_PORT:-13307}:3306"  # Use non-standard port
```

## Quick Connection Test Script

**On your server:**

```bash
cat > /tmp/test-db-connection.sh << 'EOF'
#!/bin/bash
echo "Testing MySQL connection..."
echo "============================"

cd /var/www/flipzy-backend

echo -e "\n1. Container Status:"
docker-compose -f docker-compose.staging.yml ps mysql

echo -e "\n2. Port Mapping:"
docker-compose -f docker-compose.staging.yml port mysql 3306

echo -e "\n3. Test Connection:"
docker-compose -f docker-compose.staging.yml exec -T mysql mysql -u flipzy -pflipzy_password -e "SELECT 'Connection OK' AS status;" 2>&1

echo -e "\n4. Firewall Status:"
sudo ufw status | grep 3307 || echo "Port 3307 not in firewall rules"

echo -e "\n5. Listening Ports:"
sudo netstat -tulpn | grep 3307 || echo "Port 3307 not listening"

echo -e "\n============================"
echo "Connection Details for DataGrip:"
echo "Host: $(curl -s ifconfig.me 2>/dev/null || echo 'your-server-ip')"
echo "Port: 3307"
echo "Database: flipzy_staging"
echo "User: flipzy"
echo "Password: flipzy_password"
EOF

chmod +x /tmp/test-db-connection.sh
/tmp/test-db-connection.sh
```

## DataGrip Features You Can Use

Once connected:

1. **Browse Tables**: See all tables visually
2. **View Data**: Click any table to see data
3. **Run Queries**: Write and execute SQL
4. **Export Data**: Export tables to CSV, JSON, etc.
5. **Import Data**: Import from files
6. **Schema Visualization**: See table relationships
7. **Data Comparison**: Compare data between environments
8. **Query History**: See all executed queries

## Connection String Summary

**For DataGrip:**
```
Host: your-server-ip
Port: 3307
Database: flipzy_staging
User: flipzy
Password: flipzy_password
```

**JDBC URL:**
```
jdbc:mysql://your-server-ip:3307/flipzy_staging?useSSL=false&allowPublicKeyRetrieval=true&serverTimezone=UTC
```

## Next Steps

1. **Configure firewall** to allow port 3307
2. **Test connection** from command line first
3. **Connect with DataGrip** using the details above
4. **Use SSH tunnel** for better security (optional but recommended)

If you have connection issues, run the test script and share the output!

