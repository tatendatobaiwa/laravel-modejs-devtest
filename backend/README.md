# Salary Management System - Laravel Backend

This is the Laravel backend for the Salary Management System, providing a robust API for managing user salaries, commissions, and file uploads.

## Features

- **User Management**: Create, read, update, and delete users
- **Salary Management**: Handle salary in local currency and Euros
- **Commission System**: Default 500€ commission with admin override
- **File Upload**: Secure document storage for salary documents
- **History Tracking**: Complete audit trail of all salary changes
- **Bulk Operations**: Efficient bulk updates for multiple users
- **Search & Pagination**: Advanced filtering and pagination
- **CORS Support**: Frontend integration ready

## Tech Stack

- **Framework**: Laravel 10
- **Database**: MySQL 8.0
- **PHP Version**: 8.2+
- **Containerization**: Docker with Nginx + PHP-FPM
- **File Storage**: Laravel Storage with public disk

## Quick Start with Docker

### Prerequisites
- Docker Desktop installed and running
- Git

### 1. Clone and Setup
```bash
# Navigate to backend directory
cd backend

# Copy environment file
cp env.example .env

# Start Docker containers
docker-compose up -d --build
```

### 2. Install Dependencies
```bash
# Access the Laravel container
docker exec -it salary-app bash

# Install Composer dependencies
composer install

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate

# Seed the database
php artisan db:seed

# Create storage link
php artisan storage:link
```

### 3. Access the Application
- **API**: http://localhost:8000/api
- **phpMyAdmin**: http://localhost:8080
- **Health Check**: http://localhost:8000/up

## API Endpoints

### User Management
```
POST   /api/users              - Register/Update user
GET    /api/users              - List all users (with search & pagination)
GET    /api/users/{id}         - Get user details
PUT    /api/users/{id}         - Update user
DELETE /api/users/{id}         - Delete user
POST   /api/users/bulk-update  - Bulk update users
```

### Commission Management
```
GET    /api/commission         - Get commission settings
PUT    /api/commission         - Update commission amount
```

### Health Check
```
GET    /api/health             - API health status
```

## Database Schema

### Users Table
- `id` - Primary key
- `name` - User's full name
- `email` - Unique email address
- `password` - Hashed password
- `email_verified_at` - Email verification timestamp
- `created_at`, `updated_at` - Timestamps

### Salaries Table
- `id` - Primary key
- `user_id` - Foreign key to users
- `salary_local_currency` - Salary in local currency format
- `salary_euros` - Salary converted to Euros
- `commission` - Commission amount (default 500€)
- `document_path` - Path to uploaded salary document
- `notes` - Additional notes
- `created_at`, `updated_at` - Timestamps

### Salary History Table
- `id` - Primary key
- `user_id` - Foreign key to users
- `old_*`, `new_*` - Previous and new values
- `changed_by` - User who made the change
- `change_reason` - Reason for the change
- `created_at`, `updated_at` - Timestamps

### Commissions Table
- `id` - Primary key
- `amount` - Commission amount
- `is_active` - Whether this commission is active
- `description` - Description of the commission
- `created_at`, `updated_at` - Timestamps

## File Upload

The system supports secure file uploads for salary documents:

- **Supported Formats**: PDF, DOC, DOCX, XLS, XLSX
- **Maximum Size**: 10MB
- **Storage**: Public disk with organized folder structure
- **Security**: File type validation and secure naming

## Environment Variables

Key environment variables in `.env`:

```env
APP_NAME="Salary Management System"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=salary_management
DB_USERNAME=salary_user
DB_PASSWORD=password

FILESYSTEM_DISK=public
```

## Development

### Running Tests
```bash
# Inside the container
php artisan test
```

### Code Quality
```bash
# Laravel Pint for code style
./vendor/bin/pint

# Static analysis
./vendor/bin/phpstan analyse
```

### Database
```bash
# Reset database
php artisan migrate:fresh --seed

# Create new migration
php artisan make:migration create_table_name

# Rollback migrations
php artisan migrate:rollback
```

## Docker Services

- **app**: Laravel PHP-FPM application
- **webserver**: Nginx web server
- **db**: MySQL 8.0 database
- **phpmyadmin**: Database management interface

## Security Features

- **Input Validation**: Comprehensive request validation
- **SQL Injection Protection**: Eloquent ORM with parameterized queries
- **File Upload Security**: Type and size validation
- **CORS Configuration**: Proper cross-origin resource sharing
- **Database Transactions**: ACID compliance for critical operations

## Performance Optimizations

- **Database Indexing**: Strategic indexes on frequently queried columns
- **Eager Loading**: Prevents N+1 query problems
- **Pagination**: Efficient handling of large datasets
- **File Storage**: Optimized file handling and storage

## Troubleshooting

### Common Issues

1. **Permission Errors**: Ensure Docker has proper permissions
2. **Port Conflicts**: Check if ports 8000, 3306, 8080 are available
3. **Database Connection**: Verify MySQL service is running
4. **Storage Issues**: Run `php artisan storage:link` inside container

### Logs
```bash
# View Laravel logs
docker exec -it salary-app tail -f storage/logs/laravel.log

# View Nginx logs
docker exec -it salary-nginx tail -f /var/log/nginx/error.log
```

## Contributing

1. Follow PSR-12 coding standards
2. Write tests for new features
3. Update documentation as needed
4. Use conventional commit messages

## License

This project is part of the Salary Management System.
