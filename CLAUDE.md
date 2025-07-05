# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Master Color API is a Laravel 12 REST API for inventory management and e-commerce operations. It serves as the backend for a paint/color store with comprehensive inventory management, user roles, and shopping cart functionality.

## Key Technologies

- **Laravel 12** with PHP 8.2+
- **JWT Authentication** (`tymon/jwt-auth`)
- **MySQL 8.0+** database
- **PestPHP** for testing
- **Vite** with TailwindCSS for frontend assets

## Development Commands

### Setup and Installation
```bash
# Initial setup
composer install && npm install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate --seed

# Start development (runs server + queue + vite concurrently)
composer run dev

# Individual services
php artisan serve
php artisan queue:listen
npm run dev
```

### Testing and Code Quality
```bash
# Run tests
composer test
# Or directly: php artisan test

# Generate IDE helper files
php artisan ide-helper:generate
php artisan ide-helper:models
php artisan ide-helper:meta

# Format code
vendor/bin/pint

# Clear caches
php artisan config:clear
php artisan cache:clear
```

### Build and Deployment
```bash
# Production build
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Architecture Overview

### Authentication System
- **Dual JWT Authentication**: Separate systems for staff users (`/api/auth/`) and clients (`/api/client/auth/`)
- **Role-based Access**: Admin, Seller, Warehouse staff roles
- **JWT Guards**: `api` for staff, `client` for customers

### Service Layer Pattern
Business logic is centralized in service classes:
- `ProductService`: Product management and validation
- `StockService`: Inventory operations and stock movements
- `FileUploadService`: Image upload handling
- `OrderService`: Order processing and status management

### Database Structure
**Core Models with Relationships:**
- `User` (staff) ↔ `Role` (many-to-many)
- `Client` (customers) ↔ `Address` (one-to-many)
- `Product` ↔ `Stock` (one-to-one)
- `Order` ↔ `OrderDetail` (one-to-many)
- `StockMovement` ↔ `StockMovementDetail` (one-to-many)

### API Response Format
All responses use `ApiResponseClass` for consistency:
```php
// Success: ApiResponseClass::sendResponse($data, $message, $code)
// Error: ApiResponseClass::sendError($message, $errors, $code)
```

### Key Directories
- `app/Services/`: Business logic services
- `app/Http/Controllers/`: API controllers grouped by functionality
- `app/Http/Resources/`: Response transformers
- `app/Http/Requests/`: Validation request classes
- `app/Models/`: Eloquent models with relationships
- `database/seeders/`: Default data including roles and sample products

## Development Notes

### File Upload Handling
Images are processed via `FileUploadService` with validation and storage management in `/storage/app/public/`.

### Stock Management
Stock movements are tracked through `StockMovement` and `StockMovementDetail` models. All inventory changes create audit trails.

### API Documentation
Swagger documentation available at `/api/documentation` when running locally.

### Testing
Uses PestPHP for testing. Test files follow the pattern `tests/Feature/` for integration tests and `tests/Unit/` for unit tests.

### Database Seeding
Run `php artisan migrate --seed` to populate default roles, users, and sample products for development.