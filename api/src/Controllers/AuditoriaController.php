<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../Models/Auditoria.php';
require_once __DIR__ . '/../Config/AuthMiddleware.php';

class AuditoriaController extends ApiController
{
    public function listarAuditoria()
    {
        $this->run(function () {
            AuthMiddleware::esAdmin();

            $filtros = [];
            if (!empty($_GET['fecha_inicio'])) {
                $filtros['fecha_inicio'] = trim((string) $_GET['fecha_inicio']);
            }
            if (!empty($_GET['fecha_fin'])) {
                $filtros['fecha_fin'] = trim((string) $_GET['fecha_fin']);
            }
            if (!empty($_GET['accion'])) {
                $filtros['accion'] = trim((string) $_GET['accion']);
            }
            if (!empty($_GET['entidad'])) {
                $filtros['entidad'] = trim((string) $_GET['entidad']);
            }
            if (!empty($_GET['id_usuario'])) {
                $filtros['id_usuario'] = (int) $_GET['id_usuario'];
            }
            if (!empty($_GET['limite'])) {
                $filtros['limite'] = min(200, max(1, (int) $_GET['limite']));
            }

            return Auditoria::obtenerListado($filtros);
        });
    }
}
