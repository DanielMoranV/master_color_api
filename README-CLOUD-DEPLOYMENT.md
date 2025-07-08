# Master Color API - Despliegue en la Nube ☁️

Esta guía te ayudará a desplegar Master Color API en servicios cloud como Railway, Render, y otros.

## 🎯 Resumen de Cambios

Tu proyecto ahora incluye:

- ✅ **Dockerfile optimizado** para single container deployment
- ✅ **Configuraciones específicas** para Railway y Render
- ✅ **Scripts automatizados** de despliegue
- ✅ **Variables de entorno** pre-configuradas
- ✅ **Manejo de puertos dinámicos**
- ✅ **Optimizaciones de performance**

## 🚂 DESPLIEGUE EN RAILWAY

### Requisitos Previos
```bash
# Instalar Railway CLI
npm install -g @railway/cli

# Verificar instalación
railway --version
```

### Despliegue Automático
```bash
# Ejecutar script de despliegue
./scripts/deploy-railway.sh
```

### Despliegue Manual

1. **Login y configuración inicial**:
```bash
railway login
railway init
```

2. **Configurar servicios necesarios**:
```bash
# Agregar MySQL
railway add mysql

# Agregar Redis  
railway add redis
```

3. **Configurar variables de entorno**:
```bash
# Variables básicas
railway vars set APP_ENV=production
railway vars set APP_DEBUG=false
railway vars set CACHE_DRIVER=redis
railway vars set SESSION_DRIVER=redis

# Generar claves (ejecutar una sola vez)
railway vars set APP_KEY="base64:$(openssl rand -base64 32)"
railway vars set JWT_SECRET="$(openssl rand -base64 64)"

# MercadoPago
railway vars set MERCADOPAGO_ACCESS_TOKEN="tu_access_token"
railway vars set MERCADOPAGO_PUBLIC_KEY="tu_public_key"
railway vars set MERCADOPAGO_SANDBOX=false
```

4. **Desplegar**:
```bash
railway up --dockerfile Dockerfile.cloud
```

5. **Ejecutar migraciones**:
```bash
railway run php artisan migrate --force
```

### URLs y Acceso
- **API**: `https://tu-proyecto.railway.app`
- **Health Check**: `https://tu-proyecto.railway.app/health`
- **Logs**: `railway logs --follow`

## 🎨 DESPLIEGUE EN RENDER

### Preparación
```bash
# Ejecutar script de preparación
./scripts/deploy-render.sh
```

### Configuración Manual

1. **Crear cuenta en Render.com**

2. **Conectar repositorio GitHub**

3. **Crear servicios**:

   **a) Base de datos PostgreSQL**:
   - Tipo: PostgreSQL
   - Plan: Free/Starter
   - Región: Oregon

   **b) Redis**:
   - Tipo: Redis
   - Plan: Free/Starter
   - Región: Oregon

   **c) Web Service**:
   - Tipo: Web Service
   - Runtime: Docker
   - Dockerfile Path: `./Dockerfile.cloud`
   - Build Command: (dejar vacío)
   - Start Command: (dejar vacío)
   - Health Check Path: `/health`

4. **Variables de entorno**:
```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:TU_CLAVE_GENERADA
JWT_SECRET=TU_JWT_SECRET
LOG_LEVEL=info
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Estas se auto-generan en Render:
# DATABASE_URL=postgresql://...
# REDIS_URL=redis://...
# APP_URL=https://...

# Configurar manualmente:
MERCADOPAGO_ACCESS_TOKEN=tu_token
MERCADOPAGO_PUBLIC_KEY=tu_public_key
MERCADOPAGO_SANDBOX=false
```

### URLs y Acceso
- **API**: `https://tu-servicio.onrender.com`
- **Health Check**: `https://tu-servicio.onrender.com/health`

## 🌍 OTROS SERVICIOS CLOUD

### Heroku
```bash
# Usar Dockerfile.cloud
echo "web: /usr/local/bin/entrypoint.sh" > Procfile

# Configurar buildpack
heroku buildpacks:set heroku/php

# Agregar addons
heroku addons:create heroku-postgresql:hobby-dev
heroku addons:create heroku-redis:hobby-dev
```

### DigitalOcean App Platform
```yaml
# .do/app.yaml
name: master-color-api
services:
- name: api
  source_dir: /
  github:
    repo: tu-usuario/tu-repo
    branch: main
  run_command: /usr/local/bin/entrypoint.sh
  dockerfile_path: Dockerfile.cloud
  http_port: 8080
  instance_count: 1
  instance_size_slug: basic-xxs
```

## ⚙️ CONFIGURACIONES IMPORTANTES

### 1. Base de Datos

