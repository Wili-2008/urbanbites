<?php
$config = parse_ini_file(__DIR__ . '/../.env');
if ($config === false) {
    throw new RuntimeException('No se pudo cargar la configuración del backend.');
}

$required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_PORT', 'DB_CHARSET'];
foreach ($required as $key) {
    if (!array_key_exists($key, $config)) {
        throw new RuntimeException("Falta la variable {$key} en backend/.env.");
    }
}

define('HOST', $config['DB_HOST']);
define('DATABASE', $config['DB_NAME']);
define('USERNAME', $config['DB_USER']);
define('PASSWORD', $config['DB_PASSWORD']);
define('PORT', $config['DB_PORT']);
define('CHARSET', preg_replace('/^charset=/i', '', $config['DB_CHARSET']));
$jwtSecret = getenv('JWT_SECRET') ?: ($config['JWT_SECRET'] ?? '');
if (strlen($jwtSecret) < 32) {
    throw new RuntimeException('JWT_SECRET debe tener al menos 32 caracteres.');
}
define('JWT_SECRET', $jwtSecret);
