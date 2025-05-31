-- Tabla roles
CREATE TABLE `role` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` VARCHAR(255) NOT NULL
);

-- Tabla usuarios
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role_id` BIGINT NOT NULL,
    `dni` INT NOT NULL UNIQUE,
    FOREIGN KEY (`role_id`) REFERENCES `role`(`id`)
);

-- Tabla clientes
CREATE TABLE `client` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `type` ENUM('persona', 'empresa') NOT NULL,
    `identity_document` BIGINT NOT NULL UNIQUE,
    `type_document` ENUM('DNI', 'RUC', 'CE', 'PASAPORTE') NOT NULL,
    `phone` VARCHAR(255) NOT NULL
);

-- Tabla direcciones
CREATE TABLE `address` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `client_id` BIGINT NULL,
    `address_full` VARCHAR(255) NOT NULL,
    `district` VARCHAR(255) NOT NULL,
    `province` VARCHAR(255) NOT NULL,
    `department` VARCHAR(255) NOT NULL,
    `postal_code` VARCHAR(255) NOT NULL,
    `reference` VARCHAR(255) NOT NULL,
    `is_main` BOOLEAN NOT NULL,
    FOREIGN KEY (`client_id`) REFERENCES `client`(`id`)
);

-- Tabla productos
CREATE TABLE `product` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `sku` VARCHAR(255) NOT NULL UNIQUE,
    `image_url` VARCHAR(255) NOT NULL,
    `barcode` VARCHAR(255) NOT NULL UNIQUE,
    `brand` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `presentation` VARCHAR(255) NOT NULL,
    `unidad` VARCHAR(255) NOT NULL,
    `user_id` BIGINT NOT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
);

-- Tabla stock
CREATE TABLE `stock` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `product_id` BIGINT NOT NULL,
    `quantity` BIGINT NOT NULL,
    `min_stock` BIGINT NOT NULL,
    `max_stock` BIGINT NOT NULL,
    `purchase_price` DECIMAL(8,2) NOT NULL,
    `sale_price` DECIMAL(8,2) NOT NULL,
    FOREIGN KEY (`product_id`) REFERENCES `product`(`id`)
);

-- Movimientos de stock
CREATE TABLE `stock_movements` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `stock_id` BIGINT NOT NULL,
    `movement_type` ENUM('Entrada', 'Salida', 'Ajuste', 'Devolucion') NOT NULL,
    `quantity` INT NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `unit_price` DECIMAL(8,2) NOT NULL,
    `user_id` BIGINT NOT NULL,
    `vouche_number` VARCHAR(255) NULL,
    FOREIGN KEY (`stock_id`) REFERENCES `stock`(`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
);

-- Pedidos (renombrado de `order` a `orders`)
CREATE TABLE `orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT NOT NULL,
    `client_id` BIGINT NOT NULL,
    `delivery_address_id` BIGINT NOT NULL,
    `subtotal` DECIMAL(8,2) NOT NULL,
    `shipping_cost` DECIMAL(8,2) NOT NULL,
    `discount` DECIMAL(8,2) NOT NULL,
    `status` ENUM('pendiente', 'confirmado', 'procesando', 'enviado', 'entregado', 'cancelado') NOT NULL DEFAULT 'pendiente',
    `codigo_payment` VARCHAR(255) NULL,
    `observations` TEXT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
    FOREIGN KEY (`client_id`) REFERENCES `client`(`id`),
    FOREIGN KEY (`delivery_address_id`) REFERENCES `address`(`id`)
);

-- Detalles del pedido
CREATE TABLE `order_detail` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `product_id` BIGINT NOT NULL,
    `order_id` BIGINT NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(8,2) NOT NULL,
    `subtotal` DECIMAL(8,2) NOT NULL,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`),
    FOREIGN KEY (`product_id`) REFERENCES `product`(`id`)
);

-- Pagos
CREATE TABLE `payment` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `order_id` BIGINT NOT NULL,
    `payment_method` ENUM('Efectivo', 'Tarjeta', 'Yape', 'Plin', 'TC') NOT NULL,
    `payment_code` VARCHAR(255) NOT NULL,
    `document_type` ENUM('Boleta', 'Factura', 'Ticket', 'NC') NOT NULL DEFAULT 'Ticket',
    `nc_reference` VARCHAR(255) NULL,
    `observations` TEXT NULL,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`)
);
