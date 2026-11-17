<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/Producto.php';
require_once __DIR__ . '/../config/AuthMiddleware.php';

class ProductoController extends ApiController
{
    public function listarProductos()
    {
        $this->run(function () {
            $includeUnavailable = ($_GET['incluir_no_disponibles'] ?? null) === '1';
            if ($includeUnavailable) {
                AuthMiddleware::esAdmin();
            }
            return Producto::obtenerTodos($includeUnavailable);
        });
    }

    public function agregarProducto()
    {
        $this->run(function () {
            $actor = AuthMiddleware::esAdmin();
            $data = $this->input();
            $this->requireFields($data, ['nombre', 'precio', 'id_categoria', 'codigo']);

            $codigo = strtoupper(trim((string) $data['codigo']));
            $name = trim((string) $data['nombre']);
            $price = $data['precio'];
            $stock = isset($data['stock']) ? (int) $data['stock'] : 0;
            $image = isset($data['imagen']) && trim((string) $data['imagen']) !== '' ? trim((string) $data['imagen']) : null;
            $idCategoria = $this->positiveInteger($data['id_categoria'], 'id_categoria');
            $disponible = $this->activeFlag($data['disponible'] ?? 1);

            if ($codigo === '' || strlen($codigo) > 50 || !preg_match('/^[A-Z0-9\-_]+$/', $codigo)) {
                throw new InvalidArgumentException('El código de producto es obligatorio y solo puede contener letras, números, guiones y guiones bajos (hasta 50 caracteres).');
            }
            if (Producto::existeCodigo($codigo)) {
                throw new DomainException("Ya existe un producto con el código '{$codigo}'.");
            }
            if ($name === '' || strlen($name) > 120) {
                throw new InvalidArgumentException('El nombre del producto es obligatorio y debe tener hasta 120 caracteres.');
            }
            if (!is_numeric($price) || (float) $price < 0 || (float) $price > 99999999.99) {
                throw new InvalidArgumentException('El precio debe ser un número válido mayor o igual a 0.');
            }
            if ($stock < 0) {
                throw new InvalidArgumentException('El stock inicial no puede ser negativo.');
            }
            if ($image !== null && strlen($image) > 255) {
                throw new InvalidArgumentException('La URL de imagen no debe superar 255 caracteres.');
            }

            $id = Producto::crear(
                $codigo,
                $name,
                $data['descripcion'] ?? null,
                $price,
                $stock,
                $image,
                $idCategoria,
                $disponible,
                $actor['id_usuario'],
                $actor['nombre']
            );

            return [
                'success' => true,
                'message' => 'Producto agregado exitosamente al catálogo.',
                'id_producto' => $id,
                'codigo' => $codigo,
            ];
        }, 201);
    }

    public function updateProducto()
    {
        $this->run(function () {
            $actor = AuthMiddleware::esAdmin();
            $data = $this->input();
            $this->requireFields($data, ['id_producto', 'nombre', 'precio', 'id_categoria', 'codigo']);

            $id = $this->positiveInteger($data['id_producto'], 'id_producto');
            $codigo = strtoupper(trim((string) $data['codigo']));
            $name = trim((string) $data['nombre']);
            $price = $data['precio'];
            $stock = isset($data['stock']) ? (int) $data['stock'] : 0;
            $image = isset($data['imagen']) && trim((string) $data['imagen']) !== '' ? trim((string) $data['imagen']) : null;
            $idCategoria = $this->positiveInteger($data['id_categoria'], 'id_categoria');
            $disponible = $this->activeFlag($data['disponible'] ?? 1);

            if ($codigo === '' || strlen($codigo) > 50 || !preg_match('/^[A-Z0-9\-_]+$/', $codigo)) {
                throw new InvalidArgumentException('El código de producto es obligatorio y debe ser alfanumérico.');
            }
            if (Producto::existeCodigo($codigo, $id)) {
                throw new DomainException("Ya existe otro producto con el código '{$codigo}'.");
            }
            if ($name === '' || strlen($name) > 120) {
                throw new InvalidArgumentException('El nombre del producto es obligatorio.');
            }
            if (!is_numeric($price) || (float) $price < 0 || (float) $price > 99999999.99) {
                throw new InvalidArgumentException('El precio debe ser un número válido mayor o igual a 0.');
            }
            if ($stock < 0) {
                throw new InvalidArgumentException('El stock no puede ser negativo.');
            }

            $oldImage = Producto::obtenerImagen($id);
            $affected = Producto::updateProducto(
                $id,
                $codigo,
                $name,
                $data['descripcion'] ?? null,
                $price,
                $stock,
                $image,
                $idCategoria,
                $disponible,
                $actor['id_usuario'],
                $actor['nombre']
            );

            if ($image !== $oldImage) {
                $this->eliminarImagenLocal($oldImage);
            }

            return [
                'success' => true,
                'message' => 'Producto actualizado exitosamente.',
                'affected' => $affected,
            ];
        });
    }

