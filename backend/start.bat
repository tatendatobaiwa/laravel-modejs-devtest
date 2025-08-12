@echo off
echo 🚀 Starting Salary Management System Backend...

REM Check if Docker is running
docker info >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Docker is not running. Please start Docker Desktop first.
    pause
    exit /b 1
)

echo ✅ Docker is running

REM Copy environment file if it doesn't exist
if not exist .env (
    echo 📝 Creating .env file from template...
    copy env.example .env
    echo ✅ .env file created
) else (
    echo ✅ .env file already exists
)

REM Build and start containers
echo 🐳 Building and starting Docker containers...
docker-compose up -d --build

if %errorlevel% equ 0 (
    echo ✅ Containers started successfully!
    echo.
    echo 🌐 Access URLs:
    echo    API: http://localhost:8000/api
    echo    phpMyAdmin: http://localhost:8080
    echo    Health Check: http://localhost:8000/up
    echo.
    echo 📋 Next steps:
    echo    1. Wait for containers to fully start (about 30 seconds)
    echo    2. Run: docker exec -it salary-app bash
    echo    3. Inside container, run: composer install
    echo    4. Then: php artisan key:generate
    echo    5. Then: php artisan migrate --seed
    echo    6. Finally: php artisan storage:link
    echo.
    echo 🔍 Check container status: docker-compose ps
    echo 📊 View logs: docker-compose logs -f
) else (
    echo ❌ Failed to start containers
    pause
    exit /b 1
)

pause
