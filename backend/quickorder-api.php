<?php
require_once __DIR__ . '/config/ConexionPDO.php';
require_once __DIR__ . '/models/Pedido.php';
require_once __DIR__ . '/config/AuthMiddleware.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = ConexionPDO::Conexion();
    $action = $_GET['action'] ?? '';

    if ($action === 'get_products') {
        $category = isset($_GET['cat']) ? filter_var($_GET['cat'], FILTER_VALIDATE_INT) : 0;
        if ($category === false) {
            throw new InvalidArgumentException('cat debe ser un entero válido.');
        }
        $search = trim((string) ($_GET['busca'] ?? ''));
        $sql = "SELECT p.id_producto AS id, p.id_producto, p.id_categoria, p.nombre, p.descripcion,
                       p.precio, p.imagen, p.disponible, c.nombre AS categoria
                FROM productos p JOIN categorias c ON c.id_categoria = p.id_categoria
                WHERE p.disponible = 1 AND c.activa = 1";
        $params = [];
        if ($category > 0) {
            $sql .= " AND p.id_categoria = :category";
            $params['category'] = $category;
        }
        if ($search !== '') {
            $sql .= " AND (p.nombre LIKE :search_name OR p.descripcion LIKE :search_description)";
            $params['search_name'] = '%' . $search . '%';
            $params['search_description'] = '%' . $search . '%';
        }
        $sql .= " ORDER BY c.nombre, p.nombre";
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        echo json_encode($statement->fetchAll(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'get_categorias') {
        $statement = $pdo->query(
            "SELECT id_categoria AS id, id_categoria, nombre, descripcion
             FROM categorias WHERE activa = 1 ORDER BY nombre"
        );
        echo json_encode($statement->fetchAll(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'crear_pedido') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) {
            throw new InvalidArgumentException('El cuerpo debe contener un objeto JSON válido.');
        }
        $idUsuario = filter_var($body['id_usuario'] ?? null, FILTER_VALIDATE_INT);
        if ($idUsuario === false || $idUsuario < 1 || empty($body['items']) || !is_array($body['items'])) {
            throw new InvalidArgumentException('id_usuario e items son obligatorios.');
        }
        AuthMiddleware::puedeActuarComo($idUsuario);
        echo json_encode(
            ['success' => true] + Pedido::crearConItems($idUsuario, $body['items'], $body),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Acción no reconocida.']);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()]);
} catch (DomainException $exception) {
    http_response_code(409);
    echo json_encode(['error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor.']);
}
