<?php
require_once __DIR__ . '/../Config/ConexionPDO.php';

class Reporte extends ConexionPDO
{
    public static function obtenerReporteVentas($fechaInicio, $fechaFin)
    {
        $fechaInicioStr = trim((string) $fechaInicio);
        $fechaFinStr = trim((string) $fechaFin);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicioStr) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFinStr)) {
            throw new InvalidArgumentException('Formato de fecha inválido. Se espera YYYY-MM-DD.');
        }

        if ($fechaInicioStr > $fechaFinStr) {
            throw new InvalidArgumentException('La fecha de inicio no puede ser posterior a la fecha de fin.');
        }

        $inicio = "{$fechaInicioStr} 00:00:00";
        $fin = "{$fechaFinStr} 23:59:59";

        // 1. Resumen general
        $resumenQuery = self::ExecuteQuery(
            "SELECT COUNT(DISTINCT v.id_venta) AS total_ventas,
                    COALESCE(SUM(v.subtotal), 0) AS subtotal_total,
                    COALESCE(SUM(v.descuento), 0) AS descuento_total,
                    COALESCE(SUM(v.total), 0) AS recaudacion_total,
                    COALESCE(SUM(dv.cantidad), 0) AS unidades_vendidas
             FROM ventas v
             LEFT JOIN detalle_ventas dv ON dv.id_venta = v.id_venta
             WHERE v.estado = 'completada' AND v.fecha_venta BETWEEN :inicio AND :fin",
            ['inicio' => $inicio, 'fin' => $fin]
        );

        $resumen = $resumenQuery[0] ?? [
            'total_ventas' => 0,
            'subtotal_total' => 0,
            'descuento_total' => 0,
            'recaudacion_total' => 0,
            'unidades_vendidas' => 0,
        ];

        $totalVentas = (int) $resumen['total_ventas'];
        $recaudacionTotal = (float) $resumen['recaudacion_total'];
        $ticketPromedio = $totalVentas > 0 ? round($recaudacionTotal / $totalVentas, 2) : 0.00;

        // 2. Ventas por método de pago
        $metodos = self::ExecuteQuery(
            "SELECT v.metodo_pago,
                    COUNT(v.id_venta) AS transacciones,
                    COALESCE(SUM(v.total), 0) AS total_recaudado
             FROM ventas v
             WHERE v.estado = 'completada' AND v.fecha_venta BETWEEN :inicio AND :fin
             GROUP BY v.metodo_pago
             ORDER BY total_recaudado DESC",
            ['inicio' => $inicio, 'fin' => $fin]
        );

        // 3. Productos más vendidos
        $productosTop = self::ExecuteQuery(
            "SELECT p.id_producto, p.codigo, p.nombre, c.nombre AS categoria,
                    SUM(dv.cantidad) AS unidades_vendidas,
                    SUM(dv.subtotal) AS total_recaudado
             FROM detalle_ventas dv
             JOIN ventas v ON v.id_venta = dv.id_venta
             JOIN productos p ON p.id_producto = dv.id_producto
             JOIN categorias c ON c.id_categoria = p.id_categoria
             WHERE v.estado = 'completada' AND v.fecha_venta BETWEEN :inicio AND :fin
             GROUP BY p.id_producto, p.codigo, p.nombre, c.nombre
             ORDER BY unidades_vendidas DESC, total_recaudado DESC
             LIMIT 15",
            ['inicio' => $inicio, 'fin' => $fin]
        );

        // 4. Ventas por vendedor
        $vendedores = self::ExecuteQuery(
            "SELECT u.id_usuario, u.nombre AS vendedor, r.nombre AS rol,
                    COUNT(v.id_venta) AS total_ventas,
                    COALESCE(SUM(v.total), 0) AS total_recaudado
             FROM ventas v
             JOIN usuarios u ON u.id_usuario = v.id_usuario
             LEFT JOIN roles r ON r.id_rol = u.id_rol
             WHERE v.estado = 'completada' AND v.fecha_venta BETWEEN :inicio AND :fin
             GROUP BY u.id_usuario, u.nombre, r.nombre
             ORDER BY total_recaudado DESC",
            ['inicio' => $inicio, 'fin' => $fin]
        );

        // 5. Listado de ventas en el período
        $ventasPeriodo = self::ExecuteQuery(
            "SELECT v.id_venta, v.numero_venta, v.fecha_venta, u.nombre AS vendedor,
                    v.cliente_nombre, v.total, v.metodo_pago, v.estado,
                    (SELECT COUNT(*) FROM detalle_ventas dv WHERE dv.id_venta = v.id_venta) AS items_count
             FROM ventas v
             JOIN usuarios u ON u.id_usuario = v.id_usuario
             WHERE v.fecha_venta BETWEEN :inicio AND :fin
             ORDER BY v.fecha_venta DESC, v.id_venta DESC
             LIMIT 100",
            ['inicio' => $inicio, 'fin' => $fin]
        );

        return [
            'rango' => [
                'fecha_inicio' => $fechaInicioStr,
                'fecha_fin' => $fechaFinStr,
            ],
            'resumen' => [
                'total_ventas' => $totalVentas,
                'subtotal_total' => (float) $resumen['subtotal_total'],
                'descuento_total' => (float) $resumen['descuento_total'],
                'recaudacion_total' => $recaudacionTotal,
                'unidades_vendidas' => (int) $resumen['unidades_vendidas'],
                'ticket_promedio' => $ticketPromedio,
            ],
            'ventas_por_metodo' => $metodos,
            'productos_mas_vendidos' => $productosTop,
            'ventas_por_vendedor' => $vendedores,
            'ventas' => $ventasPeriodo,
        ];
    }
}
