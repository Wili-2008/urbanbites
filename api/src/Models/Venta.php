<?php
require_once __DIR__ . '/../Config/ConexionPDO.php';
require_once __DIR__ . '/Auditoria.php';

class Venta extends ConexionPDO
{
    public static function generarNumeroVenta()
    {
        $fecha = date('Ymd');
        $random = strtoupper(bin2hex(random_bytes(2)));
        $prefix = "VTA-{$fecha}-";

        $rows = self::ExecuteQuery(
            "SELECT numero_venta FROM ventas WHERE numero_venta LIKE :prefix ORDER BY id_venta DESC LIMIT 1",
            ['prefix' => "{$prefix}%"]
        );

        if ($rows) {
            $last = $rows[0]['numero_venta'];
            $part = explode('-', $last);
            $seq = isset($part[2]) ? (int) $part[2] + 1 : 1;
            return sprintf("%s%04d", $prefix, $seq);
        }

        return "{$prefix}0001";
    }

    public static function crearConDetalles($idUsuario, array $items, array $datosVenta, $usuarioNombre = null)
    {
        if (empty($items)) {
            throw new InvalidArgumentException('La venta debe incluir al menos un producto.');
        }

        $idUsuario = filter_var($idUsuario, FILTER_VALIDATE_INT);
        if (!$idUsuario || $idUsuario < 1) {
            throw new InvalidArgumentException('Usuario/Vendedor no válido.');
        }

        $clienteNombre = trim((string) ($datosVenta['cliente_nombre'] ?? 'Consumidor Final')) ?: 'Consumidor Final';
        $clienteDocumento = trim((string) ($datosVenta['cliente_documento'] ?? '')) ?: null;
        $metodoPago = strtolower(trim((string) ($datosVenta['metodo_pago'] ?? 'efectivo')));
        $validMetodos = ['efectivo', 'tarjeta', 'qr', 'transferencia'];
        if (!in_array($metodoPago, $validMetodos, true)) {
            throw new InvalidArgumentException('Método de pago no válido. Permitidos: efectivo, tarjeta, qr, transferencia.');
        }

        $descuento = isset($datosVenta['descuento']) ? max(0, (float) $datosVenta['descuento']) : 0.00;
        $montoRecibido = isset($datosVenta['monto_recibido']) && $datosVenta['monto_recibido'] !== ''
            ? (float) $datosVenta['monto_recibido']
            : null;

        $observaciones = isset($datosVenta['observaciones']) ? trim((string) $datosVenta['observaciones']) : null;

        $pdo = self::Conexion();
        $pdo->beginTransaction();

        try {
            // 1. Validar que el vendedor exista y esté activo
            $stmtUser = $pdo->prepare(
                "SELECT u.id_usuario, u.nombre, r.nombre AS rol, u.activo
                 FROM usuarios u
                 JOIN roles r ON r.id_rol = u.id_rol
                 WHERE u.id_usuario = :id FOR UPDATE"
            );
            $stmtUser->execute(['id' => $idUsuario]);
            $vendedor = $stmtUser->fetch();
            if (!$vendedor || !(int) $vendedor['activo']) {
                throw new DomainException('El vendedor no existe o su cuenta está desactivada.');
            }
            if (!in_array($vendedor['rol'], ['vendedor', 'administrador'], true)) {
                throw new DomainException('El usuario no tiene permisos para realizar ventas.');
            }
            $vendedorNombre = $vendedor['nombre'];

            // 2. Bloquear y verificar existencias y precios en el servidor con SELECT ... FOR UPDATE
            $stmtProd = $pdo->prepare(
                "SELECT p.id_producto, p.codigo, p.nombre, p.precio, p.stock, p.disponible, c.activa AS categoria_activa
                 FROM productos p
                 JOIN categorias c ON c.id_categoria = p.id_categoria
                 WHERE p.id_producto = :id FOR UPDATE"
            );

            $detalleProcesado = [];
            $subtotalCalculado = 0.00;

            // Agrupar items por id_producto en caso de duplicados en la petición
            $itemsAgrupados = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    throw new InvalidArgumentException('Formato de artículo inválido.');
                }
                $idProducto = filter_var($item['id_producto'] ?? null, FILTER_VALIDATE_INT);
                $cantidad = filter_var($item['cantidad'] ?? null, FILTER_VALIDATE_INT);
                if (!$idProducto || $idProducto < 1) {
                    throw new InvalidArgumentException('Identificador de producto no válido.');
                }
                if (!$cantidad || $cantidad < 1) {
                    throw new InvalidArgumentException("La cantidad debe ser mayor a 0 para cada producto.");
                }
                if (!isset($itemsAgrupados[$idProducto])) {
                    $itemsAgrupados[$idProducto] = 0;
                }
                $itemsAgrupados[$idProducto] += $cantidad;
            }

