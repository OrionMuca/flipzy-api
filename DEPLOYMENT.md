# Deployment Guide - DigitalOcean with Docker

This guide will help you deploy your Laravel backend and Angular frontend to DigitalOcean using Docker.

## Prerequisites

- DigitalOcean account
- Docker installed locally (for testing)
- GitHub repository with your code
- Domain name (optional, for production)

## Architecture

```
┌─────────────────┐
│  DigitalOcean   │
│     Droplet     │
│                 │
│  ┌───────────┐  │
│  │  Nginx    │  │  (Reverse Proxy)
│  └─────┬─────┘  │
│        │        │
│  ┌─────▼─────┐  │
│  │  Laravel  │  │  (Backend API)
│  │  Container│  │
│  └─────┬─────┘  │
│        │        │
│  ┌─────▼─────┐  │
│  │  MySQL    │  │  (Database)
│  └───────────┘  │
│                 │
│  ┌───────────┐  │
│  │  Redis    │  │  (Cache/Queue)
│  └───────────┘  │
│                 │
│  ┌───────────┐  │
│  │  Angular  │  │  (Frontend - Static)
│  │  Container│  │
│  └───────────┘  │
└─────────────────┘
```

## Step 1: Prepare Your Code

### 1.1 Update .env for Production

Create a `.env.production` file:

```env
APP_NAME=Flipzy
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=flipzy_prod
DB_USERNAME=flipzy
DB_PASSWORD=your_secure_password_here

REDIS_HOST=redis
REDIS_PORT=6379

QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis

# Add your API keys
ATTOM_API_KEY=your_attom_key
OPENAI_API_KEY=your_openai_key
STRIPE_SECRET_KEY=your_stripe_key
# ... other keys
```

### 1.2 Commit Docker Files

```bash
git add Dockerfile docker-compose.yml .dockerignore docker/
git commit -m "Add Docker configuration"
git push origin main
```

## Step 2: Create DigitalOcean Droplet

1. **Go to DigitalOcean Dashboard** → Create → Droplets
2. **Choose:**
   - **Image**: Ubuntu 22.04 LTS
   - **Plan**: Basic ($12/month - 2GB RAM minimum recommended)
   - **Region**: Choose closest to your users
   - **Authentication**: SSH keys (recommended) or password
3. **Create Droplet**

## Step 3: Setup Server

### 3.1 Connect to Droplet

```bash
ssh root@your_droplet_ip
```

### 3.2 Install Docker

```bash
# Update system
apt update && apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh

# Install Docker Compose
apt install docker-compose-plugin -y

# Verify installation
docker --version
docker compose version
```

### 3.3 Create Non-Root User (Optional but recommended)

```bash
adduser deploy
usermod -aG docker deploy
usermod -aG sudo deploy
su - deploy
```

## Step 4: Deploy Application

### 4.1 Clone Repository

```bash
cd /opt
git clone https://github.com/yourusername/flipzy-backend.git
cd flipzy-backend
```

### 4.2 Create .env File

```bash
cp .env.example .env
nano .env
# Paste your production environment variables
```

### 4.3 Build and Start Containers

```bash
# Build images
docker compose build

# Start services
docker compose up -d

# Check status
docker compose ps
```

### 4.4 Setup Laravel

```bash
# Generate application key
docker compose exec app php artisan key:generate

# Run migrations
docker compose exec app php artisan migrate --force

# Create storage link
docker compose exec app php artisan storage:link

# Cache configuration
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

## Step 5: Setup Nginx Reverse Proxy (Optional)

If you want to use a domain name and SSL:

### 5.1 Install Nginx on Host

```bash
apt install nginx certbot python3-certbot-nginx -y
```

### 5.2 Create Nginx Config

```bash
nano /etc/nginx/sites-available/flipzy-api
```

Add:

```nginx
server {
    listen 80;
    server_name api.yourdomain.com;

    location / {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### 5.3 Enable Site and SSL

```bash
ln -s /etc/nginx/sites-available/flipzy-api /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx

# Get SSL certificate
certbot --nginx -d api.yourdomain.com
```

## Step 6: Deploy Angular Frontend

### 6.1 Build Angular App Locally

```bash
cd /path/to/angular-app
npm run build --prod
```

### 6.2 Create Dockerfile for Angular

Create `Dockerfile` in Angular project:

```dockerfile
FROM nginx:alpine
COPY dist/your-app-name /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
```

### 6.3 Add to docker-compose.yml

Add to your backend's `docker-compose.yml`:

```yaml
  frontend:
    build:
      context: ../flipzy-frontend
      dockerfile: Dockerfile
    container_name: flipzy-frontend
    restart: unless-stopped
    ports:
      - "80:80"
    networks:
      - flipzy-network
```

## Step 7: Monitoring and Maintenance

### 7.1 View Logs

```bash
# Application logs
docker compose logs -f app

# All logs
docker compose logs -f

# Specific service
docker compose logs -f mysql
```

### 7.2 Update Application

```bash
cd /opt/flipzy-backend
git pull origin main
docker compose build
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan config:cache
```

### 7.3 Backup Database

```bash
# Create backup script
nano /opt/backup-db.sh
```

```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
docker compose exec -T mysql mysqldump -u ${DB_USERNAME:-flipzy} -p${DB_PASSWORD:-flipzy_password} ${DB_DATABASE:-flipzy_prod} > /opt/backups/db_$DATE.sql
# Keep only last 7 days
find /opt/backups -name "db_*.sql" -mtime +7 -delete
```

```bash
chmod +x /opt/backup-db.sh
# Add to crontab for daily backups
crontab -e
# Add: 0 2 * * * /opt/backup-db.sh
```

## Step 8: Security Best Practices

1. **Firewall Setup:**
```bash
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable
```

2. **Update Regularly:**
```bash
apt update && apt upgrade -y
docker compose pull
docker compose up -d
```

3. **Use Strong Passwords:**
- Database passwords
- API keys
- Application keys

4. **Enable Fail2Ban:**
```bash
apt install fail2ban -y
systemctl enable fail2ban
```

## Troubleshooting

### Container won't start
```bash
docker compose logs app
docker compose ps
```

### Database connection issues
```bash
docker compose exec app php artisan tinker
# Then: DB::connection()->getPdo();
```

### Permission issues
```bash
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
docker compose exec app chmod -R 775 storage bootstrap/cache
```

### Out of memory
- Upgrade Droplet size
- Or optimize Docker resources

## Cost Estimation

- **Droplet (2GB RAM)**: $12/month
- **Backup snapshots**: $1-2/month
- **Domain + SSL**: $10-15/year
- **Total**: ~$15/month

## Next Steps

1. Set up CI/CD with GitHub Actions
2. Configure monitoring (e.g., Laravel Telescope)
3. Set up automated backups
4. Configure email service (Postmark/Resend)
5. Set up CDN for static assets

## Support

For issues, check:
- Docker logs: `docker compose logs`
- Laravel logs: `storage/logs/laravel.log`
- Nginx logs: `/var/log/nginx/`

