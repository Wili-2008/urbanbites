<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/Categoria.php';

class CategoriaController extends ApiController
{
    public function listarCategorias()
    {
        $this->run(function () {
            $includeInactive = ($_GET['incluir_inactivas'] ?? null) === '1';
            return Categoria::obtenerTodos($includeInactive);
        });
    }

    public function agregarCategoria()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['nombre']);
            $nombre = trim((string) $data['nombre']);
            if ($nombre === '' || strlen($nombre) > 80) {
                throw new InvalidArgumentException('El nombre es obligatorio y no puede superar 80 caracteres.');
            }
            $descripcion = $data['descripcion'] ?? null;
            if ($descripcion !== null && strlen((string) $descripcion) > 255) {
                throw new InvalidArgumentException('La descripción no puede superar 255 caracteres.');
            }
            return ['success' => true, 'id_categoria' => Categoria::crear($nombre, $descripcion)];
        }, 201);
    }

    public function updateCategoria()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['id_categoria', 'nombre']);
            $nombre = trim((string) $data['nombre']);
            if ($nombre === '' || strlen($nombre) > 80) {
                throw new InvalidArgumentException('El nombre es obligatorio y no puede superar 80 caracteres.');
            }
            $descripcion = $data['descripcion'] ?? null;
            if ($descripcion !== null && strlen((string) $descripcion) > 255) {
                throw new InvalidArgumentException('La descripción no puede superar 255 caracteres.');
            }
            $activa = array_key_exists('activa', $data) ? $this->activeFlag($data['activa']) : null;
            return ['success' => true, 'affected' => Categoria::updateCategoria(
                $this->positiveInteger($data['id_categoria'], 'id_categoria'),
                $nombre,
                $descripcion,
                $activa
            )];
        });
    }

    public function deleteCategoria()
    {
        $this->run(function () {
            $data = $this->input();
            return ['success' => true, 'affected' => Categoria::deleteCategoria(
                $this->positiveInteger($data['id_categoria'] ?? null, 'id_categoria')
            )];
        });
    }

    private function activeFlag($value)
    {
        if (!in_array($value, [0, 1, '0', '1', true, false], true)) {
            throw new InvalidArgumentException('El campo activa debe ser booleano.');
        }
        return (int) (bool) $value;
    }
}
