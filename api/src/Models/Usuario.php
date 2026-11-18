<?php
require_once __DIR__ . '/../Config/ConexionPDO.php';
require_once __DIR__ . '/Rol.php';
require_once __DIR__ . '/Auditoria.php';

class Usuario extends ConexionPDO
{
    public static function cantidadAdministradores()
    {
        $rows = self::ExecuteQuery(
            "SELECT COUNT(*) AS total
             FROM usuarios u
             JOIN roles r ON r.id_rol = u.id_rol
             WHERE r.nombre = 'administrador' AND u.activo = 1"
        );
        return (int) ($rows[0]['total'] ?? 0);
    }

    public static function obtenerTodos()
    {
        return self::ExecuteQuery(
            "SELECT u.id_usuario, u.id_rol, r.nombre AS rol,
                    u.nombre, u.correo, u.telefono, u.activo, u.fecha_registro
             FROM usuarios u
             JOIN roles r ON r.id_rol = u.id_rol
             ORDER BY u.id_usuario ASC"
        );
    }

    public static function obtenerPorCorreo($correo)
    {
        $rows = self::ExecuteQuery(
            "SELECT u.id_usuario, u.id_rol, r.nombre AS rol,
                    u.nombre, u.correo, u.telefono, u.contrasena_hash, u.activo, u.fecha_registro
             FROM usuarios u
             JOIN roles r ON r.id_rol = u.id_rol
             WHERE u.correo = :correo",
            ['correo' => $correo]
        );
        return $rows ? $rows[0] : null;
    }

    public static function obtenerPorEmail($email)
    {
        return self::obtenerPorCorreo($email);
    }

    public static function obtenerPorEmailOrNombre($identifier)
    {
        $rows = self::ExecuteQuery(
            "SELECT u.id_usuario, u.id_rol, r.nombre AS rol,
                    u.nombre, u.correo, u.telefono, u.contrasena_hash, u.activo, u.fecha_registro
             FROM usuarios u
             JOIN roles r ON r.id_rol = u.id_rol
             WHERE u.correo = :correo OR u.nombre = :nombre",
            ['correo' => $identifier, 'nombre' => $identifier]
        );
        return $rows ? $rows[0] : null;
    }

    public static function obtenerPorId($id)
    {
        $rows = self::ExecuteQuery(
            "SELECT u.id_usuario, u.id_rol, r.nombre AS rol,
                    u.nombre, u.correo, u.telefono, u.contrasena_hash, u.activo, u.fecha_registro
             FROM usuarios u
             JOIN roles r ON r.id_rol = u.id_rol
             WHERE u.id_usuario = :id",
            ['id' => $id]
        );
        return $rows ? $rows[0] : null;
    }

    public static function crear($nombre, $correo, $contrasena, $rol = 'vendedor', $telefono = null, $idActor = null, $actorNombre = null)
    {
        $rolInfo = Rol::obtenerPorNombre($rol) ?: Rol::obtenerPorId(is_numeric($rol) ? (int) $rol : 0);
        if (!$rolInfo || !in_array($rolInfo['nombre'], ['administrador', 'vendedor'], true)) {
            throw new InvalidArgumentException('El rol debe ser administrador o vendedor.');
        }
        $idRol = (int) $rolInfo['id_rol'];
        $rolNombre = $rolInfo['nombre'];

        $id = self::InsertarSql(
            "INSERT INTO usuarios (id_rol, nombre, correo, telefono, contrasena_hash, activo)
             VALUES (:id_rol, :nombre, :correo, :telefono, :contrasena_hash, 1)",
            [
                'id_rol' => $idRol,
                'nombre' => $nombre,
                'correo' => $correo,
                'telefono' => $telefono,
                'contrasena_hash' => password_hash($contrasena, PASSWORD_DEFAULT),
            ]
        );

        if ($id) {
            Auditoria::registrar(
                $idActor,
                $actorNombre,
                'CREAR',
                'usuarios',
                $id,
                null,
                ['nombre' => $nombre, 'correo' => $correo, 'rol' => $rolNombre],
                "Creación de usuario {$nombre} con rol {$rolNombre}"
            );
        }

        return ['id_usuario' => $id];
    }

