<?php
require_once __DIR__ . '/../config/ConexionPDO.php';

class Auditoria extends ConexionPDO
{
    public static function registrar($idUsuario, $usuarioNombre, $accion, $entidad, $entidadId = null, $datosAnteriores = null, $datosNuevos = null, $detalles = null, $ip = null)
    {
        $ip = $ip ?: ($_SERVER['REMOTE_ADDR'] ?? null);
        $antJSON = $datosAnteriores !== null ? json_encode($datosAnteriores, JSON_UNESCAPED_UNICODE) : null;
        $nueJSON = $datosNuevos !== null ? json_encode($datosNuevos, JSON_UNESCAPED_UNICODE) : null;

        // Se marca @audit_handled = 1 para que el trigger de BD no duplique el registro
        $pdo = self::Conexion();
        $pdo->exec("SET @audit_handled = 1");

        $sql = "INSERT INTO auditoria (id_usuario, usuario_nombre, accion, entidad, entidad_id, datos_anteriores, datos_nuevos, detalles, ip, fecha)
                VALUES (:id_usuario, :usuario_nombre, :accion, :entidad, :entidad_id, :datos_anteriores, :datos_nuevos, :detalles, :ip, NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_usuario' => $idUsuario,
            'usuario_nombre' => $usuarioNombre,
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'datos_anteriores' => $antJSON,
            'datos_nuevos' => $nueJSON,
            'detalles' => $detalles,
            'ip' => $ip,
        ]);

        $pdo->exec("SET @audit_handled = 0");
        return (int) $pdo->lastInsertId();
    }

    public static function obtenerListado($filtros = [])
    {
        $conditions = [];
        $params = [];

        if (!empty($filtros['fecha_inicio'])) {
            $conditions[] = "a.fecha >= :fecha_inicio";
            $params['fecha_inicio'] = $filtros['fecha_inicio'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_fin'])) {
            $conditions[] = "a.fecha <= :fecha_fin";
            $params['fecha_fin'] = $filtros['fecha_fin'] . ' 23:59:59';
        }
        if (!empty($filtros['accion'])) {
            $conditions[] = "a.accion = :accion";
            $params['accion'] = $filtros['accion'];
        }
        if (!empty($filtros['entidad'])) {
            $conditions[] = "a.entidad = :entidad";
            $params['entidad'] = $filtros['entidad'];
        }
        if (!empty($filtros['id_usuario'])) {
            $conditions[] = "a.id_usuario = :id_usuario";
            $params['id_usuario'] = (int) $filtros['id_usuario'];
        }

        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
        $limit = isset($filtros['limite']) ? (int) $filtros['limite'] : 100;

        $sql = "SELECT a.id_auditoria, a.id_usuario, COALESCE(u.nombre, a.usuario_nombre, 'Sistema') AS usuario,
                       r.nombre AS rol, a.accion, a.entidad, a.entidad_id, a.datos_anteriores,
                       a.datos_nuevos, a.detalles, a.ip, a.fecha
                FROM auditoria a
                LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario
                LEFT JOIN roles r ON r.id_rol = u.id_rol
                {$where}
                ORDER BY a.fecha DESC, a.id_auditoria DESC
                LIMIT {$limit}";

        return self::ExecuteQuery($sql, $params);
    }
}
