<?php
require_once __DIR__ . '/../config/ConexionPDO.php';

class Rol extends ConexionPDO
{
    public static function obtenerTodos()
    {
        return self::ExecuteQuery("SELECT id_rol, nombre, descripcion FROM roles ORDER BY id_rol ASC");
    }

    public static function obtenerPorId($id)
    {
        $rows = self::ExecuteQuery("SELECT id_rol, nombre, descripcion FROM roles WHERE id_rol = :id", ['id' => $id]);
        return $rows ? $rows[0] : null;
    }

    public static function obtenerPorNombre($nombre)
    {
        $rows = self::ExecuteQuery("SELECT id_rol, nombre, descripcion FROM roles WHERE nombre = :nombre", ['nombre' => $nombre]);
        return $rows ? $rows[0] : null;
    }
}
