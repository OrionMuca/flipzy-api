# Server Requirements for Docker Deployment

This document outlines the requirements for running the Flipzy backend containers on your server.

## Server Requirements

### Minimum System Requirements
- **OS**: Linux (Ubuntu 20.04+, Debian 11+, CentOS 8+, or any Linux distribution with Docker support)
- **CPU**: 2 cores minimum (4+ recommended for production)
- **RAM**: 4GB minimum (8GB+ recommended for production)
- **Disk Space**: 20GB minimum (50GB+ recommended for production)
- **Network**: Internet connection for pulling Docker images

### Required Software

#### 1. Docker Engine
Docker is required to run containers. Install Docker Engine:

**Ubuntu/Debian - Simple Method (Recommended):**
```bash
# Update package index
sudo apt-get update

# Install Docker from Ubuntu/Debian repositories
sudo apt-get install -y docker.io

# Start Docker service
sudo systemctl start docker
sudo systemctl enable docker

# Verify installation
docker --version
```

**Ubuntu/Debian - Official Docker Repository (Latest Version):**
If you need the latest Docker version directly from Docker Inc., use this method:
```bash
# Update package index
sudo apt-get update

# Install prerequisites
sudo apt-get install -y \
    ca-certificates \
    curl \
    gnupg \
    lsb-release

# Add Docker's official GPG key
sudo mkdir -p /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg

# Set up repository
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Install Docker Engine
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Start Docker service
sudo systemctl start docker
sudo systemctl enable docker

# Verify installation
docker --version
```

**CentOS/RHEL:**
```bash
# Install prerequisites
sudo yum install -y yum-utils

# Add Docker repository
sudo yum-config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo

# Install Docker Engine
sudo yum install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Start Docker service
sudo systemctl start docker
sudo systemctl enable docker

# Verify installation
docker --version
```

#### 2. Docker Compose
Docker Compose is required to orchestrate multiple containers. Install it:

**If using docker.io (Simple Method):**
```bash
# Install Docker Compose plugin
sudo apt-get install -y docker-compose

# Verify installation
docker-compose --version
```

**Standalone Installation (Alternative):**
```bash
# Download latest version
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose

# Make executable
sudo chmod +x /usr/local/bin/docker-compose

# Verify installation
docker-compose --version
```

**Note**: Docker Compose V2 is integrated into Docker CLI as `docker compose` (without hyphen). Both `docker-compose` and `docker compose` commands work. If you install via `apt install docker-compose`, you'll get the standalone version which uses `docker-compose` command.

### Optional but Recommended

#### Git
For cloning and updating the repository:
```bash
# Ubuntu/Debian
sudo apt-get install -y git

# CentOS/RHEL
sudo yum install -y git
```

#### Firewall Configuration
Ensure ports are open for your services:

```bash
# Ubuntu/Debian (UFW)
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp    # HTTPS
sudo ufw allow 22/tcp     # SSH
sudo ufw allow 3306/tcp   # MySQL (if accessing externally)
sudo ufw allow 6379/tcp   # Redis (if accessing externally)

# CentOS/RHEL (firewalld)
sudo firewall-cmd --permanent --add-port=80/tcp
sudo firewall-cmd --permanent --add-port=443/tcp
sudo firewall-cmd --permanent --add-port=22/tcp
sudo firewall-cmd --permanent --add-port=3306/tcp
sudo firewall-cmd --permanent --add-port=6379/tcp
sudo firewall-cmd --reload
```

## Quick Installation Script

For Ubuntu/Debian servers, you can use this quick install script:

**Simple Method (Recommended):**
```bash
#!/bin/bash
# Install Docker and Docker Compose on Ubuntu/Debian (Simple Method)

# Update system
sudo apt-get update

# Install Docker from Ubuntu repositories
sudo apt-get install -y docker.io docker-compose

# Start and enable Docker
sudo systemctl start docker
sudo systemctl enable docker

# Add current user to docker group (optional, to run docker without sudo)
sudo usermod -aG docker $USER

# Verify installation
echo "Docker version:"
docker --version
echo "Docker Compose version:"
docker-compose --version

echo "Installation complete! You may need to log out and back in for group changes to take effect."
```

**Official Docker Repository Method (Latest Version):**
```bash
#!/bin/bash
# Install Docker and Docker Compose on Ubuntu/Debian (Official Method)

# Update system
sudo apt-get update
sudo apt-get install -y ca-certificates curl gnupg lsb-release

# Add Docker GPG key
sudo mkdir -p /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg

# Add Docker repository
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Install Docker
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Start and enable Docker
sudo systemctl start docker
sudo systemctl enable docker

# Add current user to docker group (optional, to run docker without sudo)
sudo usermod -aG docker $USER

# Verify installation
echo "Docker version:"
docker --version
echo "Docker Compose version:"
docker compose version

echo "Installation complete! You may need to log out and back in for group changes to take effect."
```

## Post-Installation Verification

After installation, verify everything works:

```bash
# Check Docker is running
sudo systemctl status docker

# Test Docker
sudo docker run hello-world

# Check Docker Compose
docker compose version
# or
docker-compose --version
```

## Container Requirements

The containers themselves include all necessary runtime dependencies:
- **PHP 8.3** with required extensions (pdo, pdo_mysql, mbstring, exif, pcntl, bcmath, gd, zip, redis)
- **Nginx** web server
- **PHP-FPM** process manager
- **Supervisor** for process management
- **Composer** for PHP dependencies
- **Node.js & npm** (development only)

No additional PHP, Nginx, or other runtime dependencies need to be installed on the host server - everything runs in containers.

## Network Requirements

The application uses the following ports (configurable via environment variables):
- **80**: HTTP (web server)
- **443**: HTTPS (if using SSL/TLS)
- **3306**: MySQL (default, can be changed)
- **6379**: Redis (default, can be changed)

## Storage Requirements

Docker will create volumes for:
- MySQL data: ~500MB - 10GB+ (depending on data)
- Redis data: ~100MB - 1GB+ (depending on cache size)
- Application logs: ~100MB - 1GB+ (depending on log retention)

Ensure adequate disk space is available.

## Security Considerations

1. **Run Docker as non-root user** (add user to docker group)
2. **Configure firewall** to restrict access to necessary ports only
3. **Use environment variables** for sensitive data (passwords, API keys)
4. **Keep Docker updated** regularly
5. **Use Docker secrets** or external secret management for production
6. **Enable SSL/TLS** for production deployments (use reverse proxy like Nginx or Traefik)

## Troubleshooting

### Docker permission denied
```bash
# Add user to docker group
sudo usermod -aG docker $USER
# Log out and back in, or run:
newgrp docker
```

### Cannot connect to Docker daemon
```bash
# Start Docker service
sudo systemctl start docker
sudo systemctl enable docker
```

### Port already in use
```bash
# Check what's using the port
sudo netstat -tulpn | grep :80
# Or change the port in docker-compose.yml
```

## Additional Resources

- [Docker Installation Guide](https://docs.docker.com/engine/install/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Docker Security Best Practices](https://docs.docker.com/engine/security/)

