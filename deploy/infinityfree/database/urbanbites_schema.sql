-- ====================================================================
-- URBAN BITES - SISTEMA DE GESTIÓN DE INVENTARIO, VENTAS Y AUDITORÍA
-- Esquema para Despliegue en InfinityFree (phpMyAdmin)
-- No incluye CREATE DATABASE ni USE: la base ya está seleccionada en el panel.
-- ====================================================================

-- 1. TABLA ROLES
CREATE TABLE IF NOT EXISTS roles (
    id_rol INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (id_rol, nombre, descripcion) VALUES
(1, 'administrador', 'Acceso total: usuarios, productos, precios, inventario, reportes y auditoría'),
(2, 'vendedor', 'Acceso al punto de venta (POS), catálogo de productos y registro de ventas'),
(3, 'cliente', 'Acceso a catálogo de menú y pedidos en línea')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- 2. TABLA USUARIOS
CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_rol INT UNSIGNED NOT NULL DEFAULT 3,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    telefono VARCHAR(25) NULL,
    contrasena_hash VARCHAR(255) NOT NULL,
    rol VARCHAR(50) NOT NULL DEFAULT 'cliente',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_usuario_rol
    FOREIGN KEY (id_rol)
    REFERENCES roles(id_rol)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABLA CATEGORIAS
CREATE TABLE IF NOT EXISTS categorias (
    id_categoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    activa TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. TABLA PRODUCTOS
CREATE TABLE IF NOT EXISTS productos (
    id_producto INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_categoria INT UNSIGNED NOT NULL,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(120) NOT NULL,
    descripcion TEXT NULL,
    precio DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    imagen VARCHAR(255) NULL,
    disponible TINYINT(1) NOT NULL DEFAULT 1,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_producto_categoria
    FOREIGN KEY (id_categoria)
    REFERENCES categorias(id_categoria)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,

    CONSTRAINT chk_producto_precio
    CHECK (precio >= 0),

    CONSTRAINT chk_producto_stock
    CHECK (stock >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. TABLA CONFIGURACION
CREATE TABLE IF NOT EXISTS configuracion (
    id_configuracion INT UNSIGNED PRIMARY KEY,
    nombre_negocio VARCHAR(100) NOT NULL,
    costo_envio DECIMAL(10,2) NOT NULL DEFAULT 10.00,

    CONSTRAINT chk_costo_envio
    CHECK (costo_envio >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. TABLA VENTAS
CREATE TABLE IF NOT EXISTS ventas (
    id_venta INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NOT NULL,
    numero_venta VARCHAR(50) NOT NULL UNIQUE,
    fecha_venta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cliente_nombre VARCHAR(120) NOT NULL DEFAULT 'Consumidor Final',
    cliente_documento VARCHAR(30) NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    descuento DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    metodo_pago ENUM('efectivo', 'tarjeta', 'qr', 'transferencia') NOT NULL DEFAULT 'efectivo',
    monto_recibido DECIMAL(10,2) NULL,
    cambio DECIMAL(10,2) NULL,
    estado ENUM('completada', 'anulada') NOT NULL DEFAULT 'completada',
    observaciones TEXT NULL,

    CONSTRAINT fk_venta_usuario
    FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,

    CONSTRAINT chk_venta_subtotal
    CHECK (subtotal >= 0),

    CONSTRAINT chk_venta_descuento
    CHECK (descuento >= 0),

    CONSTRAINT chk_venta_total
    CHECK (total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. TABLA DETALLE_VENTAS
CREATE TABLE IF NOT EXISTS detalle_ventas (
    id_detalle_venta INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_venta INT UNSIGNED NOT NULL,
    id_producto INT UNSIGNED NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,

    CONSTRAINT fk_detalle_venta
    FOREIGN KEY (id_venta)
    REFERENCES ventas(id_venta)
    ON UPDATE CASCADE
    ON DELETE CASCADE,

    CONSTRAINT fk_detalle_producto_venta
    FOREIGN KEY (id_producto)
    REFERENCES productos(id_producto)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,

    CONSTRAINT chk_detalle_venta_cantidad
    CHECK (cantidad > 0),

    CONSTRAINT chk_detalle_venta_precio
    CHECK (precio_unitario >= 0),

    CONSTRAINT chk_detalle_venta_subtotal
    CHECK (subtotal >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. TABLA MOVIMIENTOS_INVENTARIO
CREATE TABLE IF NOT EXISTS movimientos_inventario (
    id_movimiento INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_producto INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NULL,
    tipo_movimiento ENUM('ingreso', 'egreso_venta', 'ajuste', 'devolucion') NOT NULL,
    cantidad INT NOT NULL,
    stock_anterior INT NOT NULL,
    stock_nuevo INT NOT NULL,
    motivo VARCHAR(255) NULL,
    referencia_id INT UNSIGNED NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_mov_producto
    FOREIGN KEY (id_producto)
    REFERENCES productos(id_producto)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,

    CONSTRAINT fk_mov_usuario
    FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. TABLA AUDITORIA
CREATE TABLE IF NOT EXISTS auditoria (
    id_auditoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NULL,
    usuario_nombre VARCHAR(100) NULL,
    accion VARCHAR(60) NOT NULL,
    entidad VARCHAR(60) NOT NULL,
    entidad_id INT UNSIGNED NULL,
    datos_anteriores JSON NULL,
    datos_nuevos JSON NULL,
    detalles VARCHAR(255) NULL,
    ip VARCHAR(45) NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_auditoria_usuario
    FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. TABLA PEDIDOS (Módulo pedidos en línea)
CREATE TABLE IF NOT EXISTS pedidos (
    id_pedido INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NOT NULL,
    fecha_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tipo_entrega ENUM('recoger', 'domicilio') NOT NULL DEFAULT 'recoger',
    direccion_entrega VARCHAR(255) NULL,
    referencia_entrega VARCHAR(255) NULL,
    telefono_contacto VARCHAR(25) NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    costo_envio DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    estado ENUM(
        'pendiente',
        'aceptado',
        'en_preparacion',
        'listo',
        'entregado',
        'cancelado'
    ) NOT NULL DEFAULT 'pendiente',
    observaciones TEXT NULL,

    CONSTRAINT fk_pedido_usuario
    FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,

    CONSTRAINT chk_pedido_subtotal
    CHECK (subtotal >= 0),

    CONSTRAINT chk_pedido_envio
    CHECK (costo_envio >= 0),

    CONSTRAINT chk_pedido_total
    CHECK (total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. TABLA DETALLE_PEDIDOS
CREATE TABLE IF NOT EXISTS detalle_pedidos (
    id_detalle INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT UNSIGNED NOT NULL,
    id_producto INT UNSIGNED NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,

    CONSTRAINT fk_detalle_pedido
    FOREIGN KEY (id_pedido)
    REFERENCES pedidos(id_pedido)
    ON UPDATE CASCADE
    ON DELETE CASCADE,

    CONSTRAINT fk_detalle_producto
    FOREIGN KEY (id_producto)
    REFERENCES productos(id_producto)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,

    CONSTRAINT chk_detalle_cantidad
    CHECK (cantidad > 0),

    CONSTRAINT chk_detalle_precio
    CHECK (precio_unitario >= 0),

    CONSTRAINT chk_detalle_subtotal
    CHECK (subtotal >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DATOS INICIALES
INSERT INTO configuracion (id_configuracion, nombre_negocio, costo_envio)
VALUES (1, 'Urban Bites', 10.00)
ON DUPLICATE KEY UPDATE
    nombre_negocio = VALUES(nombre_negocio),
    costo_envio = VALUES(costo_envio);

INSERT INTO categorias (id_categoria, nombre, descripcion) VALUES
(1, 'Hamburguesas', 'Hamburguesas preparadas al momento'),
(2, 'Pizzas', 'Pizzas individuales y para compartir'),
(3, 'Salchipapas', 'Salchipapas y papas fritas'),
(4, 'Pollo frito', 'Pollo crujiente con acompañamientos'),
(5, 'Bebidas', 'Bebidas frías para acompañar')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

INSERT INTO productos (id_producto, id_categoria, codigo, nombre, descripcion, precio, stock, imagen, disponible) VALUES
(1, 1, 'HAM-001', 'Hamburguesa Clásica', 'Carne a la plancha, queso, lechuga, tomate y salsa de la casa.', 28.00, 50, NULL, 1),
(2, 2, 'PIZ-001', 'Pizza Personal Pepperoni', 'Pizza individual con salsa de tomate, mozzarella y pepperoni.', 38.00, 35, NULL, 1),
(3, 3, 'SAL-001', 'Salchipapa Especial', 'Papas crocantes, salchicha, queso rallado y salsas.', 30.00, 40, NULL, 1),
(4, 4, 'POL-001', 'Pollo Crispy (2 piezas)', 'Dos piezas de pollo crujiente con papas fritas.', 35.00, 30, NULL, 1),
(5, 5, 'BEB-001', 'Limonada Rosa', 'Limonada natural refrescante con frutos rojos 500ml.', 12.00, 60, NULL, 1),
(6, 1, 'HAM-002', 'Hamburguesa Doble Queso', 'Doble medallón de carne con doble queso cheddar fundido y tocino.', 36.00, 25, NULL, 1)
ON DUPLICATE KEY UPDATE
    codigo = VALUES(codigo),
    nombre = VALUES(nombre),
    precio = VALUES(precio),
    stock = VALUES(stock);

INSERT INTO movimientos_inventario (id_producto, id_usuario, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, motivo)
SELECT id_producto, NULL, 'ingreso', stock, 0, stock, 'Inventario inicial de demostración'
FROM productos
WHERE NOT EXISTS (SELECT 1 FROM movimientos_inventario mi WHERE mi.id_producto = productos.id_producto);
