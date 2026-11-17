CREATE DATABASE IF NOT EXISTS urban_bites
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
USE urban_bites;

-- 1. TABLA USUARIOS

CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    telefono VARCHAR(25),
    contrasena_hash VARCHAR(255) NOT NULL,
    rol ENUM('cliente', 'administrador')
        NOT NULL DEFAULT 'cliente',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- 2. TABLA CATEGORIAS


CREATE TABLE IF NOT EXISTS categorias (
    id_categoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE,
    descripcion VARCHAR(255),
    activa TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;


-- 3. TABLA PRODUCTOS


CREATE TABLE IF NOT EXISTS productos (
    id_producto INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_categoria INT UNSIGNED NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    descripcion TEXT,
    precio DECIMAL(10,2) NOT NULL,
    imagen VARCHAR(255),
    disponible TINYINT(1) NOT NULL DEFAULT 1,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_producto_categoria
    FOREIGN KEY (id_categoria)
    REFERENCES categorias(id_categoria)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,

    CONSTRAINT chk_producto_precio
    CHECK (precio >= 0)
) ENGINE=InnoDB;

--=
-- 4. TABLA CONFIGURACION

CREATE TABLE IF NOT EXISTS configuracion (
    id_configuracion INT UNSIGNED PRIMARY KEY,
    nombre_negocio VARCHAR(100) NOT NULL,
    costo_envio DECIMAL(10,2) NOT NULL DEFAULT 10.00,

    CONSTRAINT chk_costo_envio
    CHECK (costo_envio >= 0)
) ENGINE=InnoDB;


-- 5. TABLA PEDIDOS


CREATE TABLE IF NOT EXISTS pedidos (
    id_pedido INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NOT NULL,

    fecha_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    tipo_entrega ENUM('recoger', 'domicilio')
        NOT NULL DEFAULT 'recoger',

    direccion_entrega VARCHAR(255),
    referencia_entrega VARCHAR(255),

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

    observaciones TEXT,

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
) ENGINE=InnoDB;

-- 6. TABLA DETALLE PEDIDOS

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
) ENGINE=InnoDB;

-- ==========================================
-- 7. CONFIGURACION INICIAL
-- ==========================================

INSERT INTO configuracion (
    id_configuracion,
    nombre_negocio,
    costo_envio
)
VALUES (
    1,
    'Urban Bites',
    10.00
)
ON DUPLICATE KEY UPDATE
    id_configuracion = id_configuracion;

INSERT INTO categorias (nombre, descripcion) VALUES
('Hamburguesas', 'Hamburguesas preparadas al momento'),
('Pizzas', 'Pizzas individuales y para compartir'),
('Salchipapas', 'Salchipapas y papas fritas'),
('Pollo frito', 'Pollo crujiente con acompañamientos'),
('Bebidas', 'Bebidas frías para acompañar')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- PRODUCTOS DE DEMOSTRACION (no duplica registros al reejecutar)
INSERT INTO productos (id_categoria, nombre, descripcion, precio, imagen, disponible)
SELECT c.id_categoria, 'Hamburguesa Clásica', 'Carne a la plancha, queso, lechuga, tomate y salsa de la casa.', 28.00, NULL, 1
FROM categorias c
WHERE c.nombre = 'Hamburguesas'
  AND NOT EXISTS (SELECT 1 FROM productos p WHERE p.id_categoria = c.id_categoria AND p.nombre = 'Hamburguesa Clásica');

INSERT INTO productos (id_categoria, nombre, descripcion, precio, imagen, disponible)
SELECT c.id_categoria, 'Pizza Personal Pepperoni', 'Pizza individual con salsa de tomate, mozzarella y pepperoni.', 38.00, NULL, 1
FROM categorias c
WHERE c.nombre = 'Pizzas'
  AND NOT EXISTS (SELECT 1 FROM productos p WHERE p.id_categoria = c.id_categoria AND p.nombre = 'Pizza Personal Pepperoni');

INSERT INTO productos (id_categoria, nombre, descripcion, precio, imagen, disponible)
SELECT c.id_categoria, 'Salchipapa Especial', 'Papas crocantes, salchicha, queso rallado y salsas.', 30.00, NULL, 1
FROM categorias c
WHERE c.nombre = 'Salchipapas'
  AND NOT EXISTS (SELECT 1 FROM productos p WHERE p.id_categoria = c.id_categoria AND p.nombre = 'Salchipapa Especial');

INSERT INTO productos (id_categoria, nombre, descripcion, precio, imagen, disponible)
SELECT c.id_categoria, 'Pollo Crispy (2 piezas)', 'Dos piezas de pollo crujiente con papas fritas.', 35.00, NULL, 1
FROM categorias c
WHERE c.nombre = 'Pollo frito'
  AND NOT EXISTS (SELECT 1 FROM productos p WHERE p.id_categoria = c.id_categoria AND p.nombre = 'Pollo Crispy (2 piezas)');



SHOW TABLES;
