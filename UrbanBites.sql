-- URBAN BITES - Inventario, ventas y auditoria (WAMP local)
-- Instalacion limpia: exactamente seis tablas MySQL.
-- La bitacora se almacena en un archivo JSONL fuera del DocumentRoot.

CREATE DATABASE IF NOT EXISTS urbanbites_wamp_6
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE urbanbites_wamp_6;

CREATE TABLE IF NOT EXISTS roles (
  id_rol INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL UNIQUE,
  descripcion VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (id_rol, nombre, descripcion) VALUES
  (1, 'administrador', 'Gestiona usuarios, categorias, productos, existencias, reportes y auditoria'),
  (2, 'vendedor', 'Consulta el catalogo y registra ventas en el punto de venta')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

CREATE TABLE IF NOT EXISTS usuarios (
  id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_rol INT UNSIGNED NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  correo VARCHAR(150) NOT NULL UNIQUE,
  telefono VARCHAR(25) NULL,
  contrasena_hash VARCHAR(255) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usuario_rol FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categorias (
  id_categoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL UNIQUE,
  descripcion VARCHAR(255) NULL,
  activa TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_producto_categoria FOREIGN KEY (id_categoria) REFERENCES categorias(id_categoria)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_producto_precio CHECK (precio >= 0),
  CONSTRAINT chk_producto_stock CHECK (stock >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ventas (
  id_venta INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT UNSIGNED NOT NULL,
  numero_venta VARCHAR(50) NOT NULL UNIQUE,
  fecha_venta TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
  CONSTRAINT fk_venta_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_venta_subtotal CHECK (subtotal >= 0),
  CONSTRAINT chk_venta_descuento CHECK (descuento >= 0),
  CONSTRAINT chk_venta_total CHECK (total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS detalle_ventas (
  id_detalle_venta INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_venta INT UNSIGNED NOT NULL,
  id_producto INT UNSIGNED NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  precio_unitario DECIMAL(10,2) NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_detalle_venta FOREIGN KEY (id_venta) REFERENCES ventas(id_venta)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_detalle_producto_venta FOREIGN KEY (id_producto) REFERENCES productos(id_producto)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_detalle_venta_cantidad CHECK (cantidad > 0),
  CONSTRAINT chk_detalle_venta_precio CHECK (precio_unitario >= 0),
  CONSTRAINT chk_detalle_venta_subtotal CHECK (subtotal >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS trg_productos_check_stock;
DELIMITER $$
CREATE TRIGGER trg_productos_check_stock
BEFORE UPDATE ON productos
FOR EACH ROW
BEGIN
  IF NEW.stock < 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El stock no puede quedar en un valor negativo.';
  END IF;
END$$
DELIMITER ;

INSERT INTO categorias (id_categoria, nombre, descripcion) VALUES
  (1, 'Hamburguesas', 'Hamburguesas preparadas al momento'),
  (2, 'Pizzas', 'Pizzas individuales y para compartir'),
  (3, 'Salchipapas', 'Salchipapas y papas fritas'),
  (4, 'Pollo frito', 'Pollo crujiente con acompanamientos'),
  (5, 'Bebidas', 'Bebidas frias para acompanar')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

INSERT INTO productos (id_categoria, codigo, nombre, descripcion, precio, stock, disponible) VALUES
  (1, 'HAM-001', 'Hamburguesa Clasica', 'Carne a la plancha, queso, lechuga, tomate y salsa de la casa.', 28.00, 50, 1),
  (2, 'PIZ-001', 'Pizza Personal Pepperoni', 'Pizza individual con salsa de tomate, mozzarella y pepperoni.', 38.00, 35, 1),
  (3, 'SAL-001', 'Salchipapa Especial', 'Papas crocantes, salchicha, queso rallado y salsas.', 30.00, 40, 1),
  (4, 'POL-001', 'Pollo Crispy (2 piezas)', 'Dos piezas de pollo crujiente con papas fritas.', 35.00, 30, 1),
  (5, 'BEB-001', 'Limonada Rosa', 'Limonada natural refrescante con frutos rojos.', 12.00, 60, 1),
  (1, 'HAM-002', 'Hamburguesa Doble Queso', 'Doble carne con doble queso cheddar.', 36.00, 25, 1)
ON DUPLICATE KEY UPDATE
  nombre = VALUES(nombre),
  descripcion = VALUES(descripcion),
  precio = VALUES(precio),
  disponible = VALUES(disponible);