<?php
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'http://urbanbites.com',
    'https://urbanbites.com',
    'http://localhost',
    'https://localhost',
    'http://127.0.0.1',
    'https://127.0.0.1',
];
if (
    $origin !== ''
    && (in_array($origin, $allowedOrigins, true)
        || preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin))
) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Vary: Origin');
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/Controllers/AuthController.php';
require_once __DIR__ . '/Controllers/UsuarioController.php';
require_once __DIR__ . '/Controllers/RolController.php';
require_once __DIR__ . '/Controllers/CategoriaController.php';
require_once __DIR__ . '/Controllers/ProductoController.php';
require_once __DIR__ . '/Controllers/VentaController.php';
require_once __DIR__ . '/Controllers/ReporteController.php';
require_once __DIR__ . '/Controllers/AuditoriaController.php';
require_once __DIR__ . '/Config/AuthMiddleware.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
foreach (['api/index.php/', 'index.php/', 'api/'] as $prefix) {
    if (strpos($uri, $prefix) === 0) {
        $uri = substr($uri, strlen($prefix));
    }
}
$uri = strtolower(trim($uri, '/'));

// Control estricto de rutas protegidas para Administrador
$adminGetRoutes = [
    'listarusuarios',
    'listar_usuarios',
    'reportes/ventas',
    'reporte_ventas',
    'auditoria/listar',
    'listar_auditoria',
];

$adminPostRoutes = [
    'agregar_usuario',
    'agregar_categoria',
    'agregar_producto',
    'ajustar_stock',
    'subir_imagen_producto',
    'quitar_imagen_producto',
];

// Rutas accesibles para Vendedor y Administrador (Punto de Venta)
$posGetRoutes = [
    'listarventas',
    'listar_ventas',
    'obtener_venta',
    'listarroles',
];

$posPostRoutes = [
    'agregar_venta',
];

if (
    ($method === 'GET' && in_array($uri, $adminGetRoutes, true))
    || ($method === 'GET' && (
        ($_GET['incluir_inactivas'] ?? null) === '1'
        || ($_GET['incluir_no_disponibles'] ?? null) === '1'
    ))
    || $method === 'PUT'
    || $method === 'DELETE'
    || ($method === 'POST' && in_array($uri, $adminPostRoutes, true))
) {
    AuthMiddleware::esAdmin();
} elseif (
    ($method === 'GET' && in_array($uri, $posGetRoutes, true))
    || ($method === 'POST' && in_array($uri, $posPostRoutes, true))
) {
    AuthMiddleware::esVendedorOAdmin();
}

if ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $raw = file_get_contents('php://input');
    if (stripos($contentType, 'multipart/form-data') !== 0 && $raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'El cuerpo debe contener un objeto JSON válido.']);
            exit;
        }
        $GLOBALS['_API_BODY'] = $decoded;
    }
}

$auth = new AuthController();
$usuario = new UsuarioController();
$rol = new RolController();
$categoria = new CategoriaController();
$producto = new ProductoController();
$venta = new VentaController();
$reporte = new ReporteController();
$auditoria = new AuditoriaController();

if ($method === 'GET') {
    switch ($uri) {
        case 'auth/verify':
            $auth->verifyToken();
            break;
        case 'auth/setup-status':
            $auth->setupStatus();
            break;
        case 'listarusuarios':
        case 'listar_usuarios':
            $usuario->listarUsuarios();
            break;
        case 'listarroles':
            $rol->listarRoles();
            break;
        case 'listarcategorias':
        case 'listar_categorias':
            $categoria->listarCategorias();
            break;
        case 'listarproductos':
        case 'listar_productos':
        case 'productos':
            $producto->listarProductos();
            break;
        case 'listarventas':
        case 'listar_ventas':
            $venta->listarVentas();
            break;
        case 'obtener_venta':
            $venta->obtenerVenta();
            break;
        case 'reportes/ventas':
        case 'reporte_ventas':
            $reporte->reporteVentas();
            break;
        case 'auditoria/listar':
        case 'listar_auditoria':
            $auditoria->listarAuditoria();
            break;
        default:
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Ruta GET no encontrada.']);
    }
    exit;
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? ($GLOBALS['_API_BODY']['action'] ?? '');
    if ($action === 'login') {
        $auth->login();
        exit;
    }
    $routes = [
        'auth/login' => [$auth, 'login'],
        'auth/login-usuario' => [$auth, 'loginUsuario'],
        'auth/setup-admin' => [$auth, 'setupAdmin'],
        'auth/logout' => [$auth, 'logout'],
        'agregar_usuario' => [$usuario, 'agregarUsuario'],
        'agregar_categoria' => [$categoria, 'agregarCategoria'],
        'agregar_producto' => [$producto, 'agregarProducto'],
        'ajustar_stock' => [$producto, 'ajustarStock'],
        'subir_imagen_producto' => [$producto, 'subirImagen'],
        'quitar_imagen_producto' => [$producto, 'quitarImagen'],
        'agregar_venta' => [$venta, 'agregarVenta'],
    ];
    if (isset($routes[$uri])) {
        call_user_func($routes[$uri]);
        exit;
    }
} elseif ($method === 'PUT') {
    $routes = [
        'update_usuario' => [$usuario, 'updateUsuario'],
        'update_categoria' => [$categoria, 'updateCategoria'],
        'update_producto' => [$producto, 'updateProducto'],
    ];
    if (isset($routes[$uri])) {
        call_user_func($routes[$uri]);
        exit;
    }
} elseif ($method === 'DELETE') {
    $routes = [
        'delete_usuario' => [$usuario, 'deleteUsuario'],
        'delete_categoria' => [$categoria, 'deleteCategoria'],
        'delete_producto' => [$producto, 'deleteProducto'],
    ];
    if (isset($routes[$uri])) {
        call_user_func($routes[$uri]);
        exit;
    }
} else {
    http_response_code(405);
    header('Allow: GET, POST, PUT, DELETE, OPTIONS');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}
