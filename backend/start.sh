#!/bin/bash

echo "🚀 Starting Salary Management System Backend..."

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker Desktop first."
    exit 1
fi

echo "✅ Docker is running"

# Copy environment file if it doesn't exist
if [ ! -f .env ]; then
    echo "📝 Creating .env file from template..."
    cp env.example .env
    echo "✅ .env file created"
else
    echo "✅ .env file already exists"
fi

# Build and start containers
echo "🐳 Building and starting Docker containers..."
docker-compose up -d --build

if [ $? -eq 0 ]; then
    echo "✅ Containers started successfully!"
    echo ""
    echo "🌐 Access URLs:"
    echo "   API: http://localhost:8000/api"
    echo "   phpMyAdmin: http://localhost:8080"
    echo "   Health Check: http://localhost:8000/up"
    echo ""
    echo "📋 Next steps:"
    echo "   1. Wait for containers to fully start (about 30 seconds)"
    echo "   2. Run: docker exec -it salary-app bash"
    echo "   3. Inside container, run: composer install"
    echo "   4. Then: php artisan key:generate"
    echo "   5. Then: php artisan migrate --seed"
    echo "   6. Finally: php artisan storage:link"
    echo ""
    echo "🔍 Check container status: docker-compose ps"
    echo "📊 View logs: docker-compose logs -f"
else
    echo "❌ Failed to start containers"
    exit 1
fi