    public static function updateUsuario($id_usuario, $nombre, $correo, $telefono = null, $contrasena = null, $rol = null, $activo = null, $idActor = null, $actorNombre = null)
    {
        $id_usuario = (int) $id_usuario;
        $actual = self::obtenerPorId($id_usuario);
        if (!$actual) {
            throw new DomainException('El usuario no existe.');
        }

        // Si se intenta desactivar o cambiar de rol al último administrador
        if ($actual['rol'] === 'administrador' && self::cantidadAdministradores() <= 1) {
            if ($activo !== null && (int) $activo === 0) {
                throw new DomainException('No puedes desactivar al único administrador del sistema.');
            }
            if ($rol !== null && $rol !== 'administrador') {
                throw new DomainException('No puedes cambiar el rol del único administrador del sistema.');
            }
        }

        $params = [
            'id_usuario' => $id_usuario,
            'nombre' => $nombre,
            'correo' => $correo,
            'telefono' => $telefono,
        ];

        $roleSql = '';
        if ($rol !== null) {
            $rolInfo = Rol::obtenerPorNombre($rol) ?: Rol::obtenerPorId(is_numeric($rol) ? (int) $rol : 0);
            if (!$rolInfo || !in_array($rolInfo['nombre'], ['administrador', 'vendedor'], true)) {
                throw new InvalidArgumentException('El rol debe ser administrador o vendedor.');
            }
            $idRol = (int) $rolInfo['id_rol'];
            $rolNombre = $rolInfo['nombre'];
            $roleSql = ', id_rol = :id_rol';
            $params['id_rol'] = $idRol;
        }

        $passwordSql = '';
        if ($contrasena !== null && $contrasena !== '') {
            $passwordSql = ', contrasena_hash = :contrasena_hash';
            $params['contrasena_hash'] = password_hash($contrasena, PASSWORD_DEFAULT);
        }

        $activeSql = '';
        if ($activo !== null) {
            $activeSql = ', activo = :activo';
            $params['activo'] = (int) $activo;
        }

        $affected = self::ExecuteStatement(
            "UPDATE usuarios SET nombre = :nombre, correo = :correo, telefono = :telefono
             {$roleSql}{$passwordSql}{$activeSql} WHERE id_usuario = :id_usuario",
            $params
        );

        if ($affected > 0) {
            Auditoria::registrar(
                $idActor,
                $actorNombre,
                'ACTUALIZAR',
                'usuarios',
                $id_usuario,
                ['nombre' => $actual['nombre'], 'correo' => $actual['correo'], 'rol' => $actual['rol'], 'activo' => $actual['activo']],
                ['nombre' => $nombre, 'correo' => $correo, 'rol' => $rol ?? $actual['rol'], 'activo' => $activo ?? $actual['activo']],
                "Actualización de datos del usuario {$nombre}"
            );
        }

        return $affected;
    }

    public static function deleteUsuario($id, $idActor = null, $actorNombre = null)
    {
        $id = (int) $id;
        $actual = self::obtenerPorId($id);
        if (!$actual) {
            return 0;
        }
        if ($actual['rol'] === 'administrador' && self::cantidadAdministradores() <= 1) {
            throw new DomainException('No puedes desactivar al único administrador del sistema.');
        }

        $affected = self::ExecuteStatement("UPDATE usuarios SET activo = 0 WHERE id_usuario = :id AND activo = 1", ['id' => $id]);
        if ($affected > 0) {
            Auditoria::registrar(
                $idActor,
                $actorNombre,
                'DESACTIVAR',
                'usuarios',
                $id,
                ['activo' => 1],
                ['activo' => 0],
                "Desactivación del usuario {$actual['nombre']}"
            );
        }
        return $affected;
    }
}
