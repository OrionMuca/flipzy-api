#!/bin/bash

# Automated setup script for Flipzy Backend
# This script helps set up the environment for development, staging, or production

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Function to check if Docker is running
check_docker() {
    if ! docker info > /dev/null 2>&1; then
        print_error "Docker is not running. Please start Docker Desktop and try again."
        exit 1
    fi
    print_success "Docker is running"
}

# Function to select environment
select_environment() {
    echo ""
    print_info "Select environment:"
    echo "  1) Development"
    echo "  2) Staging"
    echo "  3) Production"
    read -p "Enter choice [1-3]: " env_choice
    
    case $env_choice in
        1)
            ENV="dev"
            COMPOSE_FILE="docker-compose.dev.yml"
            print_success "Selected: Development"
            ;;
        2)
            ENV="staging"
            COMPOSE_FILE="docker-compose.staging.yml"
            print_success "Selected: Staging"
            ;;
        3)
            ENV="production"
            COMPOSE_FILE="docker-compose.yml"
            print_success "Selected: Production"
            ;;
        *)
            print_error "Invalid choice. Defaulting to Development"
            ENV="dev"
            COMPOSE_FILE="docker-compose.dev.yml"
            ;;
    esac
}

# Function to create .env file
create_env_file() {
    if [ -f ".env" ]; then
        print_warning ".env file already exists"
        read -p "Do you want to overwrite it? (y/N): " overwrite
        if [[ ! $overwrite =~ ^[Yy]$ ]]; then
            print_info "Keeping existing .env file"
            return
        fi
    fi
    
    if [ -f ".env.example" ]; then
        cp .env.example .env
        print_success "Created .env file from .env.example"
        print_warning "Please edit .env file with your configuration values"
    else
        print_error ".env.example not found!"
        exit 1
    fi
}

# Function to build and start containers
start_containers() {
    print_info "Building and starting containers..."
    docker-compose -f $COMPOSE_FILE up -d --build
    
    if [ $? -eq 0 ]; then
        print_success "Containers started successfully"
    else
        print_error "Failed to start containers"
        exit 1
    fi
}

# Function to wait for services
wait_for_services() {
    print_info "Waiting for services to be ready..."
    sleep 5
    
    # Check if containers are running
    if docker-compose -f $COMPOSE_FILE ps | grep -q "Up"; then
        print_success "Services are running"
    else
        print_error "Some services failed to start"
        docker-compose -f $COMPOSE_FILE ps
        exit 1
    fi
}

# Function to show logs
show_logs() {
    print_info "Showing container logs (Ctrl+C to exit)..."
    docker-compose -f $COMPOSE_FILE logs -f app
}

# Main execution
main() {
    echo ""
    echo "=========================================="
    echo "  Flipzy Backend - Automated Setup"
    echo "=========================================="
    echo ""
    
    # Check Docker
    check_docker
    
    # Select environment
    select_environment
    
    # Create .env file
    echo ""
    read -p "Create .env file? (Y/n): " create_env
    if [[ ! $create_env =~ ^[Nn]$ ]]; then
        create_env_file
    fi
    
    # Build and start
    echo ""
    read -p "Build and start containers? (Y/n): " start_containers_choice
    if [[ ! $start_containers_choice =~ ^[Nn]$ ]]; then
        start_containers
        wait_for_services
        
        echo ""
        print_success "Setup complete!"
        echo ""
        print_info "The application will automatically:"
        echo "  • Generate application key"
        echo "  • Install Passport keys"
        echo "  • Run migrations"
        if [ "$ENV" != "production" ]; then
            echo "  • Seed database"
        fi
        echo "  • Generate Swagger documentation"
        echo ""
        print_info "Container status:"
        docker-compose -f $COMPOSE_FILE ps
        echo ""
        print_info "View logs with: docker-compose -f $COMPOSE_FILE logs -f app"
        echo ""
        
        read -p "Show logs now? (y/N): " show_logs_choice
        if [[ $show_logs_choice =~ ^[Yy]$ ]]; then
            show_logs
        fi
    else
        print_info "Skipping container startup"
        print_info "To start containers manually, run:"
        echo "  docker-compose -f $COMPOSE_FILE up -d --build"
    fi
}

# Run main function
main

