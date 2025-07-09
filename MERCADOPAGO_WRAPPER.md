# MercadoPago Wrapper - Guía de Uso

## Resumen de Cambios

Este documento describe la nueva implementación del wrapper personalizado para MercadoPago que reemplaza la implementación anterior. El wrapper está inspirado en el paquete `gjae/laravel-mercadopago` pero adaptado para usar el SDK v3.5 y mantener toda la funcionalidad existente.

## Ventajas del Nuevo Wrapper

### 1. **Interfaz Simplificada**
```php
// Antes (implementación anterior)
$paymentService = app(PaymentService::class);
$result = $paymentService->createPaymentPreference($order);

// Ahora (nuevo wrapper)
$result = MercadoPago::setOrder($order)->begin(function($mp) use ($order) {
    foreach ($order->orderDetails as $detail) {
        $mp->addItem([
            'id' => (string) $detail->product->id,
            'title' => $detail->product->name,
            'quantity' => $detail->quantity,
            'price' => $detail->unit_price,
            'currency' => 'PEN',
        ]);
    }
});
```

### 2. **Mejor Organización del Código**
- Separación clara de responsabilidades
- Facade para acceso fácil
- Service Provider para inyección de dependencias
- Manejo de errores mejorado

### 3. **Compatibilidad Total**
- Usa el SDK v3.5 (más actualizado)
- Mantiene toda la funcionalidad existente
- Compatible con webhooks actuales
- Mismo formato de respuesta

## Componentes del Wrapper

### 1. **MercadoPagoWrapper (Servicio Principal)**
- `app/Services/MercadoPagoWrapper.php`
- Contiene toda la lógica de negocio
- Maneja la configuración del SDK
- Procesa pagos y webhooks

### 2. **MercadoPago (Facade)**
- `app/Facades/MercadoPago.php`
- Proporciona acceso estático al wrapper
- Interfaz limpia y fácil de usar

### 3. **MercadoPagoServiceProvider**
- `app/Providers/MercadoPagoServiceProvider.php`
- Registra el servicio en el contenedor
- Configuración de singleton

## Uso del Wrapper

### 1. **Crear Preferencia de Pago Simple**
```php
use App\Facades\MercadoPago;

$result = MercadoPago::begin(function($mp) {
    $mp->addItem([
        'id' => 'product-1',
        'title' => 'Producto de Prueba',
        'quantity' => 1,
        'price' => 100.00,
        'currency' => 'PEN',
    ]);
});

// Resultado
$result = [
    'id' => 'preference-id',
    'init_point' => 'https://...',
    'sandbox_init_point' => 'https://...',
    'items' => [...],
    'order_id' => null,
];
```

### 2. **Crear Preferencia con Múltiples Items**
```php
$result = MercadoPago::begin(function($mp) {
    $mp->addItem([
        'id' => 'product-1',
        'title' => 'Producto 1',
        'quantity' => 2,
        'price' => 50.00,
        'currency' => 'PEN',
    ]);
    
    $mp->addItem([
        'id' => 'product-2',
        'title' => 'Producto 2',
        'quantity' => 1,
        'price' => 75.00,
        'currency' => 'PEN',
    ]);
});
```

### 3. **Crear Preferencia con Orden Asociada**
```php
$order = Order::with('orderDetails.product')->find($orderId);

$result = MercadoPago::setOrder($order)->begin(function($mp) use ($order) {
    foreach ($order->orderDetails as $detail) {
        $mp->addItem([
            'id' => (string) $detail->product->id,
            'title' => $detail->product->name,
            'quantity' => $detail->quantity,
            'price' => $detail->unit_price,
            'currency' => 'PEN',
        ]);
    }
});
```

### 4. **Obtener Información de Pago**
```php
$paymentData = MercadoPago::getPayment($paymentId);

if ($paymentData) {
    $status = $paymentData['status'];
    $amount = $paymentData['transaction_amount'];
    $method = $paymentData['payment_method'];
}
```

### 5. **Procesar Webhook**
```php
// En el WebhookController
$result = MercadoPago::processWebhookNotification($request->all());
```

## Métodos Disponibles

### `begin(callable $callback): array`
Inicia una nueva transacción y ejecuta el callback para configurar items.

### `addItem(array $item): self`
Agrega un item a la preferencia de pago.

**Parámetros del item:**
- `id`: ID único del producto
- `title`: Nombre del producto
- `quantity` o `qtty`: Cantidad
- `price`: Precio unitario
- `currency`: Moneda (opcional, por defecto PEN)

### `setOrder(Order $order): self`
Asocia una orden con la preferencia de pago.

### `getPayment(string $paymentId): ?array`
Obtiene información de un pago desde MercadoPago.

### `processWebhookNotification(array $data): bool`
Procesa notificaciones webhook de MercadoPago.

### `isConfigured(): bool`
Verifica si MercadoPago está configurado correctamente.

### `getConfig(): array`
Obtiene la configuración actual de MercadoPago.

## Configuración

La configuración se mantiene igual en `config/mercadopago.php`:

```php
return [
    'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
    'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
    'sandbox' => env('MERCADOPAGO_SANDBOX', true),
    'success_url' => env('APP_FRONTEND_URL') . '/payment/success',
    'failure_url' => env('APP_FRONTEND_URL') . '/payment/failure',
    'pending_url' => env('APP_FRONTEND_URL') . '/payment/pending',
    'statement_descriptor' => 'MasterColor',
    'currency' => 'PEN',
    'country' => 'PE',
];
```

## Testing

### Comando de Prueba
```bash
php artisan mercadopago:test-wrapper --debug
```

Este comando prueba:
- Configuración del wrapper
- Creación de preferencias simples
- Múltiples items
- Integración con órdenes reales
- Recuperación de pagos

### Archivos Actualizados

1. **Controllers actualizados:**
   - `ClientOrderController.php` - Método `createPayment()`
   - `WebhookController.php` - Método `mercadoPago()`

2. **Archivos nuevos:**
   - `app/Services/MercadoPagoWrapper.php`
   - `app/Facades/MercadoPago.php`
   - `app/Providers/MercadoPagoServiceProvider.php`
   - `app/Console/Commands/TestMercadoPagoWrapper.php`

3. **Archivos de configuración:**
   - `bootstrap/providers.php` - Registra el service provider

## Migración

### Pasos para Migrar
1. ✅ Crear el wrapper personalizado
2. ✅ Actualizar controladores para usar el wrapper
3. ✅ Crear comandos de prueba
4. ⏳ Probar la integración
5. ⏳ Verificar que todo funciona correctamente

### Rollback
Si necesitas volver a la implementación anterior:
1. Restaurar los archivos originales de los controladores
2. Eliminar el service provider del `bootstrap/providers.php`
3. Eliminar los archivos del wrapper

## Beneficios

1. **Código más limpio y mantenible**
2. **Interfaz intuitiva inspirada en el paquete gjae**
3. **Mejor manejo de errores**
4. **Más fácil de testear**
5. **Compatible con versiones futuras del SDK**
6. **Mantiene toda la funcionalidad existente**