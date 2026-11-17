<?php
require_once __DIR__ . '/Usuario.php';

class Cliente extends Usuario
{
    public static function obtenerTodos()
    {
        return self::ExecuteQuery(
            "SELECT id_usuario AS id_cliente, id_usuario, nombre, correo, telefono, rol, activo, fecha_registro
             FROM usuarios WHERE rol = 'cliente' ORDER BY id_usuario"
        );
    }

    public static function obtenerPorEmail($email)
    {
        $rows = self::ExecuteQuery(
            "SELECT * FROM usuarios WHERE correo = :correo AND rol = 'cliente'",
            ['correo' => $email]
        );
        return $rows ? $rows[0] : null;
    }

    public static function crearConAutenticacion($nombre, $email, $password)
    {
        $created = self::crear($nombre, $email, $password, 'cliente');
        return ['mensaje' => 'OK', 'id' => $created['id_usuario']];
    }

    public static function updateCliente($id, $nombre, $correo, $contrasena = '', $telefono = null, $activo = null)
    {
        return self::updateUsuario(
            $id,
            $nombre,
            $correo,
            $telefono,
            $contrasena !== '' ? $contrasena : null,
            'cliente',
            $activo
        );
    }

    public static function deleteCliente($id)
    {
        return self::ExecuteStatement(
            "UPDATE usuarios SET activo = 0 WHERE id_usuario = :id AND rol = 'cliente' AND activo = 1",
            ['id' => $id]
        );
    }
}