            foreach ($itemsAgrupados as $idProducto => $cantidadTotal) {
                $stmtProd->execute(['id' => $idProducto]);
                $producto = $stmtProd->fetch();

                if (!$producto) {
                    throw new DomainException("El producto con ID {$idProducto} no existe en el catálogo.");
                }
                if (!(int) $producto['disponible'] || !(int) $producto['categoria_activa']) {
                    throw new DomainException("El producto '{$producto['nombre']}' no se encuentra disponible para la venta.");
                }

                $stockActual = (int) $producto['stock'];
                if ($stockActual < $cantidadTotal) {
                    throw new DomainException("Stock insuficiente para '{$producto['nombre']}' ({$producto['codigo']}). Disponibles: {$stockActual}, solicitados: {$cantidadTotal}.");
                }

                $precioUnitario = (float) $producto['precio'];
                $lineSubtotal = round($precioUnitario * $cantidadTotal, 2);
                $subtotalCalculado += $lineSubtotal;

                $detalleProcesado[] = [
                    'id_producto' => $idProducto,
                    'codigo' => $producto['codigo'],
                    'nombre' => $producto['nombre'],
                    'cantidad' => $cantidadTotal,
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => $lineSubtotal,
                    'stock_anterior' => $stockActual,
                    'stock_nuevo' => $stockActual - $cantidadTotal,
                ];
            }

            $subtotalCalculado = round($subtotalCalculado, 2);
            $totalCalculado = round(max(0, $subtotalCalculado - $descuento), 2);

            // Validar monto recibido en efectivo
            $cambio = 0.00;
            if ($metodoPago === 'efectivo') {
                if ($montoRecibido !== null && $montoRecibido < $totalCalculado) {
                    throw new InvalidArgumentException("El monto recibido (Bs {$montoRecibido}) es menor al total a pagar (Bs {$totalCalculado}).");
                }
                $cambio = $montoRecibido !== null ? round($montoRecibido - $totalCalculado, 2) : 0.00;
            }

            // 3. Generar número de venta correlativo
            $numeroVenta = self::generarNumeroVenta();

