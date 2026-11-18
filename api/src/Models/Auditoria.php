<?php

/**
 * Bitacora local en JSON Lines. Se guarda fuera de frontend/ (DocumentRoot)
 * para mantener el SQL del proyecto en las seis tablas solicitadas.
 */
class Auditoria
{
    private static function filePath()
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'auditoria.log';
    }

    public static function registrar($idUsuario, $usuarioNombre, $accion, $entidad, $entidadId = null, $datosAnteriores = null, $datosNuevos = null, $detalles = null, $ip = null)
    {
        $path = self::filePath();
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo crear el directorio local de auditoría.');
        }

        $record = [
            'fecha' => date('Y-m-d H:i:s'),
            'id_usuario' => $idUsuario === null ? null : (int) $idUsuario,
            'usuario' => $usuarioNombre ?: 'Sistema',
            'accion' => (string) $accion,
            'entidad' => (string) $entidad,
            'entidad_id' => $entidadId === null ? null : (int) $entidadId,
            'datos_anteriores' => $datosAnteriores,
            'datos_nuevos' => $datosNuevos,
            'detalles' => $detalles,
            'ip' => $ip ?: ($_SERVER['REMOTE_ADDR'] ?? null),
        ];
        $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($line === false || file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('No se pudo guardar el evento en la bitácora local.');
        }
        return true;
    }

    public static function obtenerListado($filtros = [])
    {
        $path = self::filePath();
        if (!is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new RuntimeException('No se pudo leer la bitácora local.');
        }

        $records = [];
        foreach ($lines as $index => $line) {
            $record = json_decode($line, true);
            if (!is_array($record)) {
                continue;
            }
            $record['id_auditoria'] = $index + 1;
            foreach (['datos_anteriores', 'datos_nuevos'] as $field) {
                $record[$field] = $record[$field] === null
                    ? null
                    : json_encode($record[$field], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            }
            if (!empty($filtros['fecha_inicio']) && substr($record['fecha'], 0, 10) < $filtros['fecha_inicio']) {
                continue;
            }
            if (!empty($filtros['fecha_fin']) && substr($record['fecha'], 0, 10) > $filtros['fecha_fin']) {
                continue;
            }
            if (!empty($filtros['accion']) && $record['accion'] !== $filtros['accion']) {
                continue;
            }
            if (!empty($filtros['entidad']) && $record['entidad'] !== $filtros['entidad']) {
                continue;
            }
            if (!empty($filtros['id_usuario']) && (int) $record['id_usuario'] !== (int) $filtros['id_usuario']) {
                continue;
            }
            $records[] = $record;
        }

        $records = array_reverse($records);
        $limit = isset($filtros['limite']) ? min(500, max(1, (int) $filtros['limite'])) : 100;
        return array_slice($records, 0, $limit);
    }
}
