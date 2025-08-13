@echo off
REM Backend Foundation Verification Script for Windows
REM This script verifies that all components of the backend foundation are properly set up

echo 🔍 Verifying Backend Foundation Setup...
echo ==================================

REM Check directory structure
echo 📁 Checking directory structure...

if exist "app\Services" (echo ✅ app/Services exists) else (echo ❌ app/Services missing)
if exist "app\Repositories" (echo ✅ app/Repositories exists) else (echo ❌ app/Repositories missing)
if exist "app\Http\Requests" (echo ✅ app/Http/Requests exists) else (echo ❌ app/Http/Requests missing)
if exist "app\Http\Middleware" (echo ✅ app/Http/Middleware exists) else (echo ❌ app/Http/Middleware missing)
if exist "app\Exceptions" (echo ✅ app/Exceptions exists) else (echo ❌ app/Exceptions missing)
if exist "app\Providers" (echo ✅ app/Providers exists) else (echo ❌ app/Providers missing)
if exist "config" (echo ✅ config exists) else (echo ❌ config missing)
if exist "storage\app\public" (echo ✅ storage/app/public exists) else (echo ❌ storage/app/public missing)
if exist "storage\framework\cache" (echo ✅ storage/framework/cache exists) else (echo ❌ storage/framework/cache missing)
if exist "storage\framework\sessions" (echo ✅ storage/framework/sessions exists) else (echo ❌ storage/framework/sessions missing)
if exist "storage\framework\views" (echo ✅ storage/framework/views exists) else (echo ❌ storage/framework/views missing)
if exist "storage\logs" (echo ✅ storage/logs exists) else (echo ❌ storage/logs missing)

echo.
echo 📄 Checking essential files...

if exist "composer.json" (echo ✅ composer.json exists) else (echo ❌ composer.json missing)
if exist "docker-compose.yml" (echo ✅ docker-compose.yml exists) else (echo ❌ docker-compose.yml missing)
if exist "docker-compose.prod.yml" (echo ✅ docker-compose.prod.yml exists) else (echo ❌ docker-compose.prod.yml missing)
if exist "Dockerfile" (echo ✅ Dockerfile exists) else (echo ❌ Dockerfile missing)
if exist ".env.example" (echo ✅ .env.example exists) else (echo ❌ .env.example missing)
if exist ".env.production" (echo ✅ .env.production exists) else (echo ❌ .env.production missing)
if exist "config\app.php" (echo ✅ config/app.php exists) else (echo ❌ config/app.php missing)
if exist "config\database.php" (echo ✅ config/database.php exists) else (echo ❌ config/database.php missing)
if exist "config\cors.php" (echo ✅ config/cors.php exists) else (echo ❌ config/cors.php missing)
if exist "config\sanctum.php" (echo ✅ config/sanctum.php exists) else (echo ❌ config/sanctum.php missing)
if exist "bootstrap\app.php" (echo ✅ bootstrap/app.php exists) else (echo ❌ bootstrap/app.php missing)
if exist "public\index.php" (echo ✅ public/index.php exists) else (echo ❌ public/index.php missing)
if exist "artisan" (echo ✅ artisan exists) else (echo ❌ artisan missing)
if exist "deploy.sh" (echo ✅ deploy.sh exists) else (echo ❌ deploy.sh missing)
if exist "deploy.bat" (echo ✅ deploy.bat exists) else (echo ❌ deploy.bat missing)

echo.
echo 🐳 Checking Docker configuration...
if exist "docker-compose.yml" (
    echo ✅ Docker Compose configuration found
    findstr /C:"redis" docker-compose.yml >nul && echo ✅ Redis service configured || echo ❌ Redis service not found
    findstr /C:"mysql:8.0" docker-compose.yml >nul && echo ✅ MySQL 8.0 configured || echo ❌ MySQL 8.0 not configured
)

echo.
echo 🏗️ Checking Dockerfile optimizations...
if exist "Dockerfile" (
    findstr /C:"as production" Dockerfile >nul && echo ✅ Multi-stage build configured || echo ❌ Multi-stage build not found
    findstr /C:"opcache" Dockerfile >nul && echo ✅ OPcache optimization found || echo ❌ OPcache optimization missing
)

echo.
echo ⚙️ Checking environment configuration...
if exist ".env.example" (
    findstr /C:"REDIS_HOST" .env.example >nul && echo ✅ Redis configuration in environment || echo ❌ Redis configuration missing
    findstr /C:"SANCTUM_STATEFUL_DOMAINS" .env.example >nul && echo ✅ Sanctum configuration found || echo ❌ Sanctum configuration missing
    findstr /C:"CORS_ALLOWED_ORIGINS" .env.example >nul && echo ✅ CORS configuration found || echo ❌ CORS configuration missing
)

echo.
echo 🎉 Backend Foundation Verification Complete!
echo ============================================
pause