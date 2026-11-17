<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/DetallePedido.php';

class DetallePedidoController extends ApiController
{
    public function listarDetallesPedidos()
    {
        $this->run(function () {
            $user = AuthMiddleware::autenticado();
            $idPedido = isset($_GET['id_pedido'])
                ? $this->positiveInteger($_GET['id_pedido'], 'id_pedido')
                : null;
            if (($user['rol'] ?? null) !== 'administrador') {
                if ($idPedido === null || !Pedido::perteneceAUsuario($idPedido, $user['id_usuario'])) {
                    throw new AuthorizationException('No tienes permiso para consultar estos detalles.');
                }
            }
            return DetallePedido::obtenerTodos($idPedido);
        });
    }

    public function agregarDetallePedido()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['id_pedido', 'cantidad']);
            $idProducto = $data['id_producto'] ?? null;
            if ($idProducto === null) {
                throw new InvalidArgumentException('Se requiere id_producto.');
            }
            $cantidad = $this->positiveInteger($data['cantidad'], 'cantidad');
            return ['success' => true] + DetallePedido::crear(
                $this->positiveInteger($data['id_pedido'], 'id_pedido'),
                $this->positiveInteger($idProducto, 'id_producto'),
                $cantidad
            );
        }, 201);
    }

    public function updateDetallePedido()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['id_detalle', 'id_producto', 'cantidad']);
            return ['success' => true, 'affected' => DetallePedido::updateDetallePedido(
                $this->positiveInteger($data['id_detalle'], 'id_detalle'),
                $this->positiveInteger($data['id_producto'], 'id_producto'),
                $this->positiveInteger($data['cantidad'], 'cantidad')
            )];
        });
    }

    public function deleteDetallePedido()
    {
        $this->run(function () {
            $data = $this->input();
            return ['success' => true, 'affected' => DetallePedido::deleteDetallePedido(
                $this->positiveInteger($data['id_detalle'] ?? null, 'id_detalle')
            )];
        });
    }
}
