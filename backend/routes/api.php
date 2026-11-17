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

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/UsuarioController.php';
require_once __DIR__ . '/../controllers/ClienteController.php';
require_once __DIR__ . '/../controllers/CategoriaController.php';
require_once __DIR__ . '/../controllers/ProductoController.php';
require_once __DIR__ . '/../controllers/PedidoController.php';
require_once __DIR__ . '/../controllers/DetallePedidoController.php';
require_once __DIR__ . '/../controllers/ConfiguracionController.php';
require_once __DIR__ . '/../config/AuthMiddleware.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
foreach (['backend/', 'routes/api.php/', 'api.php/', 'api/'] as $prefix) {
    if (strpos($uri, $prefix) === 0) {
        $uri = substr($uri, strlen($prefix));
    }
}
$uri = strtolower(trim($uri, '/'));

$adminGetRoutes = ['listarusuarios', 'listar_usuarios', 'listarclientes', 'listar_clientes'];
$adminPostRoutes = [
    'agregar_usuario',
    'agregar_cliente',
    'agregar_categoria',
    'agregar_producto',
    'agregar_detallepedido',
    'update_configuracion',
    'subir_imagen_producto',
    'quitar_imagen_producto',
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
} elseif ($method === 'GET' && in_array($uri, ['listarpedidos', 'listar_pedidos', 'listardetallepedidos', 'listar_detallepedidos'], true)) {
    AuthMiddleware::autenticado();
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
$cliente = new ClienteController();
$categoria = new CategoriaController();
$producto = new ProductoController();
$pedido = new PedidoController();
$detalle = new DetallePedidoController();
$configuracion = new ConfiguracionController();

if ($method === 'GET') {
    switch ($uri) {
        case 'auth/verify':
            $auth->verifyToken();
            break;
        case 'auth/setup-status':
            $auth->setupStatus();
            break;
        case 'configuracion':
        case 'obtener_configuracion':
            $configuracion->obtenerConfiguracion();
            break;
        case 'listarusuarios':
        case 'listar_usuarios':
            $usuario->listarUsuarios();
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
        case 'listarclientes':
        case 'listar_clientes':
            $cliente->listarClientes();
            break;
        case 'listarpedidos':
        case 'listar_pedidos':
            $pedido->listarPedidos();
            break;
        case 'listardetallepedidos':
        case 'listar_detallepedidos':
            $detalle->listarDetallesPedidos();
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
        'auth/login-usuario' => [$auth, 'loginUsuario'],
        'auth/login-cliente' => [$auth, 'loginCliente'],
        'auth/registrar-cliente' => [$auth, 'registrarCliente'],
        'auth/setup-admin' => [$auth, 'setupAdmin'],
        'auth/logout' => [$auth, 'logout'],
        'agregar_usuario' => [$usuario, 'agregarUsuario'],
        'agregar_cliente' => [$cliente, 'agregarCliente'],
        'agregar_categoria' => [$categoria, 'agregarCategoria'],
        'agregar_producto' => [$producto, 'agregarProducto'],
        'subir_imagen_producto' => [$producto, 'subirImagen'],
        'quitar_imagen_producto' => [$producto, 'quitarImagen'],
        'agregar_pedido' => [$pedido, 'agregarPedido'],
        'agregar_detallepedido' => [$detalle, 'agregarDetallePedido'],
        'update_configuracion' => [$configuracion, 'actualizarConfiguracion'],
    ];
    if (isset($routes[$uri])) {
        call_user_func($routes[$uri]);
        exit;
    }
} elseif ($method === 'PUT') {
    $routes = [
        'update_usuario' => [$usuario, 'updateUsuario'],
        'update_cliente' => [$cliente, 'updateCliente'],
        'update_categoria' => [$categoria, 'updateCategoria'],
        'update_producto' => [$producto, 'updateProducto'],
        'update_pedido' => [$pedido, 'updatePedido'],
        'update_detallepedido' => [$detalle, 'updateDetallePedido'],
        'update_configuracion' => [$configuracion, 'actualizarConfiguracion'],
    ];
    if (isset($routes[$uri])) {
        call_user_func($routes[$uri]);
        exit;
    }
} elseif ($method === 'DELETE') {
    $routes = [
        'delete_usuario' => [$usuario, 'deleteUsuario'],
        'delete_cliente' => [$cliente, 'deleteCliente'],
        'delete_categoria' => [$categoria, 'deleteCategoria'],
        'delete_producto' => [$producto, 'deleteProducto'],
        'delete_pedido' => [$pedido, 'deletePedido'],
        'delete_detallepedido' => [$detalle, 'deleteDetallePedido'],
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

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error' => 'Ruta no encontrada.']);
