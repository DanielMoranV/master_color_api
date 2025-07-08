#!/bin/bash

# Script de despliegue automático para Railway
set -e

echo "🚂 Master Color API - Deploy to Railway"
echo "======================================="

# Verificar que estamos en el directorio correcto
if [ ! -f "composer.json" ]; then
    echo "❌ Error: No se encuentra composer.json. Ejecuta desde el directorio raíz del proyecto."
    exit 1
fi

# Verificar que Railway CLI está instalado
if ! command -v railway &> /dev/null; then
    echo "❌ Error: Railway CLI no está instalado."
    echo "💡 Instala con: npm install -g @railway/cli"
    exit 1
fi

# Login a Railway
echo "🔐 Verificando autenticación con Railway..."
railway auth:login

# Seleccionar proyecto
echo "📋 Seleccionando proyecto Railway..."
railway project:select

# Verificar configuración
echo "🔧 Verificando configuración..."

# Verificar que existe el Dockerfile para cloud
if [ ! -f "Dockerfile.cloud" ]; then
    echo "❌ Error: No se encuentra Dockerfile.cloud"
    exit 1
fi

# Configurar variables de entorno esenciales
echo "⚙️ Configurando variables de entorno..."

# APP_KEY (generar si no existe)
if ! railway vars get APP_KEY &> /dev/null; then
    echo "🔑 Generando APP_KEY..."
    APP_KEY=$(openssl rand -base64 32)
    railway vars set APP_KEY="base64:$APP_KEY"
fi

# JWT_SECRET (generar si no existe)
if ! railway vars get JWT_SECRET &> /dev/null; then
    echo "🔒 Generando JWT_SECRET..."
    JWT_SECRET=$(openssl rand -base64 64)
    railway vars set JWT_SECRET="$JWT_SECRET"
fi

# Configurar variables básicas
railway vars set APP_ENV=production
railway vars set APP_DEBUG=false
railway vars set LOG_LEVEL=info
railway vars set CACHE_DRIVER=redis
railway vars set SESSION_DRIVER=redis
railway vars set QUEUE_CONNECTION=redis

echo "✅ Variables de entorno configuradas"

# Desplegar
echo "🚀 Desplegando a Railway..."
railway up --dockerfile Dockerfile.cloud

# Ejecutar migraciones
echo "📊 Ejecutando migraciones..."
railway run php artisan migrate --force

echo "🎉 ¡Despliegue completado!"
echo "🌐 Tu aplicación estará disponible en: $(railway domain)"

# Mostrar logs en tiempo real
echo "📄 Logs en tiempo real (Ctrl+C para salir):"
railway logs --follow