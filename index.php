<?php
// ==========================================================
// PUNTO DE ENTRADA (front controller)
// Este archivo es muy corto a propósito: solo crea el
// Controlador, le pide que procese la petición, y le pasa
// el resultado a la Vista. No tiene lógica propia.
// ==========================================================

require_once __DIR__ . '/controllers/EmpleadoController.php';

$controlador = new EmpleadoController();
$datos = $controlador->manejarPeticion();

// Convertimos el array en variables individuales para que
// la vista las use directo ($error, $empleadosLista, etc.)
extract($datos);

require_once __DIR__ . '/views/nomina_view.php';
