<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/Usuario.php';

class UsuarioController extends ApiController
{
    public function listarUsuarios()
    {
        $this->run(function () {
            return Usuario::obtenerTodos();
        });
    }

    public function agregarUsuario()
    {
        $this->run(function () {
            $data = $this->input();
            $correo = $data['correo'] ?? $data['email'] ?? null;
            if (!isset($data['nombre'], $correo) || !isset($data['contrasena']) && !isset($data['password'])) {
                throw new InvalidArgumentException('Nombre, correo y contraseña son obligatorios.');
            }
            $password = $data['contrasena'] ?? $data['password'];
            $rol = $data['rol'] ?? 'cliente';
            if (trim((string) $data['nombre']) === '' || strlen((string) $data['nombre']) > 100
                || !filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen((string) $correo) > 150
                || strlen($password) < 6 || !in_array($rol, ['administrador', 'cliente'], true)) {
                throw new InvalidArgumentException('Nombre, correo válido y contraseña son obligatorios.');
            }
            $telefono = $data['telefono'] ?? null;
            if ($telefono !== null && strlen((string) $telefono) > 25) {
                throw new InvalidArgumentException('El teléfono no puede superar 25 caracteres.');
            }
            $created = Usuario::crear(trim($data['nombre']), trim($correo), $password, $rol, $telefono);
            return ['success' => true, 'data' => $created];
        }, 201);
    }

    public function updateUsuario()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['id_usuario', 'nombre']);
            $admin = AuthMiddleware::esAdmin();
            $idUsuario = $this->positiveInteger($data['id_usuario'], 'id_usuario');
            $correo = $data['correo'] ?? $data['email'] ?? null;
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen((string) $correo) > 150
                || trim((string) $data['nombre']) === '' || strlen((string) $data['nombre']) > 100) {
                throw new InvalidArgumentException('Se requiere un correo válido.');
            }
            $rol = $data['rol'] ?? null;
            if ($rol !== null && !in_array($rol, ['administrador', 'cliente'], true)) {
                throw new InvalidArgumentException('El rol debe ser administrador o cliente.');
            }
            $telefono = $data['telefono'] ?? null;
            if ($telefono !== null && strlen((string) $telefono) > 25) {
                throw new InvalidArgumentException('El teléfono no puede superar 25 caracteres.');
            }
            $activo = array_key_exists('activo', $data) ? $this->activeFlag($data['activo']) : null;
            if ((int) $admin['id_usuario'] === $idUsuario
                && ($activo === 0 || $rol === 'cliente')) {
                throw new DomainException('No puedes desactivar o cambiar el rol de tu propia cuenta.');
            }
            return ['success' => true, 'affected' => Usuario::updateUsuario(
                $idUsuario,
                trim($data['nombre']),
                trim($correo),
                $telefono,
                $data['contrasena'] ?? $data['password'] ?? null,
                $rol,
                $activo
            )];
        });
    }

    public function deleteUsuario()
    {
        $this->run(function () {
            $data = $this->input();
            $id = $data['id_usuario'] ?? $_GET['id_usuario'] ?? null;
            $id = $this->positiveInteger($id, 'id_usuario');
            $admin = AuthMiddleware::esAdmin();
            if ((int) $admin['id_usuario'] === $id) {
                throw new DomainException('No puedes desactivar tu propia cuenta.');
            }
            return ['success' => true, 'affected' => Usuario::deleteUsuario($id)];
        });
    }

    private function activeFlag($value)
    {
        if (!in_array($value, [0, 1, '0', '1', true, false], true)) {
            throw new InvalidArgumentException('El campo activo debe ser booleano.');
        }
        return (int) (bool) $value;
    }
}
