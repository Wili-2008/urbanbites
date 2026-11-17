<?php
require_once __DIR__ . '/../config/ConexionPDO.php';

class MovimientoInventario extends ConexionPDO
{
    public static function registrar($idProducto, $idUsuario, $tipo, $cantidad, $stockAnterior, $stockNuevo, $motivo = null, $referenciaId = null)
    {
        return self::InsertarSql(
            "INSERT INTO movimientos_inventario (id_producto, id_usuario, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, motivo, referencia_id, fecha)
             VALUES (:id_producto, :id_usuario, :tipo, :cantidad, :stock_anterior, :stock_nuevo, :motivo, :referencia_id, NOW())",
            [
                'id_producto' => $idProducto,
                'id_usuario' => $idUsuario,
                'tipo' => $tipo,
                'cantidad' => $cantidad,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo' => $stockNuevo,
                'motivo' => $motivo,
                'referencia_id' => $referenciaId,
            ]
        );
    }

    public static function obtenerPorProducto($idProducto, $limite = 50)
    {
        return self::ExecuteQuery(
            "SELECT m.id_movimiento, m.id_producto, p.nombre AS producto, p.codigo,
                    m.id_usuario, u.nombre AS usuario, m.tipo_movimiento, m.cantidad,
                    m.stock_anterior, m.stock_nuevo, m.motivo, m.referencia_id, m.fecha
             FROM movimientos_inventario m
             JOIN productos p ON p.id_producto = m.id_producto
             LEFT JOIN usuarios u ON u.id_usuario = m.id_usuario
             WHERE m.id_producto = :id_producto
             ORDER BY m.fecha DESC, m.id_movimiento DESC
             LIMIT :limite",
            ['id_producto' => $idProducto, 'limite' => (int) $limite]
        );
    }

    public static function obtenerRecientes($limite = 50)
    {
        return self::ExecuteQuery(
            "SELECT m.id_movimiento, m.id_producto, p.nombre AS producto, p.codigo,
                    m.id_usuario, COALESCE(u.nombre, 'Sistema') AS usuario, m.tipo_movimiento,
                    m.cantidad, m.stock_anterior, m.stock_nuevo, m.motivo, m.referencia_id, m.fecha
             FROM movimientos_inventario m
             JOIN productos p ON p.id_producto = m.id_producto
             LEFT JOIN usuarios u ON u.id_usuario = m.id_usuario
             ORDER BY m.fecha DESC, m.id_movimiento DESC
             LIMIT {$limite}"
        );
    }
}
