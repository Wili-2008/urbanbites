-- ====================================================================
-- MIGRACIÓN INCREMENTAL: Módulo de Inventario, Ventas POS y Auditoría
-- Sistema Urban Bites
-- Preserva tablas existentes (usuarios, categorias, productos, pedidos, detalle_pedidos)
-- ====================================================================

-- 1. CREAR TABLA ROLES
CREATE TABLE IF NOT EXISTS roles (
    id_rol INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (id_rol, nombre, descripcion) VALUES
(1, 'administrador', 'Acceso total: usuarios, productos, inventario, reportes y auditoría'),
(2, 'vendedor', 'Punto de venta, catálogo de productos y registro de ventas'),
(3, 'cliente', 'Consulta de menú y pedidos en línea')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- 2. ADAPTAR TABLA USUARIOS (incorporar llave foránea id_rol)
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'id_rol'
);

SET @sql := IF(@col_exists = 0,
    'ALTER TABLE usuarios ADD COLUMN id_rol INT UNSIGNED NULL AFTER id_usuario',
    'SELECT "Columna id_rol ya existe en usuarios"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Mapear roles existentes a la nueva tabla roles
UPDATE usuarios u
LEFT JOIN roles r ON r.nombre = u.rol
SET u.id_rol = COALESCE(r.id_rol, 3)
WHERE u.id_rol IS NULL;

-- Asignar rol por defecto para cualquier usuario que no tenga
UPDATE usuarios SET id_rol = 3 WHERE id_rol IS NULL;

-- Ajustar a NOT NULL y agregar llave foránea si no existe
SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND CONSTRAINT_NAME = 'fk_usuario_rol'
);

SET @sql_fk := IF(@fk_exists = 0,
    'ALTER TABLE usuarios MODIFY COLUMN id_rol INT UNSIGNED NOT NULL DEFAULT 3, ADD CONSTRAINT fk_usuario_rol FOREIGN KEY (id_rol) REFERENCES roles(id_rol) ON UPDATE CASCADE ON DELETE RESTRICT',
    'ALTER TABLE usuarios MODIFY COLUMN id_rol INT UNSIGNED NOT NULL DEFAULT 3'
);
PREPARE stmt FROM @sql_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- 3. ADAPTAR TABLA PRODUCTOS (agregar codigo único y stock >= 0)
SET @col_codigo := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'productos' AND COLUMN_NAME = 'codigo'
);

SET @sql_codigo := IF(@col_codigo = 0,
    'ALTER TABLE productos ADD COLUMN codigo VARCHAR(50) NULL AFTER id_categoria',
    'SELECT "Columna codigo ya existe en productos"'
);
PREPARE stmt FROM @sql_codigo;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Asignar códigos únicos correlativos a productos existentes que no tengan código
UPDATE productos
SET codigo = CONCAT('PRD-', LPAD(id_producto, 4, '0'))
WHERE codigo IS NULL OR codigo = '';

-- Asignar códigos mnemotécnicos específicos a los productos demo conocidos si coinciden
UPDATE productos SET codigo = 'HAM-001' WHERE nombre = 'Hamburguesa Clásica' AND (codigo LIKE 'PRD-%' OR codigo IS NULL);
UPDATE productos SET codigo = 'PIZ-001' WHERE nombre = 'Pizza Personal Pepperoni' AND (codigo LIKE 'PRD-%' OR codigo IS NULL);
UPDATE productos SET codigo = 'SAL-001' WHERE nombre = 'Salchipapa Especial' AND (codigo LIKE 'PRD-%' OR codigo IS NULL);
UPDATE productos SET codigo = 'POL-001' WHERE nombre = 'Pollo Crispy (2 piezas)' AND (codigo LIKE 'PRD-%' OR codigo IS NULL);

-- Asegurar restricción UNIQUE en codigo
SET @idx_codigo := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'productos' AND INDEX_NAME = 'uniq_producto_codigo'
);

SET @sql_idx_codigo := IF(@idx_codigo = 0,
    'ALTER TABLE productos MODIFY COLUMN codigo VARCHAR(50) NOT NULL, ADD CONSTRAINT uniq_producto_codigo UNIQUE (codigo)',
    'ALTER TABLE productos MODIFY COLUMN codigo VARCHAR(50) NOT NULL'
);
PREPARE stmt FROM @sql_idx_codigo;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar columna stock si no existe
SET @col_stock := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'productos' AND COLUMN_NAME = 'stock'
);

SET @sql_stock := IF(@col_stock = 0,
    'ALTER TABLE productos ADD COLUMN stock INT NOT NULL DEFAULT 50 AFTER precio, ADD CONSTRAINT chk_producto_stock CHECK (stock >= 0)',
    'SELECT "Columna stock ya existe en productos"'
);
PREPARE stmt FROM @sql_stock;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- 4. TABLA VENTAS
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


-- 5. TABLA DETALLE_VENTAS
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


-- 6. TABLA MOVIMIENTOS_INVENTARIO
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


-- 7. TABLA AUDITORIA (BITÁCORA)
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


-- 8. TRIGGERS DE SEGURIDAD Y AUDITORÍA
DROP TRIGGER IF EXISTS trg_productos_check_stock;
DELIMITER $$
CREATE TRIGGER trg_productos_check_stock
BEFORE UPDATE ON productos
FOR EACH ROW
BEGIN
    IF NEW.stock < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Error: El stock no puede quedar en un valor negativo.';
    END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_productos_audit_precio;
DELIMITER $$
CREATE TRIGGER trg_productos_audit_precio
AFTER UPDATE ON productos
FOR EACH ROW
BEGIN
    IF OLD.precio <> NEW.precio AND (@audit_handled IS NULL OR @audit_handled = 0) THEN
        INSERT INTO auditoria (
            id_usuario,
            usuario_nombre,
            accion,
            entidad,
            entidad_id,
            datos_anteriores,
            datos_nuevos,
            detalles,
            fecha
        )
        VALUES (
            @current_user_id,
            @current_user_name,
            'MODIFICAR_PRECIO',
            'productos',
            NEW.id_producto,
            JSON_OBJECT('precio', OLD.precio),
            JSON_OBJECT('precio', NEW.precio),
            CONCAT('Cambio de precio de Bs ', OLD.precio, ' a Bs ', NEW.precio),
            NOW()
        );
    END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_productos_audit_delete;
DELIMITER $$
CREATE TRIGGER trg_productos_audit_delete
AFTER DELETE ON productos
FOR EACH ROW
BEGIN
    IF (@audit_handled IS NULL OR @audit_handled = 0) THEN
        INSERT INTO auditoria (
            id_usuario,
            usuario_nombre,
            accion,
            entidad,
            entidad_id,
            datos_anteriores,
            detalles,
            fecha
        )
        VALUES (
            @current_user_id,
            @current_user_name,
            'ELIMINAR',
            'productos',
            OLD.id_producto,
            JSON_OBJECT('codigo', OLD.codigo, 'nombre', OLD.nombre, 'precio', OLD.precio, 'stock', OLD.stock),
            CONCAT('Producto eliminado: ', OLD.nombre, ' (', OLD.codigo, ')'),
            NOW()
        );
    END IF;
END$$
DELIMITER ;
