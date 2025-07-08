#!/bin/bash

BACKEND_DIR="/home/maeldev/Code/master_color_api"
FRONTEND_DIR="/home/maeldev/Code/master_color_frontend"

BACKEND_PORT=8000
FRONTEND_PORT=5173

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

echo "🚀 Iniciando Laravel backend..."
cd "$BACKEND_DIR" || { echo "❌ No se pudo acceder al backend"; exit 1; }
php artisan serve --port=$BACKEND_PORT > storage/logs/laravel-serve.log 2>&1 &
LARAVEL_PID=$!

sleep 2

echo "⚙️ Iniciando Vite frontend..."
cd "$FRONTEND_DIR" || { echo "❌ No se pudo acceder al frontend"; kill $LARAVEL_PID; exit 1; }
npm run dev > vite.log 2>&1 &
VITE_PID=$!

sleep 2

echo "🌐 Iniciando ngrok para backend ($BACKEND_PORT)..."
ngrok http $BACKEND_PORT --log=stdout > /tmp/ngrok-backend.log &
NGROK_BACK_PID=$!

sleep 5

BACKEND_URL=$(get_ngrok_url $BACKEND_PORT)

if [[ -z "$BACKEND_URL" ]]; then
  echo "❌ No se pudo obtener URL de ngrok para backend"
  kill $LARAVEL_PID $VITE_PID $NGROK_BACK_PID
  exit 1
fi

echo "✅ Backend URL: $BACKEND_URL"

# Update .env in backend
cd "$BACKEND_DIR"
sed -i "s|^APP_URL=.*|APP_URL=$BACKEND_URL|" .env
sed -i "s|^APP_FRONTEND_URL=.*|APP_FRONTEND_URL=http://localhost:$FRONTEND_PORT|" .env

# Update .env in frontend
cd "$FRONTEND_DIR"
sed -i "s|^VITE_API_URL=.*|VITE_API_URL=$BACKEND_URL/api|" .env
sed -i "s|^VITE_APP_FRONTEND_URL=.*|VITE_APP_FRONTEND_URL=http://localhost:$FRONTEND_PORT|" .env

# Abrir frontend local
if command -v xdg-open &> /dev/null; then
  xdg-open "http://localhost:$FRONTEND_PORT"
fi

echo ""
echo "📡 Laravel en: $BACKEND_URL"
echo "💻 Vite en: http://localhost:$FRONTEND_PORT"
echo "📝 Variables de entorno actualizadas"

# Esperar procesos
wait $LARAVEL_PID