**MySQL (Railway)**:
```env
DB_CONNECTION=mysql
DB_HOST=containers-us-west-X.railway.app
DB_PORT=XXXX
DB_DATABASE=railway
DB_USERNAME=root
DB_PASSWORD=XXXX
```

**PostgreSQL (Render)**:
```env
DB_CONNECTION=pgsql
DATABASE_URL=postgresql://user:pass@host:port/db
```

### 2. Storage de Archivos

Para **producción**, configura almacenamiento en la nube:

**AWS S3**:
```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=tu_access_key
AWS_SECRET_ACCESS_KEY=tu_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=tu_bucket
```

**Cloudinary** (alternativa):
```env
CLOUDINARY_URL=cloudinary://key:secret@cloud_name
```

### 3. Email en Producción

**SendGrid**:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=tu_api_key
MAIL_ENCRYPTION=tls
```

**Mailgun**:
```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=tu-dominio.com
MAILGUN_SECRET=tu_secret_key
```

## 🔍 DEBUGGING Y MONITOREO

### Ver Logs
```bash
# Railway
railway logs --follow

# Render (via dashboard)
# https://dashboard.render.com -> tu-servicio -> Logs
```

### Health Checks
```bash
# Verificar que la aplicación esté corriendo
curl https://tu-app.com/health

# Debería retornar: "healthy"
```

### Comandos Útiles
```bash
# Railway - Ejecutar comandos
railway run php artisan migrate:status
railway run php artisan cache:clear
railway run php artisan queue:work

# Render - SSH (planes pagos)
render ssh tu-servicio
```

## 🚨 SOLUCIÓN DE PROBLEMAS

### Errores Comunes

1. **Puerto no configurado**:
   - Verifica que tu Dockerfile.cloud exponga el puerto 8080
   - Asegúrate de que el entrypoint maneje la variable `$PORT`

2. **Base de datos no conecta**:
   - Verifica las variables de entorno DB_*
   - Asegúrate de que los servicios estén en la misma región

3. **Redis no conecta**:
   - Verifica REDIS_HOST/REDIS_URL
   - Confirma que el servicio Redis esté activo

4. **Archivos no persisten**:
   - Configura almacenamiento en la nube (S3, Cloudinary)
   - No uses almacenamiento local en producción

5. **MercadoPago webhooks no llegan**:
   - Verifica que APP_URL esté configurado correctamente
   - Asegúrate de estar usando tokens de producción

### Logs Importantes
```bash
# Ver logs de la aplicación
tail -f storage/logs/laravel.log

# Ver logs de Nginx
tail -f /var/log/nginx/error.log

# Ver logs de PHP-FPM
tail -f /var/log/nginx/fpm-access.log
```

## 📊 OPTIMIZACIÓN DE PERFORMANCE

### 1. Cache de Laravel
```bash
# En producción, estos comandos se ejecutan automáticamente
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 2. OPcache
El Dockerfile ya incluye OPcache configurado para producción.

### 3. Compresión
Nginx está configurado con gzip para todos los assets.

### 4. CDN
Para mejor performance, configura un CDN:
- **Cloudflare** (recomendado)
- **AWS CloudFront**
- **KeyCDN**

## 🔒 SEGURIDAD EN PRODUCCIÓN

### Variables de Entorno Críticas
```env
# NUNCA hardcodear estas variables
APP_KEY=base64:...
JWT_SECRET=...
DB_PASSWORD=...
MERCADOPAGO_ACCESS_TOKEN=...
AWS_SECRET_ACCESS_KEY=...
```

### Headers de Seguridad
Ya configurados en `nginx.conf`:
- X-Frame-Options
- X-XSS-Protection
- X-Content-Type-Options
- Referrer-Policy

### HTTPS
Todos los servicios cloud proporcionan HTTPS automático.

## 📋 CHECKLIST PRE-DESPLIEGUE

- [ ] Variables de entorno configuradas
- [ ] Base de datos creada y configurada
- [ ] Redis configurado
- [ ] Storage en la nube configurado (S3/Cloudinary)
- [ ] Servicio de email configurado
- [ ] Tokens de MercadoPago de producción
- [ ] Health check funcionando
- [ ] Migraciones ejecutadas
- [ ] HTTPS configurado

## 🎉 POST-DESPLIEGUE

1. **Verificar funcionalidad**:
   - Health check: `/health`
   - API endpoints: `/api/`
   - Documentación: `/api/documentation`

2. **Configurar monitoreo**:
   - Uptime monitoring
   - Error tracking (Sentry)
   - Performance monitoring

3. **Configurar backups**:
   - Base de datos automática
   - Archivos en la nube

¡Tu aplicación Master Color API ahora está lista para producción! 🚀