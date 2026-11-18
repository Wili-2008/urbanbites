<?php
require_once __DIR__ . '/../Config/ConexionPDO.php';
require_once __DIR__ . '/Auditoria.php';

class Producto extends ConexionPDO
{
    public static function obtenerTodos($incluirInactivos = true)
    {
        $where = $incluirInactivos ? '' : 'WHERE p.disponible = 1 AND c.activa = 1';
        return self::ExecuteQuery(
            "SELECT p.id_producto, p.id_categoria, c.nombre AS categoria, p.codigo, p.nombre, p.descripcion,
                    p.precio, p.stock, p.imagen, p.disponible, p.fecha_registro
             FROM productos p
             JOIN categorias c ON c.id_categoria = p.id_categoria
             {$where}
             ORDER BY p.id_producto ASC"
        );
    }

    public static function obtenerPorId($id)
    {
        $rows = self::ExecuteQuery(
            "SELECT p.id_producto, p.id_categoria, c.nombre AS categoria, p.codigo, p.nombre, p.descripcion,
                    p.precio, p.stock, p.imagen, p.disponible, p.fecha_registro
             FROM productos p
             JOIN categorias c ON c.id_categoria = p.id_categoria
             WHERE p.id_producto = :id",
            ['id' => $id]
        );
        return $rows ? $rows[0] : null;
    }

    public static function obtenerPorCodigo($codigo)
    {
        $rows = self::ExecuteQuery(
            "SELECT p.id_producto, p.id_categoria, c.nombre AS categoria, p.codigo, p.nombre, p.descripcion,
                    p.precio, p.stock, p.imagen, p.disponible
             FROM productos p
             JOIN categorias c ON c.id_categoria = p.id_categoria
             WHERE p.codigo = :codigo",
            ['codigo' => trim((string) $codigo)]
        );
        return $rows ? $rows[0] : null;
    }

    public static function existeCodigo($codigo, $excluirId = null)
    {
        $sql = "SELECT id_producto FROM productos WHERE codigo = :codigo";
        $params = ['codigo' => trim((string) $codigo)];
        if ($excluirId !== null) {
            $sql .= " AND id_producto <> :excluir_id";
            $params['excluir_id'] = (int) $excluirId;
        }
        $rows = self::ExecuteQuery($sql, $params);
        return !empty($rows);
    }

    public static function crear($codigo, $nombre, $descripcion, $precio, $stock, $imagen, $id_categoria, $disponible = 1, $idUsuario = null, $usuarioNombre = null)
    {
        $codigo = strtoupper(trim((string) $codigo));
        $stock = max(0, (int) $stock);
        $precio = (float) $precio;

        $pdo = self::Conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO productos (id_categoria, codigo, nombre, descripcion, precio, stock, imagen, disponible)
                 VALUES (:id_categoria, :codigo, :nombre, :descripcion, :precio, :stock, :imagen, :disponible)"
            );
            $stmt->execute([
                'id_categoria' => $id_categoria,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'precio' => $precio,
                'stock' => $stock,
                'imagen' => $imagen,
                'disponible' => $disponible,
            ]);
            $id = (int) $pdo->lastInsertId();

            // Registrar en auditoría
            Auditoria::registrar(
                $idUsuario,
                $usuarioNombre,
                'CREAR',
                'productos',
                $id,
                null,
                ['codigo' => $codigo, 'nombre' => $nombre, 'precio' => $precio, 'stock' => $stock],
                "Creación de producto: {$nombre} ({$codigo})"
            );

            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function updateProducto($id, $codigo, $nombre, $descripcion, $precio, $stock, $imagen, $id_categoria, $disponible, $idUsuario = null, $usuarioNombre = null)
    {
        $id = (int) $id;
        $codigo = strtoupper(trim((string) $codigo));
        $nuevoPrecio = (float) $precio;
        $nuevoStock = max(0, (int) $stock);

        $actual = self::obtenerPorId($id);
        if (!$actual) {
            throw new DomainException('El producto no existe.');
        }

        $precioAnterior = (float) $actual['precio'];
        $stockAnterior = (int) $actual['stock'];
        $disponibleAnterior = (int) $actual['disponible'];

        $pdo = self::Conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE productos
                 SET id_categoria = :id_categoria, codigo = :codigo, nombre = :nombre, descripcion = :descripcion,
                     precio = :precio, stock = :stock, imagen = :imagen, disponible = :disponible
                 WHERE id_producto = :id"
            );
            $stmt->execute([
                'id_categoria' => $id_categoria,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'precio' => $nuevoPrecio,
                'stock' => $nuevoStock,
                'imagen' => $imagen,
                'disponible' => $disponible,
                'id' => $id,
            ]);
            $affected = $stmt->rowCount();

