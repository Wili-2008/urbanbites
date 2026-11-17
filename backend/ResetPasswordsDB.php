<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se puede ejecutar desde la consola.');
}

require_once __DIR__ . '/config/ConexionPDO.php';

$id = filter_var($argv[1] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    fwrite(STDERR, "Uso: php ResetPasswordsDB.php <id_usuario>\n");
    exit(1);
}

fwrite(STDOUT, 'Nueva contraseña: ');
$password = trim((string) fgets(STDIN));
if (strlen($password) < 6) {
    fwrite(STDERR, "La contraseña debe tener al menos 6 caracteres.\n");
    exit(1);
}

$statement = ConexionPDO::Conexion()->prepare(
    'UPDATE usuarios SET contrasena_hash = :contrasena_hash WHERE id_usuario = :id_usuario'
);
$statement->execute([
    'contrasena_hash' => password_hash($password, PASSWORD_DEFAULT),
    'id_usuario' => $id,
]);
if ($statement->rowCount() !== 1) {
    fwrite(STDERR, "No se encontró un usuario con ese id.\n");
    exit(1);
}
fwrite(STDOUT, "Contraseña actualizada.\n");
