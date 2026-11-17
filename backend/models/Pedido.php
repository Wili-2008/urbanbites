<?php
require_once __DIR__ . '/../config/ConexionPDO.php';

class Pedido extends ConexionPDO
{
    private static $estados = ['pendiente', 'aceptado', 'en_preparacion', 'listo', 'entregado', 'cancelado'];

    public static function obtenerTodos($idUsuario = null)
    {
        $where = $idUsuario === null ? '' : 'WHERE p.id_usuario = :id_usuario';
        return self::ExecuteQuery(
            "SELECT p.id_pedido, p.id_usuario, u.nombre AS usuario, u.correo, p.fecha_pedido,
                    p.tipo_entrega, p.direccion_entrega, p.referencia_entrega, p.subtotal,
                    p.costo_envio, p.total, p.estado, p.observaciones
             FROM pedidos p JOIN usuarios u ON u.id_usuario = p.id_usuario
             {$where}
             ORDER BY p.fecha_pedido DESC, p.id_pedido DESC"
            ,
            $idUsuario === null ? [] : ['id_usuario' => $idUsuario]
        );
    }

    public static function perteneceAUsuario($idPedido, $idUsuario)
    {
        $rows = self::ExecuteQuery(
            "SELECT id_pedido FROM pedidos WHERE id_pedido = :id_pedido AND id_usuario = :id_usuario",
            ['id_pedido' => $idPedido, 'id_usuario' => $idUsuario]
        );
        return (bool) $rows;
    }

