<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/Configuracion.php';

class ConfiguracionController extends ApiController
{
    public function obtenerConfiguracion()
    {
        $this->run(function () {
            $configuracion = Configuracion::obtener();
            if (!$configuracion) {
                throw new DomainException('No se encontró la configuración del negocio.');
            }
            return $configuracion;
        });
    }

    public function actualizarConfiguracion()
    {
        $this->run(function () {
            $data = $this->input();
            $this->requireFields($data, ['nombre_negocio', 'costo_envio']);
            $nombre = trim((string) $data['nombre_negocio']);
            $costo = $data['costo_envio'];
            if ($nombre === '' || strlen($nombre) > 100 || !is_numeric($costo)
                || (float) $costo < 0 || (float) $costo > 99999999.99) {
                throw new InvalidArgumentException('Se requiere un nombre de negocio y costo de envío válido.');
            }
            return ['success' => true, 'affected' => Configuracion::actualizar($nombre, $costo)];
        });
    }
}
