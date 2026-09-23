<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Empleado.php';
require_once __DIR__ . '/../models/Evaluable.php';
require_once __DIR__ . '/../models/EmpleadoTiempoCompleto.php';
require_once __DIR__ . '/../models/EmpleadoPorHoras.php';
require_once __DIR__ . '/../models/Encargado.php';
require_once __DIR__ . '/../models/Nomina.php';

// ==========================================================
// CONTROLADOR
// Recibe la petición (POST/GET), habla con el Modelo
// (clases de empleado + base de datos) y prepara los datos
// que la Vista va a necesitar. No contiene HTML.
// ==========================================================
class EmpleadoController {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::obtenerConexion();
    }

    /**
     * Procesa la petición actual (agregar, eliminar, vaciar o evaluar)
     * y devuelve un array con todo lo que la vista necesita mostrar.
     */
    public function manejarPeticion(): array {
        $error = '';
        $mensajeEvaluacion = '';
        $empleadoEvaluado = '';

        $accion = $_POST['accion'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'agregar') {
            $error = $this->agregarEmpleado($_POST);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'eliminar') {
            $this->eliminarEmpleado((int) $_POST['idRegistro']);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'vaciar') {
            $this->vaciarNomina();
        }

        // Reconstruir el estado actual (Modelo) para mostrarlo en la Vista
        [$nomina, $filas, $idsRegistro] = $this->obtenerNominaActual();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'evaluar') {
            $idRegistro = (int) $_POST['idRegistro'];
            $resultado = $this->evaluarEmpleado($nomina, $idsRegistro, $idRegistro);
            $mensajeEvaluacion = $resultado['mensaje'];
            $empleadoEvaluado = $resultado['nombre'];
        }

        return [
            'error' => $error,
            'mensajeEvaluacion' => $mensajeEvaluacion,
            'empleadoEvaluado' => $empleadoEvaluado,
            'empleadosLista' => $nomina->getEmpleados(),
            'filas' => $filas,
            'idsRegistro' => $idsRegistro,
            'total' => $nomina->calcularTotalNomina(),
        ];
    }

    // ---------- Operaciones sobre el Modelo ----------

    private function agregarEmpleado(array $datosPost): string {
        try {
            $tipo = $datosPost['tipo'];
            $nombre = trim($datosPost['nombre']);
            $codigo = trim($datosPost['id']);
            $fecha = $datosPost['fecha'];

            if ($nombre === '' || $codigo === '') {
                throw new InvalidArgumentException("Nombre e ID son obligatorios.");
            }

            $salarioMensual = null;
            $bonoAnual = null;
            $horasTrabajadas = null;
            $tarifaPorHora = null;
            $bonoLiderazgo = null;

            if ($tipo === 'tiempoCompleto') {
                $salarioMensual = (float) $datosPost['salarioMensual'];
                $bonoAnual = (float) $datosPost['bonoAnual'];
                new EmpleadoTiempoCompleto($nombre, $codigo, $fecha, $salarioMensual, $bonoAnual); // valida

            } elseif ($tipo === 'porHoras') {
                $horasTrabajadas = (float) $datosPost['horasTrabajadas'];
                $tarifaPorHora = (float) $datosPost['tarifaPorHora'];
                new EmpleadoPorHoras($nombre, $codigo, $fecha, $horasTrabajadas, $tarifaPorHora); // valida

            } elseif ($tipo === 'encargado') {
                $salarioMensual = (float) $datosPost['salarioMensual'];
                $bonoAnual = (float) $datosPost['bonoAnual'];
                $bonoLiderazgo = (float) $datosPost['bonoLiderazgo'];
                new Encargado($nombre, $codigo, $fecha, $salarioMensual, $bonoAnual, $bonoLiderazgo); // valida

            } else {
                throw new InvalidArgumentException("Tipo de empleado no válido.");
            }

            $stmt = $this->pdo->prepare(
                "INSERT INTO empleados (tipo, nombre, codigo, fecha_contratacion, salario_mensual, bono_anual, horas_trabajadas, tarifa_por_hora, bono_liderazgo)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$tipo, $nombre, $codigo, $fecha, $salarioMensual, $bonoAnual, $horasTrabajadas, $tarifaPorHora, $bonoLiderazgo]);

            return '';

        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
    }

    private function eliminarEmpleado(int $idRegistro): void {
        $stmt = $this->pdo->prepare("DELETE FROM empleados WHERE id = ?");
        $stmt->execute([$idRegistro]);
    }

    private function vaciarNomina(): void {
        $this->pdo->exec("DELETE FROM empleados");
    }

    private function obtenerNominaActual(): array {
        $filas = $this->pdo->query("SELECT * FROM empleados ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

        $nomina = new Nomina();
        $idsRegistro = [];

        foreach ($filas as $fila) {
            switch ($fila['tipo']) {
                case 'tiempoCompleto':
                    $empleado = new EmpleadoTiempoCompleto($fila['nombre'], $fila['codigo'], $fila['fecha_contratacion'], (float) $fila['salario_mensual'], (float) $fila['bono_anual']);
                    break;
                case 'porHoras':
                    $empleado = new EmpleadoPorHoras($fila['nombre'], $fila['codigo'], $fila['fecha_contratacion'], (float) $fila['horas_trabajadas'], (float) $fila['tarifa_por_hora']);
                    break;
                case 'encargado':
                    $empleado = new Encargado($fila['nombre'], $fila['codigo'], $fila['fecha_contratacion'], (float) $fila['salario_mensual'], (float) $fila['bono_anual'], (float) $fila['bono_liderazgo']);
                    break;
                default:
                    continue 2;
            }
            $nomina->agregarEmpleado($empleado);
            $idsRegistro[] = $fila['id'];
        }

        return [$nomina, $filas, $idsRegistro];
    }

    private function evaluarEmpleado(Nomina $nomina, array $idsRegistro, int $idRegistro): array {
        $empleadosLista = $nomina->getEmpleados();

        foreach ($idsRegistro as $indice => $id) {
            if ($id === $idRegistro) {
                $empleado = $empleadosLista[$indice];
                if ($empleado instanceof Evaluable) {
                    return [
                        'mensaje' => $empleado->evaluarDesempeño(),
                        'nombre' => $empleado->getNombre(),
                    ];
                }
            }
        }

        return ['mensaje' => '', 'nombre' => ''];
    }
}
