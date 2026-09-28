<?php
// ==========================================================
// Punto de entrada: exportar la nómina como PDF
// ==========================================================

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/controllers/ReporteController.php';

$reporteController = new ReporteController();
$reporteController->generarPdf();
