-- Script de inicialización de base de datos para Docker
-- Este script se ejecuta automáticamente cuando se inicia el contenedor de MySQL

-- Crear la base de datos si no existe
CREATE DATABASE IF NOT EXISTS `master_color_api` 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

-- Crear usuario para la aplicación si no existe
CREATE USER IF NOT EXISTS 'laravel'@'%' IDENTIFIED BY 'password';

-- Otorgar permisos al usuario
GRANT ALL PRIVILEGES ON `master_color_api`.* TO 'laravel'@'%';

-- Crear base de datos de testing
CREATE DATABASE IF NOT EXISTS `master_color_api_testing` 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

-- Otorgar permisos para testing
GRANT ALL PRIVILEGES ON `master_color_api_testing`.* TO 'laravel'@'%';

-- Aplicar cambios
FLUSH PRIVILEGES;

-- Mostrar información de la configuración
SELECT 'Database initialization completed' AS status;