    public function ajustarStock()
    {
        $this->run(function () {
            $actor = AuthMiddleware::esAdmin();
            $data = $this->input();
            $this->requireFields($data, ['id_producto', 'cantidad']);

            $id = $this->positiveInteger($data['id_producto'], 'id_producto');
            $cantidad = filter_var($data['cantidad'], FILTER_VALIDATE_INT);
            if ($cantidad === false || $cantidad === 0) {
                throw new InvalidArgumentException('La cantidad de ajuste debe ser un entero distinto de cero (positivo para entrada, negativo para salida).');
            }

            $motivo = isset($data['motivo']) ? trim((string) $data['motivo']) : 'Ajuste manual de inventario';
            $resultado = Producto::ajustarStock($id, $cantidad, $motivo, $actor['id_usuario'], $actor['nombre']);

            return [
                'success' => true,
                'message' => 'Stock ajustado exitosamente.',
                'stock_anterior' => $resultado['stock_anterior'],
                'stock_nuevo' => $resultado['stock_nuevo'],
                'diferencia' => $cantidad,
            ];
        });
    }

    public function deleteProducto()
    {
        $this->run(function () {
            $actor = AuthMiddleware::esAdmin();
            $data = $this->input();
            $id = $this->positiveInteger($data['id_producto'] ?? null, 'id_producto');

            $affected = Producto::deleteProducto($id, $actor['id_usuario'], $actor['nombre']);
            return [
                'success' => true,
                'message' => 'Producto desactivado del menú.',
                'affected' => $affected,
            ];
        });
    }

    public function subirImagen()
    {
        $this->run(function () {
            AuthMiddleware::esAdmin();
            $data = $this->input();
            $id = $this->positiveInteger($data['id_producto'] ?? null, 'id_producto');
            $oldImage = Producto::obtenerImagen($id);
            if ($oldImage === null && !Producto::existe($id)) {
                throw new InvalidArgumentException('No se encontró el producto.');
            }
            $file = $_FILES['imagen_archivo'] ?? null;
            if (!$file) {
                throw new InvalidArgumentException('Selecciona una imagen válida para subir.');
            }
            $uploadError = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
                throw new InvalidArgumentException('La imagen supera el límite de 2 MB configurado.');
            }
            if ($uploadError !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('No se pudo recibir la imagen. Selecciona el archivo de nuevo.');
            }
            if (($file['size'] ?? 0) < 1 || $file['size'] > 2 * 1024 * 1024) {
                throw new InvalidArgumentException('La imagen no debe superar 2 MB.');
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($extensions[$mime]) || @getimagesize($file['tmp_name']) === false) {
                throw new InvalidArgumentException('Usa una imagen JPG, PNG o WebP válida.');
            }
            $directory = __DIR__ . '/../uploads/products';
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException('No se pudo crear la carpeta de imágenes.');
            }
            if (!is_writable($directory)) {
                throw new RuntimeException('No hay permisos para guardar imágenes en backend/uploads/products.');
            }
            $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
            $destination = $directory . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new RuntimeException('No se pudo guardar la imagen subida.');
            }
            $imageUrl = '/backend/uploads/products/' . $filename;
            try {
                if (Producto::actualizarImagen($id, $imageUrl) < 1) {
                    throw new RuntimeException('No se pudo asociar la imagen al producto.');
                }
            } catch (Throwable $exception) {
                @unlink($destination);
                throw $exception;
            }
            $this->eliminarImagenLocal($oldImage);
            return ['success' => true, 'imagen' => $imageUrl];
        });
    }

    public function quitarImagen()
    {
        $this->run(function () {
            AuthMiddleware::esAdmin();
            $data = $this->input();
            $id = $this->positiveInteger($data['id_producto'] ?? null, 'id_producto');
            if (!Producto::existe($id)) {
                throw new InvalidArgumentException('No se encontró el producto.');
            }
            $oldImage = Producto::obtenerImagen($id);
            Producto::actualizarImagen($id, null);
            $this->eliminarImagenLocal($oldImage);
            return ['success' => true, 'imagen' => null];
        });
    }

    private function eliminarImagenLocal($image)
    {
        $prefix = '/backend/uploads/products/';
        if (!is_string($image) || strpos($image, $prefix) !== 0) {
            return;
        }
        $filename = substr($image, strlen($prefix));
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $filename)) {
            return;
        }
        $path = __DIR__ . '/../uploads/products/' . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function activeFlag($value)
    {
        if (!in_array($value, [0, 1, '0', '1', true, false], true)) {
            throw new InvalidArgumentException('El campo disponible debe ser booleano.');
        }
        return (int) (bool) $value;
    }
}
