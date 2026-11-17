<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/Producto.php';

class ProductoController extends ApiController
{
    public function listarProductos()
    {
        $this->run(function () {
            $includeUnavailable = ($_GET['incluir_no_disponibles'] ?? null) === '1';
            return Producto::obtenerTodos($includeUnavailable);
        });
    }

    public function agregarProducto()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['nombre', 'precio', 'id_categoria']);
            $name = trim((string) $data['nombre']);
            $price = $data['precio'];
            $image = $data['imagen'] ?? null;
            if ($name === '' || strlen($name) > 120 || !is_numeric($price)
                || (float) $price < 0 || (float) $price > 99999999.99
                || ($image !== null && strlen((string) $image) > 255)) {
                throw new InvalidArgumentException('Nombre y precio válido son obligatorios.');
            }
            $id = Producto::crear(
                $name,
                $data['descripcion'] ?? null,
                $price,
                $image,
                $this->positiveInteger($data['id_categoria'], 'id_categoria'),
                $this->activeFlag($data['disponible'] ?? 1)
            );
            return ['success' => true, 'id_producto' => $id];
        }, 201);
    }

    public function updateProducto()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['id_producto', 'nombre', 'precio', 'id_categoria']);
            $name = trim((string) $data['nombre']);
            $price = $data['precio'];
            $image = $data['imagen'] ?? null;
            if ($name === '' || strlen($name) > 120 || !is_numeric($price)
                || (float) $price < 0 || (float) $price > 99999999.99
                || ($image !== null && strlen((string) $image) > 255)) {
                throw new InvalidArgumentException('Nombre y precio válido son obligatorios.');
            }
            $id = $this->positiveInteger($data['id_producto'], 'id_producto');
            $oldImage = Producto::obtenerImagen($id);
            $affected = Producto::updateProducto(
                $id,
                $name,
                $data['descripcion'] ?? null,
                $price,
                $image,
                $this->positiveInteger($data['id_categoria'], 'id_categoria'),
                $this->activeFlag($data['disponible'] ?? 1)
            );
            if ($image !== $oldImage) {
                $this->eliminarImagenLocal($oldImage);
            }
            return ['success' => true, 'affected' => $affected];
        });
    }

    public function deleteProducto()
    {
        $this->run(function () {
            $data = $this->input();
            return ['success' => true, 'affected' => Producto::deleteProducto(
                $this->positiveInteger($data['id_producto'] ?? null, 'id_producto')
            )];
        });
    }

    public function subirImagen()
    {
        $this->run(function () {
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
                throw new InvalidArgumentException('La imagen supera el límite de 2 MB configurado en WAMP.');
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
                throw new RuntimeException('Apache no tiene permisos para guardar imágenes en backend/uploads/products.');
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
