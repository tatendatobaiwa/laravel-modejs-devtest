#!/bin/bash

# Backend Foundation Verification Script
# This script verifies that all components of the backend foundation are properly set up

echo "🔍 Verifying Backend Foundation Setup..."
echo "=================================="

# Check directory structure
echo "📁 Checking directory structure..."
directories=(
    "app/Services"
    "app/Repositories" 
    "app/Http/Requests"
    "app/Http/Middleware"
    "app/Exceptions"
    "app/Providers"
    "config"
    "storage/app/public"
    "storage/framework/cache"
    "storage/framework/sessions"
    "storage/framework/views"
    "storage/logs"
    "docker/nginx/conf.d"
    "docker/php"
)

for dir in "${directories[@]}"; do
    if [ -d "$dir" ]; then
        echo "✅ $dir exists"
    else
        echo "❌ $dir missing"
    fi
done

# Check essential files
echo ""
echo "📄 Checking essential files..."
files=(
    "composer.json"
    "docker-compose.yml"
    "docker-compose.prod.yml"
    "Dockerfile"
    ".env.example"
    ".env.production"
    "config/app.php"
    "config/database.php"
    "config/cors.php"
    "config/sanctum.php"
    "config/cache.php"
    "config/session.php"
    "config/logging.php"
    "config/queue.php"
    "bootstrap/app.php"
    "public/index.php"
    "artisan"
    "deploy.sh"
    "deploy.bat"
)

for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file exists"
    else
        echo "❌ $file missing"
    fi
done

# Check Docker configuration
echo ""
echo "🐳 Checking Docker configuration..."
if [ -f "docker-compose.yml" ]; then
    echo "✅ Docker Compose configuration found"
    if grep -q "redis" docker-compose.yml; then
        echo "✅ Redis service configured"
    else
        echo "❌ Redis service not found"
    fi
    
    if grep -q "mysql:8.0" docker-compose.yml; then
        echo "✅ MySQL 8.0 configured"
    else
        echo "❌ MySQL 8.0 not configured"
    fi
fi

# Check Dockerfile optimizations
echo ""
echo "🏗️ Checking Dockerfile optimizations..."
if [ -f "Dockerfile" ]; then
    if grep -q "multi-stage" Dockerfile || grep -q "FROM.*as" Dockerfile; then
        echo "✅ Multi-stage build configured"
    else
        echo "❌ Multi-stage build not found"
    fi
    
    if grep -q "opcache" Dockerfile; then
        echo "✅ OPcache optimization found"
    else
        echo "❌ OPcache optimization missing"
    fi
fi

# Check environment configuration
echo ""
echo "⚙️ Checking environment configuration..."
if [ -f ".env.example" ]; then
    if grep -q "REDIS_HOST" .env.example; then
        echo "✅ Redis configuration in environment"
    else
        echo "❌ Redis configuration missing"
    fi
    
    if grep -q "SANCTUM_STATEFUL_DOMAINS" .env.example; then
        echo "✅ Sanctum configuration found"
    else
        echo "❌ Sanctum configuration missing"
    fi
    
    if grep -q "CORS_ALLOWED_ORIGINS" .env.example; then
        echo "✅ CORS configuration found"
    else
        echo "❌ CORS configuration missing"
    fi
fi

# Check Laravel configuration
echo ""
echo "🎯 Checking Laravel configuration..."
if [ -f "config/database.php" ]; then
    if grep -q "PDO::ATTR_PERSISTENT" config/database.php; then
        echo "✅ Database optimizations configured"
    else
        echo "❌ Database optimizations missing"
    fi
fi

if [ -f "bootstrap/app.php" ]; then
    if grep -q "Sanctum" bootstrap/app.php; then
        echo "✅ Sanctum middleware configured"
    else
        echo "❌ Sanctum middleware missing"
    fi
fi

echo ""
echo "🎉 Backend Foundation Verification Complete!"
echo "============================================"