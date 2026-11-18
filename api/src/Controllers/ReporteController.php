<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../Models/Reporte.php';
require_once __DIR__ . '/../Config/AuthMiddleware.php';

class ReporteController extends ApiController
{
    public function reporteVentas()
    {
        $this->run(function () {
            AuthMiddleware::esAdmin();

            $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
            $fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');

            return Reporte::obtenerReporteVentas($fechaInicio, $fechaFin);
        });
    }
}
