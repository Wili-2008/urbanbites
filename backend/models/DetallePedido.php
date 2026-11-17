<?php
require_once __DIR__ . '/../config/ConexionPDO.php';
require_once __DIR__ . '/Pedido.php';

class DetallePedido extends ConexionPDO
{
    public static function obtenerTodos($idPedido = null)
    {
        $sql = "SELECT d.id_detalle, d.id_pedido, d.id_producto, p.nombre AS producto,
                       d.cantidad, d.precio_unitario, d.subtotal
                FROM detalle_pedidos d JOIN productos p ON p.id_producto = d.id_producto";
        $params = [];
        if ($idPedido !== null) {
            $sql .= " WHERE d.id_pedido = :id_pedido";
            $params['id_pedido'] = $idPedido;
        }
        $sql .= " ORDER BY d.id_detalle";
        return self::ExecuteQuery($sql, $params);
    }

    public static function crear($idPedido, $idProducto, $cantidad)
    {
        $pdo = self::Conexion();
        $pdo->beginTransaction();
        try {
            self::lockPendingOrder($pdo, $idPedido);
            $product = self::getAvailableProduct($pdo, $idProducto);
            $subtotalCents = Pedido::toCents($product['precio']) * $cantidad;
            if ($subtotalCents > 9999999999) {
                throw new InvalidArgumentException('El subtotal del detalle excede el máximo permitido.');
            }
            $subtotal = Pedido::fromCents($subtotalCents);
            $generatedSubtotal = self::IsGeneratedColumn('detalle_pedidos', 'subtotal');
            $columns = 'id_pedido, id_producto, cantidad, precio_unitario';
            $values = ':id_pedido, :id_producto, :cantidad, :precio';
            $params = [
                'id_pedido' => $idPedido,
                'id_producto' => $idProducto,
                'cantidad' => $cantidad,
                'precio' => $product['precio'],
            ];
            if (!$generatedSubtotal) {
                $columns .= ', subtotal';
                $values .= ', :subtotal';
                $params['subtotal'] = $subtotal;
            }
            $insert = $pdo->prepare("INSERT INTO detalle_pedidos ({$columns}) VALUES ({$values})");
            $insert->execute($params);
            Pedido::recalcularTotales($pdo, $idPedido);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
            return ['id_detalle' => $id];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function updateDetallePedido($idDetalle, $idProducto, $cantidad)
    {
        $pdo = self::Conexion();
        $pdo->beginTransaction();
        try {
            $currentQuery = $pdo->prepare(
                "SELECT d.id_pedido, p.estado
                 FROM detalle_pedidos d JOIN pedidos p ON p.id_pedido = d.id_pedido
                 WHERE d.id_detalle = :id FOR UPDATE"
            );
            $currentQuery->execute(['id' => $idDetalle]);
            $current = $currentQuery->fetch();
            if (!$current || $current['estado'] !== 'pendiente') {
                throw new DomainException('Solo se pueden modificar detalles de pedidos pendientes.');
            }
            $product = self::getAvailableProduct($pdo, $idProducto);
            $subtotalCents = Pedido::toCents($product['precio']) * $cantidad;
            if ($subtotalCents > 9999999999) {
                throw new InvalidArgumentException('El subtotal del detalle excede el máximo permitido.');
            }
            $subtotal = Pedido::fromCents($subtotalCents);
            $subtotalSql = self::IsGeneratedColumn('detalle_pedidos', 'subtotal') ? '' : ', subtotal = :subtotal';
            $params = [
                'id_producto' => $idProducto,
                'cantidad' => $cantidad,
                'precio' => $product['precio'],
                'id' => $idDetalle,
            ];
            if ($subtotalSql === '') {
                $sql = "UPDATE detalle_pedidos SET id_producto = :id_producto, cantidad = :cantidad,
                        precio_unitario = :precio WHERE id_detalle = :id";
            } else {
                $params['subtotal'] = $subtotal;
                $sql = "UPDATE detalle_pedidos SET id_producto = :id_producto, cantidad = :cantidad,
                        precio_unitario = :precio{$subtotalSql} WHERE id_detalle = :id";
            }
            $update = $pdo->prepare($sql);
            $update->execute($params);
            Pedido::recalcularTotales($pdo, $current['id_pedido']);
            $changed = $update->rowCount();
            $pdo->commit();
            return $changed;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function deleteDetallePedido($idDetalle)
    {
        $pdo = self::Conexion();
        $pdo->beginTransaction();
        try {
            $query = $pdo->prepare(
                "SELECT d.id_pedido, p.estado
                 FROM detalle_pedidos d JOIN pedidos p ON p.id_pedido = d.id_pedido
                 WHERE d.id_detalle = :id FOR UPDATE"
            );
            $query->execute(['id' => $idDetalle]);
            $detail = $query->fetch();
            if (!$detail) {
                $pdo->commit();
                return 0;
            }
            if ($detail['estado'] !== 'pendiente') {
                throw new DomainException('Solo se pueden eliminar detalles de pedidos pendientes.');
            }
            $delete = $pdo->prepare("DELETE FROM detalle_pedidos WHERE id_detalle = :id");
            $delete->execute(['id' => $idDetalle]);
            Pedido::recalcularTotales($pdo, $detail['id_pedido']);
            $changed = $delete->rowCount();
            $pdo->commit();
            return $changed;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private static function lockPendingOrder($pdo, $idPedido)
    {
        $query = $pdo->prepare("SELECT estado FROM pedidos WHERE id_pedido = :id FOR UPDATE");
        $query->execute(['id' => $idPedido]);
        $order = $query->fetch();
        if (!$order || $order['estado'] !== 'pendiente') {
            throw new DomainException('Solo se pueden agregar detalles a pedidos pendientes.');
        }
    }

    private static function getAvailableProduct($pdo, $idProducto)
    {
        $query = $pdo->prepare(
            "SELECT p.precio FROM productos p JOIN categorias c ON c.id_categoria = p.id_categoria
             WHERE p.id_producto = :id AND p.disponible = 1 AND c.activa = 1"
        );
        $query->execute(['id' => $idProducto]);
        $product = $query->fetch();
        if (!$product) {
            throw new DomainException('El producto no existe o no está disponible.');
        }
        return $product;
    }
}
