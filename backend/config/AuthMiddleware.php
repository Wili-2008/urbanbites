<?php
require_once __DIR__ . '/config.php';

class AuthToken
{
    public static function fromRequest()
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authorization = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return null;
        }
        return self::verify($matches[1]);
    }

    public static function verify($token)
    {
        $parts = explode('.', (string) $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $payload, $signature] = $parts;
        $decodedHeader = json_decode(self::base64UrlDecode($header), true);
        $decodedPayload = json_decode(self::base64UrlDecode($payload), true);
        if (!is_array($decodedHeader) || ($decodedHeader['alg'] ?? null) !== 'HS256' || !is_array($decodedPayload)) {
            return null;
        }
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $header . '.' . $payload, JWT_SECRET, true)), '+/', '-_'), '=');
        if (!hash_equals($expected, $signature) || !isset($decodedPayload['exp']) || $decodedPayload['exp'] < time()) {
            return null;
        }
        return $decodedPayload;
    }

    private static function base64UrlDecode($value)
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        return $decoded === false ? '' : $decoded;
    }
}

class AuthMiddleware
{
    public static function verificarAutenticacion()
    {
        return AuthToken::fromRequest();
    }

    public static function autenticado()
    {
        $user = AuthToken::fromRequest();
        if (!$user) {
            self::deny(401, 'Debe iniciar sesión.');
        }
        require_once __DIR__ . '/../models/Usuario.php';
        $id = filter_var($user['id_usuario'] ?? null, FILTER_VALIDATE_INT);
        $current = $id ? Usuario::obtenerPorId($id) : null;
        if (!$current || !(int) $current['activo'] || $current['rol'] !== ($user['rol'] ?? null)) {
            self::deny(401, 'La sesión expiró o la cuenta está desactivada.');
        }
        $user['telefono'] = $current['telefono'];
        $user['nombre'] = $current['nombre'];
        return $user;
    }

    public static function esUsuario()
    {
        $user = self::autenticado();
        if (($user['tipo'] ?? null) !== 'usuario') {
            self::deny(403, 'Acceso denegado.');
        }
        return $user;
    }

    public static function esCliente()
    {
        $user = self::autenticado();
        if (($user['rol'] ?? null) !== 'cliente') {
            self::deny(403, 'Acceso denegado.');
        }
        return $user;
    }

    public static function esAdmin()
    {
        $user = self::autenticado();
        if (($user['rol'] ?? null) !== 'administrador') {
            self::deny(403, 'Acceso denegado. Se requiere el rol de administrador.');
        }
        return $user;
    }

    public static function puedeActuarComo($idUsuario)
    {
        $user = self::autenticado();
        if (($user['rol'] ?? null) !== 'administrador'
            && (int) ($user['id_usuario'] ?? 0) !== (int) $idUsuario) {
            self::deny(403, 'No tienes permiso para acceder a estos datos.');
        }
        return $user;
    }

    private static function deny($status, $message)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message]);
        exit;
    }
}
