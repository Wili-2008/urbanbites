<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/Rol.php';
require_once __DIR__ . '/../config/AuthMiddleware.php';

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
