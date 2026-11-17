<?php
require_once __DIR__ . '/../config/ConexionPDO.php';

class Configuracion extends ConexionPDO
{
    public static function obtener()
    {
        $rows = self::ExecuteQuery(
            "SELECT id_configuracion, nombre_negocio, costo_envio
             FROM configuracion WHERE id_configuracion = 1"
        );
        return $rows ? $rows[0] : null;
    }

    public static function actualizar($nombreNegocio, $costoEnvio)
    {
        return self::ExecuteStatement(
            "UPDATE configuracion SET nombre_negocio = :nombre, costo_envio = :costo
             WHERE id_configuracion = 1",
            ['nombre' => $nombreNegocio, 'costo' => $costoEnvio]
        );
    }
}
