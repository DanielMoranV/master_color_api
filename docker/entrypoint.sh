#!/bin/bash

set -e

echo "🚀 Starting Master Color API Docker Container"

# Función para esperar que la base de datos esté lista
wait_for_db() {
    echo "⏳ Waiting for database connection..."
    
    until php artisan migrate:status >/dev/null 2>&1; do
        echo "⏳ Database is unavailable - sleeping"
        sleep 5
    done
    
    echo "✅ Database is ready!"
}

# Función para configurar permisos
setup_permissions() {
    echo "🔧 Setting up permissions..."
    
    # Crear directorios si no existen
    mkdir -p /var/www/storage/logs
    mkdir -p /var/www/storage/framework/cache
    mkdir -p /var/www/storage/framework/sessions
    mkdir -p /var/www/storage/framework/views
    mkdir -p /var/www/bootstrap/cache
    
    # Configurar permisos
    chown -R www-data:www-data /var/www/storage
    chown -R www-data:www-data /var/www/bootstrap/cache
    chmod -R 775 /var/www/storage
    chmod -R 775 /var/www/bootstrap/cache
    
    echo "✅ Permissions configured!"
}

# Función para configurar Laravel
setup_laravel() {
    echo "🔧 Setting up Laravel..."
    
    # Generar clave de aplicación si no existe
    if [ -z "$APP_KEY" ]; then
        echo "🔑 Generating application key..."
        php artisan key:generate --no-interaction
    fi
    
    # Generar secreto JWT si no existe
    if [ -z "$JWT_SECRET" ]; then
        echo "🔒 Generating JWT secret..."
        php artisan jwt:secret --no-interaction
    fi
    
    # Crear enlace simbólico para storage
    if [ ! -L /var/www/public/storage ]; then
        echo "🔗 Creating storage link..."
        php artisan storage:link --no-interaction
    fi
    
    echo "✅ Laravel configured!"
}

# Función para ejecutar migraciones
run_migrations() {
    echo "📊 Running database migrations..."
    
    # Ejecutar migraciones
    php artisan migrate --no-interaction --force
    
    # Ejecutar seeders solo si es entorno local o development
    if [ "$APP_ENV" = "local" ] || [ "$APP_ENV" = "development" ]; then
        echo "🌱 Running database seeders..."
        php artisan db:seed --no-interaction --force
    fi
    
    echo "✅ Database migrations completed!"
}

# Función para optimizar Laravel
optimize_laravel() {
    echo "⚡ Optimizing Laravel..."
    
    # Limpiar cache
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    php artisan cache:clear
    
    # Si es producción, cachear configuraciones
    if [ "$APP_ENV" = "production" ]; then
        echo "📦 Caching for production..."
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
    fi
    
    echo "✅ Laravel optimized!"
}

# Función principal
main() {
    echo "🌟 Master Color API - Docker Container Initialization"
    echo "Environment: $APP_ENV"
    
    # Configurar permisos
    setup_permissions
    
    # Esperar a que la base de datos esté lista
    wait_for_db
    
    # Configurar Laravel
    setup_laravel
    
    # Ejecutar migraciones
    run_migrations
    
    # Optimizar Laravel
    optimize_laravel
    
    echo "🎉 Container initialization completed!"
    echo "🚀 Starting services..."
    
    # Iniciar supervisor que maneja nginx y php-fpm
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
}

# Ejecutar función principal
main "$@"