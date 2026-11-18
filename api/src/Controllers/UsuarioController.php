<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../Models/Usuario.php';
require_once __DIR__ . '/../Models/Rol.php';
require_once __DIR__ . '/../Config/AuthMiddleware.php';

class UsuarioController extends ApiController
{
    public function listarUsuarios()
    {
        $this->run(function () {
            AuthMiddleware::esAdmin();
            return Usuario::obtenerTodos();
        });
    }

    public function agregarUsuario()
    {
        $this->run(function () {
            $actor = AuthMiddleware::esAdmin();
            $data = $this->input();
            $this->requireFields($data, ['nombre', 'correo', 'contrasena']);

            $name = trim((string) $data['nombre']);
            $email = trim((string) $data['correo']);
            $password = (string) $data['contrasena'];
            $role = trim((string) ($data['rol'] ?? 'vendedor'));
            $phone = isset($data['telefono']) ? trim((string) $data['telefono']) : null;

            if ($name === '' || strlen($name) > 100) {
                throw new InvalidArgumentException('El nombre es obligatorio y debe tener como máximo 100 caracteres.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
                throw new InvalidArgumentException('El correo electrónico no es válido.');
            }
            if (strlen($password) < 6) {
                throw new InvalidArgumentException('La contraseña debe tener al menos 6 caracteres.');
            }
            if (!in_array($role, ['administrador', 'vendedor'], true)) {
                throw new InvalidArgumentException('El rol debe ser administrador o vendedor.');
            }
            if ($phone !== null && strlen($phone) > 25) {
                throw new InvalidArgumentException('El teléfono no debe superar 25 caracteres.');
            }
            if (Usuario::obtenerPorCorreo($email)) {
                throw new DomainException('Ya existe una cuenta registrada con este correo electrónico.');
            }

            $created = Usuario::crear(
                $name,
                $email,
                $password,
                $role,
                $phone,
                $actor['id_usuario'],
                $actor['nombre']
            );

            return [
                'success' => true,
                'message' => 'Usuario creado exitosamente.',
                'id_usuario' => $created['id_usuario'],
            ];
        }, 201);
    }

    public function updateUsuario()
    {
        $this->run(function () {
            $actor = AuthMiddleware::esAdmin();
            $data = $this->input();
            $this->requireFields($data, ['id_usuario', 'nombre', 'correo']);

            $id = $this->positiveInteger($data['id_usuario'], 'id_usuario');
            $name = trim((string) $data['nombre']);
            $email = trim((string) $data['correo']);
            $password = isset($data['contrasena']) && $data['contrasena'] !== '' ? (string) $data['contrasena'] : null;
            $role = isset($data['rol']) ? trim((string) $data['rol']) : null;
            $phone = isset($data['telefono']) ? trim((string) $data['telefono']) : null;
            $active = isset($data['activo']) ? (int) $data['activo'] : null;

            if ($name === '' || strlen($name) > 100) {
                throw new InvalidArgumentException('El nombre es obligatorio y debe tener como máximo 100 caracteres.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
                throw new InvalidArgumentException('El correo electrónico no es válido.');
            }
            if ($password !== null && strlen($password) < 6) {
                throw new InvalidArgumentException('La contraseña debe tener al menos 6 caracteres.');
            }
            if ($role !== null && !in_array($role, ['administrador', 'vendedor'], true)) {
                throw new InvalidArgumentException('El rol debe ser administrador o vendedor.');
            }

            $existing = Usuario::obtenerPorCorreo($email);
            if ($existing && (int) $existing['id_usuario'] !== $id) {
                throw new DomainException('Ya existe otra cuenta registrada con este correo electrónico.');
            }

            $affected = Usuario::updateUsuario(
                $id,
                $name,
                $email,
                $phone,
                $password,
                $role,
                $active,
                $actor['id_usuario'],
                $actor['nombre']
            );

            return [
                'success' => true,
                'message' => 'Usuario actualizado exitosamente.',
                'affected' => $affected,
            ];
        });
    }

    public function deleteUsuario()
    {
        $this->run(function () {
            $actor = AuthMiddleware::esAdmin();
            $data = $this->input();
            $id = $this->positiveInteger($data['id_usuario'] ?? null, 'id_usuario');

            if ($id === (int) $actor['id_usuario']) {
                throw new DomainException('No puedes desactivar tu propia cuenta administradora.');
            }

            $affected = Usuario::deleteUsuario($id, $actor['id_usuario'], $actor['nombre']);
            return [
                'success' => true,
                'message' => 'Usuario desactivado correctamente.',
                'affected' => $affected,
            ];
        });
    }
}
