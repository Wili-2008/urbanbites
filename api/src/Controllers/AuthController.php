<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../Models/Usuario.php';
require_once __DIR__ . '/../Config/config.php';

class AuthController extends ApiController
{
    public function setupStatus()
    {
        $this->run(function () {
            return ['setup_required' => Usuario::cantidadAdministradores() === 0];
        });
    }

    public function setupAdmin()
    {
        $this->run(function () {
            if (Usuario::cantidadAdministradores() > 0) {
                throw new AuthorizationException('La configuración inicial ya fue completada.');
            }
            $data = $this->input();
            $nombre = trim((string) ($data['nombre'] ?? ''));
            $correo = trim((string) ($data['correo'] ?? ''));
            $password = (string) ($data['contrasena'] ?? '');
            if ($nombre === '' || strlen($nombre) > 100 || !filter_var($correo, FILTER_VALIDATE_EMAIL)
                || strlen($correo) > 150 || strlen($password) < 8) {
                throw new InvalidArgumentException('Ingresa un nombre, correo válido y una contraseña de al menos 8 caracteres.');
            }
            if (Usuario::obtenerPorCorreo($correo)) {
                throw new DomainException('Ese correo ya pertenece a una cuenta.');
            }
            $created = Usuario::crear($nombre, $correo, $password, 'administrador');
            return [
                'success' => true,
                'token' => $this->generarToken([
                    'id' => $created['id_usuario'],
                    'id_usuario' => $created['id_usuario'],
                    'tipo' => 'usuario',
                    'rol' => 'administrador',
                    'correo' => $correo,
                ]),
                'user' => [
                    'id_usuario' => $created['id_usuario'],
                    'id' => $created['id_usuario'],
                    'nombre' => $nombre,
                    'correo' => $correo,
                    'email' => $correo,
                    'telefono' => null,
                    'rol' => 'administrador',
                    'tipo' => 'usuario',
                ],
            ];
        }, 201);
    }

    public function loginUsuario()
    {
        $this->authenticate(['administrador', 'vendedor']);
    }

    public function login()
    {
        $this->authenticate(['administrador', 'vendedor']);
    }

    private function authenticate(array $roles)
    {
        $this->run(function () use ($roles) {
            $data = $this->input();
            $identifier = trim((string) ($data['correo'] ?? $data['email'] ?? $data['username'] ?? ''));
            $password = (string) ($data['contrasena'] ?? $data['password'] ?? '');
            if ($identifier === '' || $password === '') {
                throw new InvalidArgumentException('Correo/usuario y contraseña son obligatorios.');
            }

            $user = Usuario::obtenerPorEmailOrNombre($identifier);
            if (!$user || !(int) $user['activo'] || !in_array($user['rol'], $roles, true)
                || !password_verify($password, $user['contrasena_hash'])) {
                throw new AuthenticationException('Usuario o contraseña incorrectos.');
            }

            $tipo = 'usuario';
            return [
                'success' => true,
                'message' => 'Sesión iniciada correctamente.',
                'token' => $this->generarToken([
                    'id' => (int) $user['id_usuario'],
                    'id_usuario' => (int) $user['id_usuario'],
                    'tipo' => $tipo,
                    'rol' => $user['rol'],
                    'id_rol' => (int) ($user['id_rol'] ?? 0),
                    'correo' => $user['correo'],
                ]),
                'user' => [
                    'id_usuario' => (int) $user['id_usuario'],
                    'id' => (int) $user['id_usuario'],
                    'id_rol' => (int) ($user['id_rol'] ?? 0),
                    'nombre' => $user['nombre'],
                    'correo' => $user['correo'],
                    'email' => $user['correo'],
                    'telefono' => $user['telefono'],
                    'rol' => $user['rol'],
                    'tipo' => $tipo,
                ],
            ];
        });
    }

    public function verifyToken()
    {
        $this->run(function () {
            $user = AuthMiddleware::autenticado();
            return [
                'success' => true,
                'user' => [
                    'id_usuario' => (int) $user['id_usuario'],
                    'id' => (int) $user['id_usuario'],
                    'id_rol' => (int) ($user['id_rol'] ?? 0),
                    'nombre' => $user['nombre'],
                    'correo' => $user['correo'],
                    'telefono' => $user['telefono'],
                    'rol' => $user['rol'],
                    'tipo' => 'usuario',
                ],
            ];
        });
    }

    public function logout()
    {
        $this->run(function () {
            return ['success' => true, 'message' => 'Sesión cerrada correctamente.'];
        });
    }

    private function generarToken(array $payload)
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $issued = time();
        $payload['iat'] = $issued;
        $payload['exp'] = $issued + (60 * 60 * 24); // 24 horas

        $base64Header = $this->base64UrlEncode(json_encode($header));
        $base64Payload = $this->base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", JWT_SECRET, true);
        $base64Signature = $this->base64UrlEncode($signature);

        return "{$base64Header}.{$base64Payload}.{$base64Signature}";
    }

    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
