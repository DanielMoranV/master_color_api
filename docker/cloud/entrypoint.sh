#!/bin/sh

set -e

echo "🚀 Starting Master Color API for Cloud Deployment"

# Configurar puerto dinámico (Railway/Render/Heroku)
if [ -n "$PORT" ]; then
    echo "📡 Using dynamic port: $PORT"
    sed -i "s/listen 8080;/listen $PORT;/g" /etc/nginx/nginx.conf
else
    echo "📡 Using default port: 8080"
fi

# Configurar permisos como usuario laravel
echo "🔧 Setting up permissions..."

# Crear directorios necesarios
mkdir -p /var/www/storage/logs
mkdir -p /var/www/storage/framework/cache
mkdir -p /var/www/storage/framework/sessions
mkdir -p /var/www/storage/framework/views
mkdir -p /var/www/bootstrap/cache

# Solo intentar cambiar permisos si tenemos acceso
if [ -w /var/www/storage ]; then
    chmod -R 775 /var/www/storage 2>/dev/null || true
fi

if [ -w /var/www/bootstrap/cache ]; then
    chmod -R 775 /var/www/bootstrap/cache 2>/dev/null || true
fi

echo "🔧 Setting up Laravel..."

# Generar clave de aplicación si no existe
if [ -z "$APP_KEY" ]; then
    echo "🔑 Generating application key..."
    php artisan key:generate --no-interaction --force
fi

# Generar secreto JWT si no existe
if [ -z "$JWT_SECRET" ]; then
    echo "🔒 Generating JWT secret..."
    php artisan jwt:secret --no-interaction --force
fi

# Crear enlace simbólico para storage
if [ ! -L /var/www/public/storage ]; then
    echo "🔗 Creating storage link..."
    php artisan storage:link --no-interaction || true
fi

# Esperar a que la base de datos esté lista (opcional, dependiendo del servicio)
if [ -n "$DATABASE_URL" ] || [ -n "$DB_HOST" ]; then
    echo "⏳ Waiting for database..."
    
    # Intentar conectar por un tiempo limitado
    for i in $(seq 1 30); do
        if php artisan migrate:status >/dev/null 2>&1; then
            echo "✅ Database is ready!"
            break
        fi
        echo "⏳ Database not ready, attempt $i/30..."
        sleep 2
    done
    
    # Ejecutar migraciones
    echo "📊 Running migrations..."
    php artisan migrate --no-interaction --force
    
    # Ejecutar seeders solo en entorno local/staging
    if [ "$APP_ENV" = "local" ] || [ "$APP_ENV" = "staging" ]; then
        echo "🌱 Running seeders..."
        php artisan db:seed --no-interaction --force || true
    fi
else
    echo "ℹ️ No database configuration found, skipping migrations"
fi

# Limpiar y optimizar
echo "⚡ Optimizing Laravel..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Cachear para producción
if [ "$APP_ENV" = "production" ]; then
    echo "📦 Caching for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

echo "🎉 Setup completed!"
echo "🚀 Starting services on port ${PORT:-8080}..."

# Iniciar supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf