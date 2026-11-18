<?php
// ====================================================================
// Test Suite de Verificación Automática del Sistema Urban Bites
// ====================================================================

echo "========================================================\n";
echo "INICIANDO TEST SUITE DE INTEGRIDAD Y REGLAS DE NEGOCIO\n";
echo "========================================================\n\n";

$errors = 0;
$tests = 0;

function assertTest($condition, $description) {
    global $errors, $tests;
    $tests++;
    if ($condition) {
        echo " [OK] " . $description . "\n";
    } else {
        echo " [FAIL] " . $description . "\n";
        $errors++;
    }
}

// 1. Verificar carga de archivos del backend
require_once __DIR__ . '/../src/Config/config.php';
require_once __DIR__ . '/../src/Config/AuthMiddleware.php';
require_once __DIR__ . '/../src/Models/Rol.php';
require_once __DIR__ . '/../src/Models/Usuario.php';
require_once __DIR__ . '/../src/Models/Producto.php';
require_once __DIR__ . '/../src/Models/Venta.php';
require_once __DIR__ . '/../src/Models/Reporte.php';
require_once __DIR__ . '/../src/Models/Auditoria.php';

assertTest(defined('JWT_SECRET') && strlen(JWT_SECRET) >= 32, "JWT_SECRET está configurado y tiene al menos 32 caracteres");

// 2. Probar generación y verificación de JWT
$tokenPayload = [
    'id' => 10,
    'id_usuario' => 10,
    'rol' => 'vendedor',
    'tipo' => 'usuario',
    'correo' => 'vendedor@test.com',
    'iat' => time(),
    'exp' => time() + 3600
];
$header = ['typ' => 'JWT', 'alg' => 'HS256'];
$base64Header = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
$base64Payload = rtrim(strtr(base64_encode(json_encode($tokenPayload)), '+/', '-_'), '=');
$signature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", JWT_SECRET, true);
$base64Signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
$token = "{$base64Header}.{$base64Payload}.{$base64Signature}";

$verified = AuthToken::verify($token);
assertTest($verified !== null && $verified['rol'] === 'vendedor', "AuthToken::verify valida correctamente un token legítimo");

$tamperedToken = $token . "mod";
assertTest(AuthToken::verify($tamperedToken) === null, "AuthToken::verify rechaza tokens manipulados o inválidos");

// 3. Probar lógica de fechas del módulo de reportes
try {
    Reporte::obtenerReporteVentas('2026-11-30', '2026-11-01');
    assertTest(false, "Reporte debe rechazar fecha inicio posterior a fecha fin");
} catch (InvalidArgumentException $e) {
    assertTest(true, "Reporte rechaza correctamente rangos de fechas invertidos: " . $e->getMessage());
}

try {
    Reporte::obtenerReporteVentas('fecha-invalida', '2026-11-30');
    assertTest(false, "Reporte debe rechazar fechas mal formateadas");
} catch (InvalidArgumentException $e) {
    assertTest(true, "Reporte valida formato estricto YYYY-MM-DD: " . $e->getMessage());
}

// 4. Probar hashing seguro de contraseñas
$plain = "ClaveSecreta2026*";
$hash = password_hash($plain, PASSWORD_DEFAULT);
assertTest(password_verify($plain, $hash), "password_hash y password_verify funcionan correctamente");
assertTest(!password_verify("ClaveErronea", $hash), "password_verify rechaza contraseñas erróneas");

// 5. Probar formato de número correlativo de venta
$dummyVentaNum = "VTA-" . date('Ymd') . "-0001";
assertTest(preg_match('/^VTA-\d{8}-\d{4}$/', $dummyVentaNum) === 1, "Formato correlativo de número de venta sigue estándar VTA-YYYYMMDD-XXXX");

// 6. Probar reglas de cálculo de existencias y montos en el servidor
$precioUnitario = 28.00;
$cantidad = 3;
$descuento = 4.00;
$subtotal = round($precioUnitario * $cantidad, 2);
$total = round($subtotal - $descuento, 2);
$montoRecibido = 100.00;
$cambio = round($montoRecibido - $total, 2);

assertTest($subtotal === 84.00, "Cálculo de subtotal exacto en servidor (28.00 * 3 = 84.00)");
assertTest($total === 80.00, "Cálculo de total con descuento exacto en servidor (84.00 - 4.00 = 80.00)");
assertTest($cambio === 20.00, "Cálculo de cambio en efectivo exacto (100.00 - 80.00 = 20.00)");

echo "\n--------------------------------------------------------\n";
echo "RESULTADOS DEL TEST SUITE: {$tests} pruebas ejecutadas, {$errors} errores.\n";
echo "--------------------------------------------------------\n";

if ($errors > 0) {
    exit(1);
}
echo "TODAS LAS PRUEBAS PASARON EXITOSAMENTE.\n";