    public static function crearConItems($idUsuario, array $items, array $data)
    {
        if (!$items) {
            throw new InvalidArgumentException('El pedido debe incluir al menos un producto.');
        }
        $tipoEntrega = $data['tipo_entrega'] ?? 'recoger';
        if (!in_array($tipoEntrega, ['recoger', 'domicilio'], true)) {
            throw new InvalidArgumentException('tipo_entrega debe ser recoger o domicilio.');
        }
        $direccionValue = $data['direccion_entrega'] ?? '';
        $referencia = $data['referencia_entrega'] ?? null;
        $observaciones = $data['observaciones'] ?? null;
        if (!is_string($direccionValue)
            || ($referencia !== null && !is_string($referencia))
            || ($observaciones !== null && !is_string($observaciones))) {
            throw new InvalidArgumentException('Dirección, referencia y observaciones deben ser texto.');
        }
        $direccion = trim($direccionValue);
        if ($tipoEntrega === 'domicilio' && $direccion === '') {
            throw new InvalidArgumentException('La dirección es obligatoria para la entrega a domicilio.');
        }
        if (strlen($direccion) > 255 || ($referencia !== null && strlen($referencia) > 255)
            || ($observaciones !== null && strlen($observaciones) > 500)) {
            throw new InvalidArgumentException('Dirección/referencia exceden 255 caracteres u observaciones exceden 500.');
        }

        $pdo = self::Conexion();
        $pdo->beginTransaction();
        try {
            $userQuery = $pdo->prepare("SELECT activo, telefono FROM usuarios WHERE id_usuario = :id FOR UPDATE");
            $userQuery->execute(['id' => $idUsuario]);
            $user = $userQuery->fetch();
            if (!$user || !(int) $user['activo']) {
                throw new DomainException('El usuario no existe o está desactivado.');
            }
            $telefonoContacto = $data['telefono_contacto'] ?? $user['telefono'];
            if ($telefonoContacto !== null && !is_string($telefonoContacto)) {
                throw new InvalidArgumentException('telefono_contacto debe ser texto.');
            }
            $telefonoContacto = trim((string) $telefonoContacto);
            $requiresPhone = self::HasColumn('pedidos', 'telefono_contacto');
            if ($requiresPhone && ($telefonoContacto === '' || strlen($telefonoContacto) > 25)) {
                throw new InvalidArgumentException('Se requiere telefono_contacto de hasta 25 caracteres.');
            }

            $configQuery = $pdo->query("SELECT costo_envio FROM configuracion WHERE id_configuracion = 1");
            $config = $configQuery->fetch();
            if (!$config) {
                throw new DomainException('No se encontró la configuración del negocio.');
            }
            $shippingCents = $tipoEntrega === 'domicilio'
                ? self::toCents($config['costo_envio'])
                : 0;

            $productQuery = $pdo->prepare(
                "SELECT p.id_producto, p.precio
                 FROM productos p JOIN categorias c ON c.id_categoria = p.id_categoria
                 WHERE p.id_producto = :id AND p.disponible = 1 AND c.activa = 1"
            );
            $lines = [];
            $subtotalCents = 0;
            foreach ($items as $item) {
                if (!is_array($item)) {
                    throw new InvalidArgumentException('Cada artículo debe incluir id_producto y cantidad.');
                }
                $idProducto = filter_var($item['id_producto'] ?? null, FILTER_VALIDATE_INT);
                $cantidad = filter_var($item['cantidad'] ?? null, FILTER_VALIDATE_INT);
                if ($idProducto === false || $idProducto < 1 || $cantidad === false || $cantidad < 1) {
                    throw new InvalidArgumentException('Cada artículo debe tener un producto y cantidad válidos.');
                }
                $productQuery->execute(['id' => $idProducto]);
                $product = $productQuery->fetch();
                if (!$product) {
                    throw new DomainException("El producto {$idProducto} no existe o no está disponible.");
                }
                $unitCents = self::toCents($product['precio']);
                $lineCents = $unitCents * $cantidad;
                $subtotalCents += $lineCents;
                if ($lineCents > 9999999999 || $subtotalCents > 9999999999) {
                    throw new InvalidArgumentException('El subtotal excede el máximo permitido para un pedido.');
                }
                $lines[] = [
                    'id_producto' => $idProducto,
                    'cantidad' => $cantidad,
                    'precio_unitario' => self::fromCents($unitCents),
                    'subtotal' => self::fromCents($lineCents),
                ];
            }

            $subtotal = self::fromCents($subtotalCents);
            $shipping = self::fromCents($shippingCents);
            if ($shippingCents > 9999999999 || $subtotalCents + $shippingCents > 9999999999) {
                throw new InvalidArgumentException('El costo de envío o el total excede el máximo permitido.');
            }
            $total = self::fromCents($subtotalCents + $shippingCents);
            $orderColumns = 'id_usuario, tipo_entrega, direccion_entrega, referencia_entrega, subtotal, costo_envio, total, observaciones';
            $orderValues = ':id_usuario, :tipo_entrega, :direccion, :referencia, :subtotal, :costo_envio, :total, :observaciones';
            $orderParams = [
                'id_usuario' => $idUsuario,
                'tipo_entrega' => $tipoEntrega,
                'direccion' => $tipoEntrega === 'domicilio' ? $direccion : null,
                'referencia' => $referencia,
                'subtotal' => $subtotal,
                'costo_envio' => $shipping,
                'total' => $total,
                'observaciones' => $observaciones,
            ];
            if ($requiresPhone) {
                $orderColumns .= ', telefono_contacto';
                $orderValues .= ', :telefono_contacto';
                $orderParams['telefono_contacto'] = $telefonoContacto;
            }
            $insertOrder = $pdo->prepare("INSERT INTO pedidos ({$orderColumns}) VALUES ({$orderValues})");
            $insertOrder->execute($orderParams);
            $idPedido = (int) $pdo->lastInsertId();
            $generatedSubtotal = self::IsGeneratedColumn('detalle_pedidos', 'subtotal');
            $lineColumns = 'id_pedido, id_producto, cantidad, precio_unitario';
            $lineValues = ':id_pedido, :id_producto, :cantidad, :precio_unitario';
            if (!$generatedSubtotal) {
                $lineColumns .= ', subtotal';
                $lineValues .= ', :subtotal';
            }
            $insertLine = $pdo->prepare("INSERT INTO detalle_pedidos ({$lineColumns}) VALUES ({$lineValues})");
            foreach ($lines as $line) {
                $lineParams = [
                    'id_pedido' => $idPedido,
                    'id_producto' => $line['id_producto'],
                    'cantidad' => $line['cantidad'],
                    'precio_unitario' => $line['precio_unitario'],
                ];
                if (!$generatedSubtotal) {
                    $lineParams['subtotal'] = $line['subtotal'];
                }
                $insertLine->execute($lineParams);
            }
            $pdo->commit();
            return [
                'id_pedido' => $idPedido,
                'subtotal' => $subtotal,
                'costo_envio' => $shipping,
                'total' => $total,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function updatePedido($idPedido, $estado)
    {
        if (!in_array($estado, self::$estados, true)) {
            throw new InvalidArgumentException('El estado del pedido no es válido.');
        }
        return self::ExecuteStatement(
            "UPDATE pedidos SET estado = :estado WHERE id_pedido = :id",
            ['estado' => $estado, 'id' => $idPedido]
        );
    }

    public static function deletePedido($idPedido)
    {
        return self::ExecuteStatement(
            "UPDATE pedidos SET estado = 'cancelado'
             WHERE id_pedido = :id AND estado <> 'cancelado'",
            ['id' => $idPedido]
        );
    }

    public static function recalcularTotales($pdo, $idPedido)
    {
        $totalsQuery = $pdo->prepare(
            "SELECT p.costo_envio, COALESCE(SUM(d.subtotal), 0) AS subtotal
             FROM pedidos p LEFT JOIN detalle_pedidos d ON d.id_pedido = p.id_pedido
             WHERE p.id_pedido = :id GROUP BY p.id_pedido"
        );
        $totalsQuery->execute(['id' => $idPedido]);
        $totals = $totalsQuery->fetch();
        if (!$totals || self::toCents($totals['subtotal']) + self::toCents($totals['costo_envio']) > 9999999999) {
            throw new InvalidArgumentException('El total excede el máximo permitido para un pedido.');
        }
        $statement = $pdo->prepare(
            "UPDATE pedidos SET
                subtotal = (SELECT COALESCE(SUM(subtotal), 0) FROM detalle_pedidos WHERE id_pedido = :id_subtotal),
                total = (SELECT COALESCE(SUM(subtotal), 0) FROM detalle_pedidos WHERE id_pedido = :id_total) + costo_envio
             WHERE id_pedido = :id_pedido"
        );
        $statement->execute([
            'id_subtotal' => $idPedido,
            'id_total' => $idPedido,
            'id_pedido' => $idPedido,
        ]);
    }

    public static function toCents($amount)
    {
        return (int) round((float) $amount * 100);
    }

    public static function fromCents($amount)
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
