<?php
require_once __DIR__ . '/../config/ConexionPDO.php';

class Usuario extends ConexionPDO
{
    public static function cantidadAdministradores()
    {
        $rows = self::ExecuteQuery("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'administrador'");
        return (int) ($rows[0]['total'] ?? 0);
    }

    public static function obtenerTodos()
    {
        return self::ExecuteQuery(
            "SELECT id_usuario, nombre, correo, telefono, rol, activo, fecha_registro
             FROM usuarios ORDER BY id_usuario"
        );
    }

    public static function obtenerPorCorreo($correo)
    {
        $rows = self::ExecuteQuery("SELECT * FROM usuarios WHERE correo = :correo", ['correo' => $correo]);
        return $rows ? $rows[0] : null;
    }

    public static function obtenerPorEmail($email)
    {
        return self::obtenerPorCorreo($email);
    }

    public static function obtenerPorEmailOrNombre($identifier)
    {
        $rows = self::ExecuteQuery(
            "SELECT * FROM usuarios WHERE correo = :correo OR nombre = :nombre",
            ['correo' => $identifier, 'nombre' => $identifier]
        );
        return $rows ? $rows[0] : null;
    }

    public static function obtenerPorId($id)
    {
        $rows = self::ExecuteQuery(
            "SELECT id_usuario, nombre, correo, telefono, contrasena_hash, rol, activo
             FROM usuarios WHERE id_usuario = :id",
            ['id' => $id]
        );
        return $rows ? $rows[0] : null;
    }

    public static function crear($nombre, $correo, $contrasena, $rol = 'cliente', $telefono = null)
    {
        $id = self::InsertarSql(
            "INSERT INTO usuarios (nombre, correo, telefono, contrasena_hash, rol)
             VALUES (:nombre, :correo, :telefono, :contrasena_hash, :rol)",
            [
                'nombre' => $nombre,
                'correo' => $correo,
                'telefono' => $telefono,
                'contrasena_hash' => password_hash($contrasena, PASSWORD_DEFAULT),
                'rol' => $rol,
            ]
        );
        return ['id_usuario' => $id];
    }

    public static function updateUsuario($id_usuario, $nombre, $correo, $telefono = null, $contrasena = null, $rol = null, $activo = null)
    {
        $params = [
            'id_usuario' => $id_usuario,
            'nombre' => $nombre,
            'correo' => $correo,
            'telefono' => $telefono,
        ];
        $roleSql = '';
        if ($rol !== null) {
            $roleSql = ', rol = :rol';
            $params['rol'] = $rol;
        }
        $passwordSql = '';
        if ($contrasena !== null && $contrasena !== '') {
            $passwordSql = ', contrasena_hash = :contrasena_hash';
            $params['contrasena_hash'] = password_hash($contrasena, PASSWORD_DEFAULT);
        }
        $activeSql = '';
        if ($activo !== null) {
            $activeSql = ', activo = :activo';
            $params['activo'] = $activo;
        }
        return self::ExecuteStatement(
            "UPDATE usuarios SET nombre = :nombre, correo = :correo, telefono = :telefono
             {$roleSql}{$passwordSql}{$activeSql} WHERE id_usuario = :id_usuario",
            $params
        );
    }

    public static function deleteUsuario($id)
    {
        return self::ExecuteStatement("UPDATE usuarios SET activo = 0 WHERE id_usuario = :id AND activo = 1", ['id' => $id]);
    }
}
