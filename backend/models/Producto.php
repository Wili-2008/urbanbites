<?php
require_once __DIR__ . '/../config/ConexionPDO.php';

class Producto extends ConexionPDO
{
    public static function obtenerTodos($incluirInactivos = true)
    {
        $where = $incluirInactivos ? '' : 'WHERE p.disponible = 1 AND c.activa = 1';
        return self::ExecuteQuery(
            "SELECT p.id_producto, p.id_categoria, c.nombre AS categoria, p.nombre, p.descripcion,
                    p.precio, p.imagen, p.disponible
             FROM productos p
             JOIN categorias c ON c.id_categoria = p.id_categoria
             {$where}
             ORDER BY p.id_producto"
        );
    }

    public static function crear($nombre, $descripcion, $precio, $imagen, $id_categoria, $disponible = 1)
    {
        return self::InsertarSql(
            "INSERT INTO productos (id_categoria, nombre, descripcion, precio, imagen, disponible)
             VALUES (:id_categoria, :nombre, :descripcion, :precio, :imagen, :disponible)",
            [
                'id_categoria' => $id_categoria,
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'precio' => $precio,
                'imagen' => $imagen,
                'disponible' => $disponible,
            ]
        );
    }

    public static function updateProducto($id, $nombre, $descripcion, $precio, $imagen, $id_categoria, $disponible)
    {
        return self::ExecuteStatement(
            "UPDATE productos SET id_categoria = :id_categoria, nombre = :nombre, descripcion = :descripcion,
             precio = :precio, imagen = :imagen, disponible = :disponible WHERE id_producto = :id",
            [
                'id_categoria' => $id_categoria,
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'precio' => $precio,
                'imagen' => $imagen,
                'disponible' => $disponible,
                'id' => $id,
            ]
        );
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

    public static function deleteProducto($id)
    {
        return self::ExecuteStatement("UPDATE productos SET disponible = 0 WHERE id_producto = :id AND disponible = 1", ['id' => $id]);
    }
}
