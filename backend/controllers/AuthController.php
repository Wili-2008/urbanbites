<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Cliente.php';
require_once __DIR__ . '/../config/config.php';

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
                    'id' => $created['id_usuario'], 'id_usuario' => $created['id_usuario'],
                    'tipo' => 'usuario', 'rol' => 'administrador', 'correo' => $correo,
                ]),
                'user' => [
                    'id_usuario' => $created['id_usuario'], 'id' => $created['id_usuario'],
                    'nombre' => $nombre, 'correo' => $correo, 'email' => $correo,
                    'telefono' => null, 'rol' => 'administrador', 'tipo' => 'usuario',
                ],
            ];
        }, 201);
    }

    public function loginUsuario()
    {
        $this->authenticate(['administrador']);
    }

    public function loginCliente()
    {
        $this->authenticate(['cliente']);
    }

    public function login()
    {
        $this->authenticate(['administrador', 'cliente']);
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

            $tipo = $user['rol'] === 'cliente' ? 'cliente' : 'usuario';
            return [
                'success' => true,
                'message' => 'Sesión iniciada correctamente.',
                'token' => $this->generarToken([
                    'id' => (int) $user['id_usuario'],
                    'id_usuario' => (int) $user['id_usuario'],
                    'tipo' => $tipo,
                    'rol' => $user['rol'],
                    'correo' => $user['correo'],
                ]),
                'user' => [
                    'id_usuario' => (int) $user['id_usuario'],
                    'id' => (int) $user['id_usuario'],
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

    public function registrarCliente()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['nombre']);
            $correo = trim((string) ($data['correo'] ?? $data['email'] ?? ''));
            $password = (string) ($data['contrasena'] ?? $data['password'] ?? '');
            $nombre = trim((string) $data['nombre']);
            $telefono = $data['telefono'] ?? null;
            if ($nombre === '' || strlen($nombre) > 100 || !filter_var($correo, FILTER_VALIDATE_EMAIL)
                || strlen($correo) > 150 || strlen($password) < 6
                || ($telefono !== null && strlen((string) $telefono) > 25)) {
                throw new InvalidArgumentException('Nombre, correo válido y contraseña de al menos 6 caracteres son obligatorios.');
            }
            if (Cliente::obtenerPorEmail($correo)) {
                throw new DomainException('El correo ya está registrado.');
            }

            $created = Usuario::crear($nombre, $correo, $password, 'cliente', $telefono);
            return [
                'success' => true,
                'message' => 'Cliente registrado correctamente.',
                'token' => $this->generarToken([
                    'id' => $created['id_usuario'],
                    'id_usuario' => $created['id_usuario'],
                    'tipo' => 'cliente',
                    'rol' => 'cliente',
                    'correo' => $correo,
                ]),
                'user' => [
                    'id_usuario' => $created['id_usuario'],
                    'id' => $created['id_usuario'],
                    'nombre' => $nombre,
                    'correo' => $correo,
                    'email' => $correo,
                    'telefono' => $telefono,
                    'tipo' => 'cliente',
                    'rol' => 'cliente',
                ],
            ];
        }, 201);
    }

    public function logout()
    {
        $this->respond(['success' => true, 'message' => 'Sesión cerrada correctamente.']);
    }

    public function verifyToken()
    {
        try {
            $payload = AuthMiddleware::autenticado();
        } catch (AuthenticationException $exception) {
            $this->respond(['authenticated' => false], 401);
            return;
        }
        $this->respond(['authenticated' => true, 'user' => $payload]);
    }

    private function generarToken(array $payload)
    {
        $header = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = self::base64UrlEncode(json_encode($payload + ['iat' => time(), 'exp' => time() + 86400]));
        $signature = self::base64UrlEncode(hash_hmac('sha256', $header . '.' . $body, JWT_SECRET, true));
        return $header . '.' . $body . '.' . $signature;
    }

    private static function base64UrlEncode($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
