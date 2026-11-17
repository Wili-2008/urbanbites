<?php
require_once __DIR__ . '/../config/ConexionPDO.php';

class Categoria extends ConexionPDO
{
    public static function obtenerTodos($incluirInactivas = false)
    {
        $where = $incluirInactivas ? '' : 'WHERE activa = 1';
        return self::ExecuteQuery(
            "SELECT id_categoria, nombre, descripcion, activa FROM categorias {$where} ORDER BY nombre"
        );
    }

    public static function crear($nombre, $descripcion = null)
    {
        return self::InsertarSql(
            "INSERT INTO categorias (nombre, descripcion) VALUES (:nombre, :descripcion)",
            ['nombre' => $nombre, 'descripcion' => $descripcion]
        );
    }

    public static function updateCategoria($id_categoria, $nombre, $descripcion = null, $activa = null)
    {
        $params = ['nombre' => $nombre, 'descripcion' => $descripcion, 'id' => $id_categoria];
        $activeSql = '';
        if ($activa !== null) {
            $activeSql = ', activa = :activa';
            $params['activa'] = $activa;
        }
        return self::ExecuteStatement(
            "UPDATE categorias SET nombre = :nombre, descripcion = :descripcion{$activeSql}
             WHERE id_categoria = :id",
            $params
        );
    }

    public static function deleteCategoria($id)
    {
        return self::ExecuteStatement("UPDATE categorias SET activa = 0 WHERE id_categoria = :id AND activa = 1", ['id' => $id]);
    }
}
