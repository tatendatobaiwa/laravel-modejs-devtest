#!/bin/bash

# Laravel Deployment Script
# This script handles the deployment of the Laravel application

set -e

echo "🚀 Starting Laravel deployment..."

# Check if we're in production
if [ "$APP_ENV" = "production" ]; then
    echo "📦 Production deployment detected"
    
    # Install dependencies without dev packages
    echo "📥 Installing production dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
    
    # Clear and cache configurations
    echo "⚡ Optimizing application..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
    
    # Run database migrations
    echo "🗄️ Running database migrations..."
    php artisan migrate --force
    
    # Clear application cache
    php artisan cache:clear
    
    # Generate application key if not set
    if [ -z "$APP_KEY" ]; then
        echo "🔑 Generating application key..."
        php artisan key:generate --force
    fi
    
    # Create storage link
    echo "🔗 Creating storage link..."
    php artisan storage:link
    
    # Set proper permissions
    echo "🔒 Setting file permissions..."
    chmod -R 755 storage bootstrap/cache
    chown -R www-data:www-data storage bootstrap/cache
    
else
    echo "🛠️ Development deployment detected"
    
    # Install all dependencies including dev
    echo "📥 Installing all dependencies..."
    composer install --optimize-autoloader --no-interaction
    
    # Run database migrations
    echo "🗄️ Running database migrations..."
    php artisan migrate
    
    # Generate application key if not set
    if [ -z "$APP_KEY" ]; then
        echo "🔑 Generating application key..."
        php artisan key:generate
    fi
    
    # Create storage link
    echo "🔗 Creating storage link..."
    php artisan storage:link
    
    # Set proper permissions
    echo "🔒 Setting file permissions..."
    chmod -R 755 storage bootstrap/cache
fi

echo "✅ Deployment completed successfully!"