<?php
require_once __DIR__ . '/../Config/ConexionPDO.php';

class DetalleVenta extends ConexionPDO
{
    public static function obtenerPorVenta($idVenta)
    {
        return self::ExecuteQuery(
            "SELECT dv.id_detalle_venta, dv.id_venta, dv.id_producto, p.codigo,
                    p.nombre AS producto, c.nombre AS categoria, dv.cantidad,
                    dv.precio_unitario, dv.subtotal
             FROM detalle_ventas dv
             JOIN productos p ON p.id_producto = dv.id_producto
             JOIN categorias c ON c.id_categoria = p.id_categoria
             WHERE dv.id_venta = :id_venta
             ORDER BY dv.id_detalle_venta ASC",
            ['id_venta' => (int) $idVenta]
        );
    }
}
