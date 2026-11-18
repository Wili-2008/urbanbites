<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../Models/Rol.php';
require_once __DIR__ . '/../Config/AuthMiddleware.php';

class RolController extends ApiController
{
    public function listarRoles()
    {
        $this->run(function () {
            AuthMiddleware::autenticado();
            return Rol::obtenerTodos();
        });
    }
}
