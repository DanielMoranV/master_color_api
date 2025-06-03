# 🎨 Master Color API

<div align="center">
  <p>
    <a href="#">
      <img src="https://img.shields.io/badge/version-1.0.0-blue" alt="Version">
    </a>
    <a href="LICENSE">
      <img src="https://img.shields.io/badge/license-MIT-green" alt="License">
    </a>
    <a href="https://laravel.com">
      <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel" alt="Laravel">
    </a>
    <a href="#">
      <img src="https://img.shields.io/badge/API-REST-4CAF50" alt="REST API">
    </a>
  </p>
</div>

## 📋 Descripción

**Master Color API** es un sistema de gestión de inventario y tienda virtual desarrollado con Laravel 12. Esta API REST proporciona un conjunto completo de endpoints para administrar productos, inventario, pedidos, usuarios y clientes, con un sistema de autenticación JWT y roles de usuario.

## 🚀 Características Principales

- 🔐 Autenticación JWT segura
- 👥 Múltiples roles de usuario (Admin, Vendedor, Almacén, Cliente)
- 📦 Gestión completa de productos y categorías
- 📊 Control de inventario en tiempo real
- 🛒 Carrito de compras integrado
- 📦 Sistema de pedidos con seguimiento
- 📊 Reportes y estadísticas
- ✉️ Sistema de notificaciones por email
- 📱 API RESTful con respuestas estandarizadas

## 🛠️ Requisitos Técnicos

- PHP 8.2 o superior
- Composer
- MySQL 8.0+
- Node.js 18+ (para assets)
- Servidor web (Apache/Nginx) con mod_rewrite habilitado

## 🚀 Instalación

1. **Clonar el repositorio**
   ```bash
   git clone https://github.com/DanielMoranV/master_color_api
   git clone 
   cd master_color_api
   ```

2. **Instalar dependencias**
   ```bash
   composer install
   npm install
   ```

3. **(Opcional, recomendado para desarrollo) Instalar Laravel IDE Helper**
   
   Si deseas autocompletado avanzado en tu IDE:
   ```bash
   composer require --dev barryvdh/laravel-ide-helper
   php artisan ide-helper:generate
   ```
   Puedes consultar la [documentación oficial](https://github.com/barryvdh/laravel-ide-helper) para más opciones.

   **Comandos útiles de IDE Helper:**
   > Los comandos deben escribirse en inglés, por ejemplo:
   ```bash
   php artisan ide-helper:generate   # Genera el archivo _ide_helper.php
   php artisan ide-helper:meta       # Genera el archivo .phpstorm.meta.php
   php artisan ide-helper:models     # Genera anotaciones para los modelos (añade --nowrite para solo mostrar en consola)
   ```

3. **Configuración del entorno**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configurar base de datos**
   Crear una base de datos MySQL y actualizar el archivo `.env` con las credenciales correspondientes.

5. **Ejecutar migraciones y seeders**
   ```bash
   php artisan migrate --seed
   ```

6. **Iniciar el servidor**
   ```bash
   php artisan serve
   ```

## 📚 Documentación de la API

La documentación completa de la API está disponible en formato OpenAPI (Swagger) en:

```
http://localhost:8000/api/documentation
```

## 📊 Estructura de Respuestas

### Respuesta Exitosa
```json
{
  "success": true,
  "data": {},
  "message": "Operación exitosa",
  "code": 200
}
```

### Respuesta de Error
```json
{
  "success": false,
  "data": null,
  "message": "Descripción del error",
  "errors": {},
  "code": 400
}
```

## 🔐 Autenticación

La API utiliza JWT (JSON Web Tokens) para autenticación. Incluye el token en el header de tus solicitudes:

```
Authorization: Bearer {token}
```

## 👥 Roles y Permisos

- **Admin**: Acceso total al sistema
- **Vendedor**: Gestión de pedidos y clientes
- **Almacén**: Gestión de inventario y stock
- **Cliente**: Realizar compras y ver sus pedidos

## 📦 Endpoints Principales

### Autenticación
- `POST /api/auth/login` - Iniciar sesión
- `POST /api/auth/register` - Registrarse
- `POST /api/auth/logout` - Cerrar sesión
- `POST /api/auth/refresh` - Refrescar token
- `POST /api/auth/forgot-password` - Recuperar contraseña
- `POST /api/auth/reset-password` - Restablecer contraseña

### Productos
- `GET /api/products` - Listar productos
- `GET /api/products/{id}` - Ver producto
- `POST /api/products` - Crear producto (Admin/Almacén)
- `PUT /api/products/{id}` - Actualizar producto (Admin/Almacén)
- `DELETE /api/products/{id}` - Eliminar producto (Admin)

### Pedidos
- `GET /api/orders` - Listar pedidos
- `POST /api/orders` - Crear pedido
- `GET /api/orders/{id}` - Ver pedido
- `PUT /api/orders/{id}/status` - Actualizar estado (Admin/Vendedor)

### Carrito
- `GET /api/cart` - Ver carrito
- `POST /api/cart/add` - Añadir producto
- `PUT /api/cart/update/{product_id}` - Actualizar cantidad
- `DELETE /api/cart/remove/{product_id}` - Eliminar producto

## 🧪 Testing

Para ejecutar las pruebas:

```bash
php artisan test
```

## 📧 Notificaciones

El sistema envía notificaciones automáticas para:
- Registro de usuarios
- Recuperación de contraseña
- Cambios de estado en pedidos
- Alertas de stock bajo
- Confirmación de pedidos

## 🤝 Contribución

Las contribuciones son bienvenidas. Por favor, lee nuestras [pautas de contribución](CONTRIBUTING.md) antes de enviar un pull request.

## 📄 Licencia

Este proyecto está licenciado bajo la [Licencia MIT](LICENSE).

---

<div align="center">
  <p>Desarrollado con ❤️ por el equipo de Master Color</p>
  <p>© 2025 Master Color - Todos los derechos reservados</p>
</div>
