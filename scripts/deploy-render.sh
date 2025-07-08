#!/bin/bash

# Script de preparación para despliegue en Render
set -e

echo "🎨 Master Color API - Preparación para Render"
echo "============================================="

# Verificar que estamos en el directorio correcto
if [ ! -f "composer.json" ]; then
    echo "❌ Error: No se encuentra composer.json. Ejecuta desde el directorio raíz del proyecto."
    exit 1
fi

# Generar claves para variables de entorno
echo "🔑 Generando claves para variables de entorno..."

# Generar APP_KEY
APP_KEY=$(openssl rand -base64 32)
echo "APP_KEY=base64:$APP_KEY"

# Generar JWT_SECRET
JWT_SECRET=$(openssl rand -base64 64)
echo "JWT_SECRET=$JWT_SECRET"

# Crear archivo con variables de entorno para Render
cat > .env.render << EOF
# Variables de entorno para Render.com
# Copia estas variables a tu servicio en Render

APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:$APP_KEY
JWT_SECRET=$JWT_SECRET
LOG_LEVEL=info
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Configurar estas variables en Render:
# - DATABASE_URL (se genera automáticamente si usas PostgreSQL de Render)
# - REDIS_URL (se genera automáticamente si usas Redis de Render)
# - APP_URL (se genera automáticamente)
# - MERCADOPAGO_ACCESS_TOKEN
# - MERCADOPAGO_PUBLIC_KEY
# - MAIL_* (configurar según tu proveedor de email)
EOF

echo "✅ Archivo .env.render creado con las variables necesarias"

# Verificar archivos necesarios
echo "🔧 Verificando archivos necesarios..."

FILES=(
    "Dockerfile.cloud"
    "render.yaml"
    "docker/cloud/nginx.conf"
    "docker/cloud/entrypoint.sh"
)

for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file existe"
    else
        echo "❌ $file no encontrado"
    fi
done

# Verificar configuración de base de datos para PostgreSQL
echo "🔧 Verificando configuración para PostgreSQL..."

if [ ! -f "config/database.php" ]; then
    echo "❌ Error: config/database.php no encontrado"
    exit 1
fi

# Verificar que la configuración de PostgreSQL esté presente
if grep -q "pgsql" config/database.php; then
    echo "✅ Configuración de PostgreSQL encontrada"
else
    echo "⚠️  Advertencia: Asegúrate de que Laravel esté configurado para PostgreSQL"
fi

echo ""
echo "📋 PASOS PARA DESPLEGAR EN RENDER:"
echo "================================="
echo ""
echo "1. Ve a https://render.com y crea una cuenta"
echo ""
echo "2. Conecta tu repositorio GitHub"
echo ""
echo "3. Crea un nuevo Web Service con esta configuración:"
echo "   - Runtime: Docker"
echo "   - Dockerfile Path: ./Dockerfile.cloud"
echo "   - Health Check Path: /health"
echo ""
echo "4. Crear servicios adicionales:"
echo "   - PostgreSQL Database"
echo "   - Redis"
echo ""
echo "5. Configurar variables de entorno (usa .env.render como referencia)"
echo ""
echo "6. Variables importantes a configurar en Render:"
echo "   - APP_URL (se auto-genera)"
echo "   - DATABASE_URL (se auto-genera con PostgreSQL)"
echo "   - REDIS_URL (se auto-genera con Redis)"
echo "   - MERCADOPAGO_ACCESS_TOKEN"
echo "   - MERCADOPAGO_PUBLIC_KEY"
echo ""
echo "7. Desplegar!"
echo ""
echo "💡 Tip: Render deployará automáticamente cuando hagas push a tu rama main"
echo ""
echo "📄 Archivo de variables creado: .env.render"
echo "🔗 Documentación: https://render.com/docs"