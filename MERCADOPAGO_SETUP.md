# Configuración de MercadoPago

## Paso 1: Obtener Credenciales de Prueba

1. Ve a tu cuenta de MercadoPago: https://www.mercadopago.com.pe/developers/
2. Ve a **"Tus integraciones"** → **"Credenciales"**
3. Selecciona **"Credenciales de prueba"**
4. Copia los valores de:
   - **Access Token de prueba**
   - **Public Key de prueba**

## Paso 2: Configurar Variables de Entorno

Agrega estas variables a tu archivo `.env`:

```bash
# MercadoPago Configuration
MERCADOPAGO_ACCESS_TOKEN=TEST-1234567890-123456-abcdef1234567890abcdef1234567890-123456789
MERCADOPAGO_PUBLIC_KEY=TEST-abcdef12-3456-7890-abcd-ef1234567890
MERCADOPAGO_SANDBOX=true

# Frontend URL for payment redirects
APP_FRONTEND_URL=http://localhost:5173
```

## Paso 3: Usuarios de Prueba

MercadoPago proporciona usuarios de prueba para simular pagos:

### Comprador de Prueba (para aprobar pagos)
- **Email**: test_user_123456789@testuser.com
- **Tarjeta**: 4009 1753 6280 8001
- **CVV**: 123
- **Fecha**: 11/25

### Comprador de Prueba (para rechazar pagos)
- **Tarjeta**: 4000 0000 0000 0002

## Paso 4: URLs de Retorno

El sistema está configurado para redirigir a:
- **Éxito**: `http://localhost:5173/payment/success`
- **Fallo**: `http://localhost:5173/payment/failure`
- **Pendiente**: `http://localhost:5173/payment/pending`

## Paso 5: Webhook URL

Para que MercadoPago notifique los pagos, configura en tu cuenta:
- **URL del Webhook**: `https://tu-dominio.com/api/webhooks/mercadopago`
- **Eventos**: `payment`

⚠️ **Importante**: En desarrollo local, usa ngrok o similar para exponer tu API.

## Paso 6: Migrar Base de Datos

Una vez configurado, ejecuta:

```bash
php artisan migrate:fresh --seed
```

## Endpoints Disponibles

### Para Clientes
- `POST /api/client/orders` - Crear orden
- `POST /api/client/orders/{id}/payment` - Generar link de pago
- `GET /api/payment-status/{orderId}` - Consultar estado

### Webhooks
- `POST /api/webhooks/mercadopago` - Recibir notificaciones

## Flujo de Prueba

1. Crear orden con productos del carrito
2. Generar link de pago
3. Ir a MercadoPago y pagar con tarjeta de prueba
4. El webhook procesará automáticamente el pago
5. El stock se descontará automáticamente
6. La orden cambiará a estado "pendiente"