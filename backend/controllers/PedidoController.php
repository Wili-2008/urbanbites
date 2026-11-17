<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/Pedido.php';

class PedidoController extends ApiController
{
    public function listarPedidos()
    {
        $this->run(function () {
            $user = AuthMiddleware::autenticado();
            return Pedido::obtenerTodos(
                ($user['rol'] ?? null) === 'administrador'
                    ? null
                    : (int) $user['id_usuario']
            );
        });
    }

    public function agregarPedido()
    {
        $this->run(function () {
            $data = $this->input();
            $idUsuario = $data['id_usuario'] ?? $data['id_cliente'] ?? null;
            $idUsuario = $this->positiveInteger($idUsuario, 'id_usuario');
            AuthMiddleware::puedeActuarComo($idUsuario);
            if (!isset($data['items']) || !is_array($data['items'])) {
                throw new InvalidArgumentException('items debe ser una lista de productos y cantidades.');
            }
            return ['success' => true] + Pedido::crearConItems($idUsuario, $data['items'], $data);
        }, 201);
    }

    public function updatePedido()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['id_pedido']);
            if (!isset($data['estado'])) {
                throw new InvalidArgumentException('El campo estado es obligatorio.');
            }
            return ['success' => true, 'affected' => Pedido::updatePedido(
                $this->positiveInteger($data['id_pedido'], 'id_pedido'),
                $data['estado']
            )];
        });
    }

    public function deletePedido()
    {
        $this->run(function () {
            $data = $this->input();
            return ['success' => true, 'affected' => Pedido::deletePedido(
                $this->positiveInteger($data['id_pedido'] ?? null, 'id_pedido')
            )];
        });
    }
}
