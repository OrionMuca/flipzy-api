# Subdomain Setup Guide: api.goflipzy.com

This guide explains how to configure your Laravel backend to run on the `api.goflipzy.com` subdomain while your Angular frontend runs on the main domain.

---

## Overview

You have:
- **Frontend (Angular)**: Running on `goflipzy.com` (or `www.goflipzy.com`)
- **Backend (Laravel)**: Should run on `api.goflipzy.com`

Both applications are containerized with Docker, but they have separate Dockerfiles and logic.

---

## Prerequisites

1. ✅ DNS configured: `api.goflipzy.com` points to your server IP
2. ✅ SSL certificate for `api.goflipzy.com` (Let's Encrypt recommended)
3. ✅ Frontend already configured and running on main domain
4. ✅ Backend Docker container running

---

## Step 1: Update Environment Variables

Update your `.env` file with the new API URL:

```env
APP_URL=https://api.goflipzy.com
FRONTEND_URL=https://goflipzy.com
```

**Important**: Make sure to use `https://` if you have SSL configured.

---

## Step 2: Update CORS Configuration

The CORS configuration file has been created at `config/cors.php`. Update the `allowed_origins` array to include your frontend domain:

```php
'allowed_origins' => [
    'https://goflipzy.com',
    'https://www.goflipzy.com',
    'http://localhost:4200',  // For local development
    'http://localhost:3000',  // Alternative local port
],
```

If you need to add more origins (like staging domains), add them to this array.

---

## Step 3: Server-Side Nginx Configuration

Since your frontend has its own Dockerfile and logic, you likely have a **host-level Nginx** that routes traffic. You need to configure it to proxy requests to `api.goflipzy.com` to your Laravel Docker container.

### Option A: Host Nginx (Recommended)

If you have Nginx installed on your host server (outside Docker), create or update the configuration:

**Create file**: `/etc/nginx/sites-available/api.goflipzy.com`

```nginx
server {
    listen 80;
    server_name api.goflipzy.com;
    
    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name api.goflipzy.com;
    
    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/api.goflipzy.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.goflipzy.com/privkey.pem;
    
    # SSL Security Settings
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    
    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # Proxy to Laravel Docker Container
    location / {
        proxy_pass http://127.0.0.1:8000;  # Change port if different
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
        
        # WebSocket support (if needed for broadcasting)
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        
        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
    
    # Increase body size for file uploads
    client_max_body_size 50M;
}
```

**Enable the site:**
```bash
sudo ln -s /etc/nginx/sites-available/api.goflipzy.com /etc/nginx/sites-enabled/
sudo nginx -t  # Test configuration
sudo systemctl reload nginx
```

### Option B: Docker Container Direct Access

If you want to expose the container directly (not recommended for production without proper SSL):

1. Make sure your Docker container exposes port 80 (or your configured port)
2. Update `docker-compose.yml` to expose the port:
   ```yaml
   ports:
     - "8000:80"  # Host:Container
   ```
3. Configure your DNS to point directly to the server
4. Use a reverse proxy like Cloudflare or set up SSL in the container

**Note**: The container's internal Nginx is already configured to listen for `api.goflipzy.com` (see `docker/nginx/default.conf`).

---

## Step 4: SSL Certificate Setup

### Using Let's Encrypt (Certbot)

```bash
# Install certbot
sudo apt-get update
sudo apt-get install certbot python3-certbot-nginx

# Get certificate for API subdomain
sudo certbot --nginx -d api.goflipzy.com

# Auto-renewal (should be set up automatically)
sudo certbot renew --dry-run
```

### Using Cloudflare (Alternative)

If you're using Cloudflare:
1. Enable "Full" or "Full (strict)" SSL mode
2. Cloudflare will handle SSL termination
3. You can use HTTP between Cloudflare and your server, or set up SSL for end-to-end encryption

---

## Step 5: Update Frontend API Base URL

In your Angular frontend, update the API base URL to point to the subdomain:

**Example (Angular environment files):**

`src/environments/environment.prod.ts`:
```typescript
export const environment = {
  production: true,
  apiUrl: 'https://api.goflipzy.com/api',
  // ... other config
};
```

`src/environments/environment.ts`:
```typescript
export const environment = {
  production: false,
  apiUrl: 'http://localhost:8000/api',  // Local development
  // ... other config
};
```

---

## Step 6: Rebuild and Restart Containers

After making configuration changes:

```bash
# Navigate to backend directory
cd /path/to/flipzy-backend

# Rebuild container (if Dockerfile changed)
docker-compose build

# Restart containers
docker-compose down
docker-compose up -d

# Check logs
docker-compose logs -f app
```

---

## Step 7: Verify Configuration

### Test API Endpoint

```bash
# Test from command line
curl https://api.goflipzy.com/api/v1/properties

# Or test health endpoint
curl https://api.goflipzy.com/up
```

### Test CORS

From your browser console on `goflipzy.com`:

```javascript
fetch('https://api.goflipzy.com/api/v1/properties')
  .then(response => response.json())
  .then(data => console.log(data))
  .catch(error => console.error('Error:', error));
```

Check the Network tab to ensure CORS headers are present:
- `Access-Control-Allow-Origin: https://goflipzy.com`
- `Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS`
- `Access-Control-Allow-Headers: *`

---

## Step 8: Update Passport OAuth Redirect URIs

If you're using Laravel Passport, update the redirect URIs in your OAuth clients:

```php
// In your database or Passport configuration
'redirect_uris' => [
    'https://goflipzy.com/oauth/callback',
    // ... other URIs
]
```

---

## Troubleshooting

### Issue: 502 Bad Gateway

**Cause**: Nginx can't reach the Docker container.

**Solution**:
1. Check if container is running: `docker ps`
2. Verify port mapping: `docker-compose ps`
3. Test connection: `curl http://127.0.0.1:8000/up`
4. Check Nginx proxy_pass URL matches your container port

### Issue: CORS Errors

**Cause**: Frontend domain not in allowed origins.

**Solution**:
1. Check `config/cors.php` includes your frontend domain
2. Clear config cache: `php artisan config:clear`
3. Restart container: `docker-compose restart app`

### Issue: SSL Certificate Errors

**Cause**: Certificate not configured or expired.

**Solution**:
1. Check certificate: `sudo certbot certificates`
2. Renew if needed: `sudo certbot renew`
3. Verify Nginx SSL configuration

### Issue: API Returns 404

**Cause**: Routes not loading or wrong base path.

**Solution**:
1. Check routes: `php artisan route:list`
2. Verify API prefix in `routes/api.php`
3. Test directly: `curl https://api.goflipzy.com/api/v1/properties`

---

## Architecture Diagram

```
┌─────────────────────────────────────────┐
│         Internet / Users                 │
└──────────────┬──────────────────────────┘
               │
               │ DNS: api.goflipzy.com
               │
┌──────────────▼──────────────────────────┐
│      Host Server (Nginx)                │
│  - Listens on port 443 (SSL)            │
│  - Proxies to Docker container          │
└──────────────┬──────────────────────────┘
               │
               │ http://127.0.0.1:8000
               │
┌──────────────▼──────────────────────────┐
│   Docker Container (flipzy-backend)     │
│  - Nginx (port 80)                      │
│  - PHP-FPM                              │
│  - Laravel Application                  │
└─────────────────────────────────────────┘
```

---

## Summary Checklist

- [ ] DNS configured: `api.goflipzy.com` → Server IP
- [ ] `.env` updated: `APP_URL=https://api.goflipzy.com`
- [ ] CORS configured: `config/cors.php` includes frontend domain
- [ ] Host Nginx configured: Proxy to Docker container
- [ ] SSL certificate installed: Let's Encrypt or Cloudflare
- [ ] Docker container running: Port 8000 exposed
- [ ] Frontend updated: API URL points to `https://api.goflipzy.com/api`
- [ ] Tested: API endpoints accessible from frontend
- [ ] Tested: CORS headers present in responses

---

## Additional Notes

1. **Port Configuration**: If your container uses a different port, update the Nginx `proxy_pass` accordingly.

2. **Multiple Environments**: Consider using different subdomains:
   - `api.goflipzy.com` - Production
   - `api-staging.goflipzy.com` - Staging
   - `api-dev.goflipzy.com` - Development

3. **Monitoring**: Set up monitoring for your API subdomain:
   - Uptime monitoring
   - Error tracking (Sentry, etc.)
   - Performance monitoring

4. **Rate Limiting**: Consider implementing rate limiting at the Nginx level for additional protection.

---

## Need Help?

If you encounter issues:
1. Check Docker logs: `docker-compose logs -f`
2. Check Nginx logs: `sudo tail -f /var/log/nginx/error.log`
3. Check Laravel logs: `docker-compose exec app tail -f storage/logs/laravel.log`
4. Verify DNS: `dig api.goflipzy.com` or `nslookup api.goflipzy.com`