            // Auditoría si cambió el precio
            if (abs($precioAnterior - $nuevoPrecio) > 0.0001) {
                Auditoria::registrar(
                    $idUsuario,
                    $usuarioNombre,
                    'MODIFICAR_PRECIO',
                    'productos',
                    $id,
                    ['precio' => $precioAnterior],
                    ['precio' => $nuevoPrecio],
                    "Cambio de precio de Bs {$precioAnterior} a Bs {$nuevoPrecio} en {$nombre}"
                );
            }

            // Auditoría si cambió el stock directamente
            if ($stockAnterior !== $nuevoStock) {
                $diff = $nuevoStock - $stockAnterior;
                Auditoria::registrar(
                    $idUsuario,
                    $usuarioNombre,
                    'MODIFICAR_STOCK',
                    'productos',
                    $id,
                    ['stock' => $stockAnterior],
                    ['stock' => $nuevoStock],
                    "Ajuste manual de stock de {$stockAnterior} a {$nuevoStock} en {$nombre}"
                );
            }

            // Auditoría si cambió disponibilidad
            if ($disponibleAnterior !== (int) $disponible) {
                Auditoria::registrar(
                    $idUsuario,
                    $usuarioNombre,
                    $disponible ? 'ACTIVAR' : 'DESACTIVAR',
                    'productos',
                    $id,
                    ['disponible' => $disponibleAnterior],
                    ['disponible' => (int) $disponible],
                    ($disponible ? "Activación de producto " : "Desactivación de producto ") . $nombre
                );
            }

            $pdo->commit();
            return $affected;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function ajustarStock($id, $cantidadAjuste, $motivo, $idUsuario = null, $usuarioNombre = null)
    {
        $id = (int) $id;
        $cantidadAjuste = (int) $cantidadAjuste;
        $motivo = trim((string) $motivo) ?: 'Ajuste manual de inventario';

        $pdo = self::Conexion();
        $pdo->beginTransaction();
        try {
            $stmtLock = $pdo->prepare("SELECT id_producto, nombre, codigo, stock FROM productos WHERE id_producto = :id FOR UPDATE");
            $stmtLock->execute(['id' => $id]);
            $producto = $stmtLock->fetch();
            if (!$producto) {
                throw new DomainException('No se encontró el producto para ajustar.');
            }

            $stockAnterior = (int) $producto['stock'];
            $stockNuevo = $stockAnterior + $cantidadAjuste;
            if ($stockNuevo < 0) {
                throw new DomainException("El ajuste dejaría el stock en {$stockNuevo}. No se permiten existencias negativas.");
            }

            $stmtUpdate = $pdo->prepare("UPDATE productos SET stock = :nuevo_stock WHERE id_producto = :id");
            $stmtUpdate->execute(['nuevo_stock' => $stockNuevo, 'id' => $id]);

            Auditoria::registrar(
                $idUsuario,
                $usuarioNombre,
                'AJUSTE_INVENTARIO',
                'productos',
                $id,
                ['stock' => $stockAnterior],
                ['stock' => $stockNuevo, 'diferencia' => $cantidadAjuste, 'motivo' => $motivo],
                "Ajuste de inventario en {$producto['nombre']}: {$stockAnterior} -> {$stockNuevo} ({$motivo})"
            );

            $pdo->commit();
            return ['stock_anterior' => $stockAnterior, 'stock_nuevo' => $stockNuevo];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function obtenerImagen($id)
    {
        $rows = self::ExecuteQuery('SELECT imagen FROM productos WHERE id_producto = :id', ['id' => $id]);
        return $rows[0]['imagen'] ?? null;
    }

    public static function existe($id)
    {
        $rows = self::ExecuteQuery('SELECT id_producto FROM productos WHERE id_producto = :id', ['id' => $id]);
        return !empty($rows);
    }

    public static function actualizarImagen($id, $imagen)
    {
        return self::ExecuteStatement(
            'UPDATE productos SET imagen = :imagen WHERE id_producto = :id',
            ['imagen' => $imagen, 'id' => $id]
        );
    }

    public static function deleteProducto($id, $idUsuario = null, $usuarioNombre = null)
    {
        $producto = self::obtenerPorId($id);
        if (!$producto) {
            return 0;
        }
        $affected = self::ExecuteStatement("UPDATE productos SET disponible = 0 WHERE id_producto = :id AND disponible = 1", ['id' => $id]);
        if ($affected > 0) {
            Auditoria::registrar(
                $idUsuario,
                $usuarioNombre,
                'DESACTIVAR',
                'productos',
                $id,
                ['disponible' => 1],
                ['disponible' => 0],
                "Desactivación de producto {$producto['nombre']} ({$producto['codigo']})"
            );
        }
        return $affected;
    }
}
