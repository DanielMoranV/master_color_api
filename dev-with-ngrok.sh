#!/bin/bash

# 🎯 Rutas de tus proyectos
BACKEND_DIR="/home/maeldev/Code/master_color_api"
FRONTEND_DIR="/home/maeldev/Code/master_color_frontend"

BACKEND_PORT=8000
FRONTEND_PORT=5173

# 🔄 Función para obtener URL pública de ngrok para un puerto
get_ngrok_url() {
  local port=$1
  local retries=5
  local delay=2
  local url=""

  for i in $(seq 1 $retries); do
    url=$(curl -s http://localhost:4040/api/tunnels | jq -r ".tunnels[] | select(.config.addr | test(\":$port\")) | .public_url")
    if [[ -n "$url" ]]; then break; fi
    sleep $delay
  done

  echo $url
}

# 🟢 Iniciar Laravel
echo "🚀 Iniciando Laravel en $BACKEND_DIR..."
cd "$BACKEND_DIR" || { echo "❌ No se pudo acceder al backend"; exit 1; }
php artisan serve --port=$BACKEND_PORT > storage/logs/laravel-serve.log 2>&1 &
LARAVEL_PID=$!

sleep 2

# 🟢 Iniciar Vite
echo "⚙️ Iniciando Vite (Vue 3) en $FRONTEND_DIR..."
cd "$FRONTEND_DIR" || { echo "❌ No se pudo acceder al frontend"; kill $LARAVEL_PID; exit 1; }
npm run dev > vite.log 2>&1 &
VITE_PID=$!

sleep 2

# 🟢 ngrok backend
echo "🌐 Iniciando ngrok para backend ($BACKEND_PORT)..."
ngrok http $BACKEND_PORT --log=stdout > /tmp/ngrok-backend.log &
NGROK_BACK_PID=$!

sleep 2

# 🟢 ngrok frontend
echo "🌐 Iniciando ngrok para frontend ($FRONTEND_PORT)..."
ngrok http $FRONTEND_PORT --log=stdout > /tmp/ngrok-frontend.log &
NGROK_FRONT_PID=$!

# Esperar y capturar URLs
sleep 5
BACKEND_URL=$(get_ngrok_url $BACKEND_PORT)
FRONTEND_URL=$(get_ngrok_url $FRONTEND_PORT)

if [[ -z "$BACKEND_URL" || -z "$FRONTEND_URL" ]]; then
  echo "❌ No se pudo obtener URLs de ngrok"
  kill $LARAVEL_PID $VITE_PID $NGROK_BACK_PID $NGROK_FRONT_PID
  exit 1
fi

echo "✅ Backend URL: $BACKEND_URL"
echo "✅ Frontend URL: $FRONTEND_URL"

# ✍️ Actualizar .env del backend
cd "$BACKEND_DIR"
echo "🔧 Actualizando .env del backend..."
sed -i "s|^APP_URL=.*|APP_URL=$BACKEND_URL|" .env
sed -i "s|^APP_FRONTEND_URL=.*|APP_FRONTEND_URL=$FRONTEND_URL|" .env

# ✍️ Actualizar .env del frontend
cd "$FRONTEND_DIR"
echo "🔧 Actualizando .env del frontend..."
sed -i "s|^VITE_API_URL=.*|VITE_API_URL=$BACKEND_URL/api|" .env
sed -i "s|^VITE_APP_FRONTEND_URL=.*|VITE_APP_FRONTEND_URL=$FRONTEND_URL|" .env

# 🌍 Abrir frontend en navegador
if command -v xdg-open &> /dev/null; then
  xdg-open "$FRONTEND_URL"
fi

# ✅ Resumen
echo ""
echo "📡 Laravel corriendo en: $BACKEND_URL"
echo "💻 Vite corriendo en: $FRONTEND_URL"
echo "📝 Variables .env actualizadas correctamente"

# 🧩 Esperar procesos
wait $LARAVEL_PID