            // 4. Insertar la venta
            $stmtVenta = $pdo->prepare(
                "INSERT INTO ventas (id_usuario, numero_venta, fecha_venta, cliente_nombre, cliente_documento,
                                     subtotal, descuento, total, metodo_pago, monto_recibido, cambio, estado, observaciones)
                 VALUES (:id_usuario, :numero_venta, NOW(), :cliente_nombre, :cliente_documento,
                         :subtotal, :descuento, :total, :metodo_pago, :monto_recibido, :cambio, 'completada', :observaciones)"
            );
            $stmtVenta->execute([
                'id_usuario' => $idUsuario,
                'numero_venta' => $numeroVenta,
                'cliente_nombre' => $clienteNombre,
                'cliente_documento' => $clienteDocumento,
                'subtotal' => $subtotalCalculado,
                'descuento' => $descuento,
                'total' => $totalCalculado,
                'metodo_pago' => $metodoPago,
                'monto_recibido' => $montoRecibido,
                'cambio' => $cambio,
                'observaciones' => $observaciones,
            ]);
            $idVenta = (int) $pdo->lastInsertId();

            // 5. Insertar detalles de la venta y descontar stock
            $stmtDetalle = $pdo->prepare(
                "INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio_unitario, subtotal)
                 VALUES (:id_venta, :id_producto, :cantidad, :precio_unitario, :subtotal)"
            );
            $stmtUpdateStock = $pdo->prepare(
                "UPDATE productos SET stock = stock - :cantidad WHERE id_producto = :id"
            );
            foreach ($detalleProcesado as $line) {
                // Insertar detalle
                $stmtDetalle->execute([
                    'id_venta' => $idVenta,
                    'id_producto' => $line['id_producto'],
                    'cantidad' => $line['cantidad'],
                    'precio_unitario' => $line['precio_unitario'],
                    'subtotal' => $line['subtotal'],
                ]);

                // Descontar existencias
                $stmtUpdateStock->execute([
                    'cantidad' => $line['cantidad'],
                    'id' => $line['id_producto'],
                ]);

            }

            $pdo->commit();

            // La bitácora está en archivo y no participa en la transacción SQL.
            // Se registra después del commit para no conservar eventos de ventas revertidas.
            $auditWarning = null;
            try {
                Auditoria::registrar(
                    $idUsuario,
                    $vendedorNombre,
                    'VENTA_REGISTRADA',
                    'ventas',
                    $idVenta,
                    null,
                    [
                        'numero_venta' => $numeroVenta,
                        'total' => $totalCalculado,
                        'metodo_pago' => $metodoPago,
                        'cliente_nombre' => $clienteNombre,
                        'articulos_totales' => count($detalleProcesado),
                        'items' => array_map(static function ($line) {
                            return [
                                'id_producto' => $line['id_producto'],
                                'codigo' => $line['codigo'],
                                'nombre' => $line['nombre'],
                                'cantidad' => $line['cantidad'],
                                'precio_unitario' => $line['precio_unitario'],
                                'subtotal' => $line['subtotal'],
                                'stock_anterior' => $line['stock_anterior'],
                                'stock_nuevo' => $line['stock_nuevo'],
                            ];
                        }, $detalleProcesado),
                    ],
                    "Venta {$numeroVenta} registrada por Bs {$totalCalculado} a {$clienteNombre}"
                );
            } catch (Throwable $auditError) {
                $auditWarning = 'La venta se guardó, pero no se pudo escribir la bitácora: ' . $auditError->getMessage();
            }

            return [
                'id_venta' => $idVenta,
                'numero_venta' => $numeroVenta,
                'cliente_nombre' => $clienteNombre,
                'subtotal' => $subtotalCalculado,
                'descuento' => $descuento,
                'total' => $totalCalculado,
                'metodo_pago' => $metodoPago,
                'monto_recibido' => $montoRecibido,
                'cambio' => $cambio,
                'items' => $detalleProcesado,
                'fecha_venta' => date('Y-m-d H:i:s'),
                'advertencia_auditoria' => $auditWarning,
            ];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function obtenerTodas($filtros = [])
    {
        $conditions = [];
        $params = [];

        if (!empty($filtros['id_usuario'])) {
            $conditions[] = "v.id_usuario = :id_usuario";
            $params['id_usuario'] = (int) $filtros['id_usuario'];
        }
        if (!empty($filtros['fecha_inicio'])) {
            $conditions[] = "v.fecha_venta >= :fecha_inicio";
            $params['fecha_inicio'] = $filtros['fecha_inicio'] . ' 00:00:00';
        }
        if (!empty($filtros['fecha_fin'])) {
            $conditions[] = "v.fecha_venta <= :fecha_fin";
            $params['fecha_fin'] = $filtros['fecha_fin'] . ' 23:59:59';
        }
        if (!empty($filtros['estado'])) {
            $conditions[] = "v.estado = :estado";
            $params['estado'] = $filtros['estado'];
        }

        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
        $limit = isset($filtros['limite']) ? (int) $filtros['limite'] : 100;

        return self::ExecuteQuery(
            "SELECT v.id_venta, v.id_usuario, u.nombre AS vendedor, r.nombre AS rol_vendedor,
                    v.numero_venta, v.fecha_venta, v.cliente_nombre, v.cliente_documento,
                    v.subtotal, v.descuento, v.total, v.metodo_pago, v.monto_recibido,
                    v.cambio, v.estado, v.observaciones,
                    (SELECT COUNT(*) FROM detalle_ventas dv WHERE dv.id_venta = v.id_venta) AS total_items
             FROM ventas v
             JOIN usuarios u ON u.id_usuario = v.id_usuario
             LEFT JOIN roles r ON r.id_rol = u.id_rol
             {$where}
             ORDER BY v.fecha_venta DESC, v.id_venta DESC
             LIMIT {$limit}",
            $params
        );
    }

    public static function obtenerPorId($idVenta)
    {
        $rows = self::ExecuteQuery(
            "SELECT v.id_venta, v.id_usuario, u.nombre AS vendedor, u.correo AS vendedor_correo,
                    v.numero_venta, v.fecha_venta, v.cliente_nombre, v.cliente_documento,
                    v.subtotal, v.descuento, v.total, v.metodo_pago, v.monto_recibido,
                    v.cambio, v.estado, v.observaciones
             FROM ventas v
             JOIN usuarios u ON u.id_usuario = v.id_usuario
             WHERE v.id_venta = :id",
            ['id' => $idVenta]
        );
        if (!$rows) {
            return null;
        }

        $venta = $rows[0];
        $venta['items'] = self::obtenerDetalles($idVenta);
        return $venta;
    }

    public static function obtenerDetalles($idVenta)
    {
        return self::ExecuteQuery(
            "SELECT dv.id_detalle_venta, dv.id_producto, p.codigo, p.nombre AS producto,
                    c.nombre AS categoria, dv.cantidad, dv.precio_unitario, dv.subtotal
             FROM detalle_ventas dv
             JOIN productos p ON p.id_producto = dv.id_producto
             JOIN categorias c ON c.id_categoria = p.id_categoria
             WHERE dv.id_venta = :id_venta
             ORDER BY dv.id_detalle_venta ASC",
            ['id_venta' => $idVenta]
        );
    }
}
