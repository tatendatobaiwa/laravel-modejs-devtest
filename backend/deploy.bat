@echo off
REM Laravel Deployment Script for Windows
REM This script handles the deployment of the Laravel application

echo 🚀 Starting Laravel deployment...

REM Check if we're in production
if "%APP_ENV%"=="production" (
    echo 📦 Production deployment detected
    
    REM Install dependencies without dev packages
    echo 📥 Installing production dependencies...
    composer install --no-dev --optimize-autoloader --no-interaction
    
    REM Clear and cache configurations
    echo ⚡ Optimizing application...
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
    
    REM Run database migrations
    echo 🗄️ Running database migrations...
    php artisan migrate --force
    
    REM Clear application cache
    php artisan cache:clear
    
    REM Generate application key if not set
    if "%APP_KEY%"=="" (
        echo 🔑 Generating application key...
        php artisan key:generate --force
    )
    
    REM Create storage link
    echo 🔗 Creating storage link...
    php artisan storage:link
    
) else (
    echo 🛠️ Development deployment detected
    
    REM Install all dependencies including dev
    echo 📥 Installing all dependencies...
    composer install --optimize-autoloader --no-interaction
    
    REM Run database migrations
    echo 🗄️ Running database migrations...
    php artisan migrate
    
    REM Generate application key if not set
    if "%APP_KEY%"=="" (
        echo 🔑 Generating application key...
        php artisan key:generate
    )
    
    REM Create storage link
    echo 🔗 Creating storage link...
    php artisan storage:link
)

echo ✅ Deployment completed successfully!
pause