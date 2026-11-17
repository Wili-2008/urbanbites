<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo se puede ejecutar desde la consola.');
}

fwrite(STDOUT, "Contraseña para generar su hash: ");
$password = trim((string) fgets(STDIN));
if ($password === '') {
    fwrite(STDERR, "La contraseña no puede estar vacía.\n");
    exit(1);
}

fwrite(STDOUT, password_hash($password, PASSWORD_DEFAULT) . PHP_EOL);
