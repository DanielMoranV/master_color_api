# Dockerfile para Master Color API
FROM php:8.2-fpm

# Configurar argumentos para el build
ARG user=laravel
ARG uid=1000

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    nodejs \
    npm \
    nginx \
    supervisor \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Limpiar cache de apt
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Crear usuario del sistema para ejecutar comandos de Composer y Artisan
RUN useradd -G www-data,root -u $uid -d /home/$user $user
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

# Configurar directorio de trabajo
WORKDIR /var/www

# Copiar archivos de dependencias primero (para optimizar cache de Docker)
COPY composer.json composer.lock package.json package-lock.json ./

# Instalar dependencias PHP como root
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Instalar dependencias de Node.js
RUN npm ci --only=production

# Copiar el resto de la aplicación
COPY . .

# Copiar archivo de configuración de Nginx
COPY docker/nginx/default.conf /etc/nginx/sites-available/default

# Copiar configuración de Supervisor
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copiar script de entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Configurar permisos
RUN chown -R $user:www-data /var/www
RUN chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Generar assets de producción
RUN npm run build

# Optimizar Laravel para producción
RUN php artisan config:cache
RUN php artisan route:cache
RUN php artisan view:cache

# Cambiar al usuario no-root
USER $user

# Exponer puerto
EXPOSE 80

# Comando de inicio
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]