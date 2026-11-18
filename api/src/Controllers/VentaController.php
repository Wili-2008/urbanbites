<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../Models/Venta.php';
require_once __DIR__ . '/../Config/AuthMiddleware.php';

class VentaController extends ApiController
{
    public function agregarVenta()
    {
        $this->run(function () {
            $user = AuthMiddleware::esVendedorOAdmin();
            $data = $this->input();
            $this->requireFields($data, ['items']);

            $items = $data['items'];
            if (!is_array($items) || empty($items)) {
                throw new InvalidArgumentException('El carrito de venta debe contener al menos un producto.');
            }

            $resultado = Venta::crearConDetalles(
                $user['id_usuario'],
                $items,
                $data,
                $user['nombre']
            );

            return [
                'success' => true,
                'message' => "Venta {$resultado['numero_venta']} registrada exitosamente.",
                'venta' => $resultado,
            ];
        }, 201);
    }

    public function listarVentas()
    {
        $this->run(function () {
            $user = AuthMiddleware::esVendedorOAdmin();

            $filtros = [];
            if ($user['rol'] === 'vendedor') {
                // El vendedor solo puede consultar sus propias ventas
                $filtros['id_usuario'] = (int) $user['id_usuario'];
            } elseif (!empty($_GET['id_usuario'])) {
                $filtros['id_usuario'] = (int) $_GET['id_usuario'];
            }

            if (!empty($_GET['fecha_inicio'])) {
                $filtros['fecha_inicio'] = trim((string) $_GET['fecha_inicio']);
            }
            if (!empty($_GET['fecha_fin'])) {
                $filtros['fecha_fin'] = trim((string) $_GET['fecha_fin']);
            }
            if (!empty($_GET['estado'])) {
                $filtros['estado'] = trim((string) $_GET['estado']);
            }
            if (!empty($_GET['limite'])) {
                $filtros['limite'] = min(200, max(1, (int) $_GET['limite']));
            }

            return Venta::obtenerTodas($filtros);
        });
    }

    public function obtenerVenta()
    {
        $this->run(function () {
            $user = AuthMiddleware::esVendedorOAdmin();
            $id = $this->positiveInteger($_GET['id_venta'] ?? null, 'id_venta');

            $venta = Venta::obtenerPorId($id);
            if (!$venta) {
                throw new DomainException('No se encontró la venta solicitada.');
            }

            if ($user['rol'] === 'vendedor' && (int) $venta['id_usuario'] !== (int) $user['id_usuario']) {
                throw new AuthorizationException('No tienes permiso para consultar esta venta.');
            }

            return $venta;
        });
    }
}
