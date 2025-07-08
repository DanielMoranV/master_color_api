# Master Color API - Configuración Docker

Este documento describe cómo ejecutar Master Color API usando Docker y Docker Compose.

## 📋 Requisitos Previos

- Docker Engine 20.10+
- Docker Compose 2.0+
- Al menos 4GB de RAM disponible
- Puertos disponibles: 8000, 3306, 6379, 8080, 8025, 1025

## 🚀 Inicio Rápido

### 1. Clonar el repositorio
```bash
git clone <tu-repositorio>
cd master_color_api
```

### 2. Configurar variables de entorno
```bash
# Copiar archivo de ejemplo
cp .env.docker .env

# Editar configuraciones según tu entorno
nano .env
```

### 3. Construir y ejecutar contenedores
```bash
# Construir imágenes y ejecutar servicios
docker-compose up --build -d

# Ver logs en tiempo real
docker-compose logs -f
```

### 4. Verificar instalación
```bash
# Verificar que todos los servicios estén corriendo
docker-compose ps

# Probar la API
curl http://localhost:8000/api/health
```

## 🏗️ Arquitectura de Contenedores

### Servicios Incluidos

| Servicio | Puerto | Descripción |
|----------|--------|-------------|
| **app** | - | Aplicación Laravel con PHP-FPM |
| **nginx** | 8000, 8443 | Servidor web Nginx |
| **db** | 3306 | Base de datos MySQL 8.0 |
| **redis** | 6379 | Cache y sesiones Redis |
| **queue** | - | Worker para colas de Laravel |
| **scheduler** | - | Cron jobs de Laravel |
| **phpmyadmin** | 8080 | Interface web para MySQL |
| **mailhog** | 8025, 1025 | Captura de emails para desarrollo |

### URLs de Acceso

- **API**: http://localhost:8000
- **PhpMyAdmin**: http://localhost:8080
- **MailHog**: http://localhost:8025
- **API Docs**: http://localhost:8000/api/documentation

## ⚙️ Configuración

### Variables de Entorno Importantes

```env
# URLs de la aplicación
APP_URL=http://localhost:8000
APP_FRONTEND_URL=http://localhost:5173

# Base de datos (configurado para Docker)
DB_HOST=db
DB_DATABASE=master_color_api
DB_USERNAME=laravel
DB_PASSWORD=password

# Redis (configurado para Docker)
REDIS_HOST=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Mail (usando Mailhog para desarrollo)
MAIL_HOST=mailhog
MAIL_PORT=1025

# MercadoPago
MERCADOPAGO_ACCESS_TOKEN=tu_access_token
MERCADOPAGO_PUBLIC_KEY=tu_public_key
MERCADOPAGO_SANDBOX=true
```

## 🛠️ Comandos Útiles

### Gestión de Contenedores

```bash
# Iniciar servicios
docker-compose up -d

# Parar servicios
docker-compose down

# Reconstruir servicios
docker-compose up --build -d

# Ver logs
docker-compose logs -f [servicio]

# Reiniciar un servicio específico
docker-compose restart app
```

### Comandos de Laravel

```bash
# Ejecutar comandos Artisan
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed
docker-compose exec app php artisan queue:work

# Acceder al shell del contenedor
docker-compose exec app bash

# Ejecutar tests
docker-compose exec app php artisan test
```

### Gestión de Base de Datos

```bash
# Ejecutar migraciones
docker-compose exec app php artisan migrate

# Ejecutar seeders
docker-compose exec app php artisan db:seed

# Resetear base de datos
docker-compose exec app php artisan migrate:fresh --seed

# Backup de base de datos
docker-compose exec db mysqldump -u laravel -ppassword master_color_api > backup.sql

# Restaurar backup
docker-compose exec -T db mysql -u laravel -ppassword master_color_api < backup.sql
```

## 🔧 Desarrollo

### Hot Reload para Frontend

Si estás desarrollando el frontend por separado:

```bash
# En tu proyecto frontend
npm run dev -- --host 0.0.0.0 --port 5173

# Actualizar APP_FRONTEND_URL en .env
APP_FRONTEND_URL=http://localhost:5173
```

### Debugging

```bash
# Ver logs de aplicación
docker-compose logs -f app

# Ver logs de Nginx
docker-compose logs -f nginx

# Ver logs de base de datos
docker-compose logs -f db

# Monitorear performance
docker stats
```

### Acceso a Servicios Internos

```bash
# Conectar a MySQL desde el host
mysql -h 127.0.0.1 -P 3306 -u laravel -p master_color_api

# Conectar a Redis desde el host
redis-cli -h 127.0.0.1 -p 6379

# Acceder a PhpMyAdmin
# http://localhost:8080
# Usuario: laravel
# Contraseña: password
```

## 🚀 Producción

### Configuraciones para Producción

1. **Variables de entorno**:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com

# Base de datos externa recomendada
DB_HOST=tu-db-host.com
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password_segura

# Redis externo recomendado
REDIS_HOST=tu-redis-host.com
```

2. **Docker Compose para producción**:
```bash
# Usar archivo específico para producción
docker-compose -f docker-compose.prod.yml up -d
```

3. **Configurar HTTPS**:
```bash
# Agregar certificados SSL
mkdir -p docker/nginx/ssl
# Copiar certificados a docker/nginx/ssl/
```

## 🔒 Seguridad

### Recomendaciones de Seguridad

1. **Cambiar contraseñas por defecto**
2. **Configurar firewalls apropiados**
3. **Usar HTTPS en producción**
4. **Configurar backups automáticos**
5. **Monitorear logs de seguridad**

### Backup y Restauración

```bash
# Script de backup automático
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
docker-compose exec db mysqldump -u laravel -ppassword master_color_api > backup_$DATE.sql
docker cp master-color-app:/var/www/storage/app storage_backup_$DATE
```

## 🐛 Solución de Problemas

### Problemas Comunes

1. **Puerto ocupado**:
```bash
# Cambiar puertos en docker-compose.yml
ports:
  - "8001:80"  # En lugar de 8000:80
```

2. **Permisos de archivos**:
```bash
# Reiniciar con permisos corregidos
docker-compose down
docker-compose up --build -d
```

3. **Base de datos no conecta**:
```bash
# Verificar logs de MySQL
docker-compose logs db

# Verificar configuración de red
docker-compose exec app ping db
```

4. **Cache de configuración**:
```bash
# Limpiar cache de Laravel
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
```

## 📊 Monitoreo

### Health Checks

```bash
# Verificar estado de servicios
curl http://localhost:8000/health

# Verificar base de datos
docker-compose exec app php artisan migrate:status

# Verificar Redis
docker-compose exec app php artisan cache:clear
```

### Logs Centralizados

Todos los logs están disponibles a través de Docker:

```bash
# Logs de aplicación
docker-compose logs -f app

# Logs combinados
docker-compose logs -f

# Logs con timestamps
docker-compose logs -f -t
```

## 🤝 Contribución

Para contribuir al proyecto:

1. Fork el repositorio
2. Crea una rama feature
3. Ejecuta tests: `docker-compose exec app php artisan test`
4. Envía un pull request

## 📞 Soporte

Si encuentras problemas:

1. Revisa los logs: `docker-compose logs -f`
2. Verifica la configuración: `.env`
3. Consulta este README
4. Crea un issue en el repositorio

---

**Master Color API** - Sistema de gestión de inventario y e-commerce dockerizado 🐳