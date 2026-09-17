<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Empleado.php';
require_once __DIR__ . '/Evaluable.php';
require_once __DIR__ . '/EmpleadoTiempoCompleto.php';
require_once __DIR__ . '/EmpleadoPorHoras.php';
require_once __DIR__ . '/Encargado.php';
require_once __DIR__ . '/Nomina.php';

$pdo = Database::obtenerConexion();
$error = '';

// ---------- Agregar empleado ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'agregar') {
    try {
        $tipo = $_POST['tipo'];
        $nombre = trim($_POST['nombre']);
        $codigo = trim($_POST['id']);
        $fecha = $_POST['fecha'];

        if ($nombre === '' || $codigo === '') {
            throw new InvalidArgumentException("Nombre e ID son obligatorios.");
        }

        $salarioMensual = null;
        $bonoAnual = null;
        $horasTrabajadas = null;
        $tarifaPorHora = null;
        $bonoLiderazgo = null;

        if ($tipo === 'tiempoCompleto') {
            $salarioMensual = (float) $_POST['salarioMensual'];
            $bonoAnual = (float) $_POST['bonoAnual'];
            new EmpleadoTiempoCompleto($nombre, $codigo, $fecha, $salarioMensual, $bonoAnual); // valida

        } elseif ($tipo === 'porHoras') {
            $horasTrabajadas = (float) $_POST['horasTrabajadas'];
            $tarifaPorHora = (float) $_POST['tarifaPorHora'];
            new EmpleadoPorHoras($nombre, $codigo, $fecha, $horasTrabajadas, $tarifaPorHora); // valida

        } elseif ($tipo === 'encargado') {
            $salarioMensual = (float) $_POST['salarioMensual'];
            $bonoAnual = (float) $_POST['bonoAnual'];
            $bonoLiderazgo = (float) $_POST['bonoLiderazgo'];
            new Encargado($nombre, $codigo, $fecha, $salarioMensual, $bonoAnual, $bonoLiderazgo); // valida

        } else {
            throw new InvalidArgumentException("Tipo de empleado no válido.");
        }

        $stmt = $pdo->prepare(
            "INSERT INTO empleados (tipo, nombre, codigo, fecha_contratacion, salario_mensual, bono_anual, horas_trabajadas, tarifa_por_hora, bono_liderazgo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$tipo, $nombre, $codigo, $fecha, $salarioMensual, $bonoAnual, $horasTrabajadas, $tarifaPorHora, $bonoLiderazgo]);

    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}

// ---------- Eliminar un empleado ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    $idRegistro = (int) $_POST['idRegistro'];
    $stmt = $pdo->prepare("DELETE FROM empleados WHERE id = ?");
    $stmt->execute([$idRegistro]);
}

// ---------- Vaciar toda la nómina ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'vaciar') {
    $pdo->exec("DELETE FROM empleados");
}

// ---------- Leer todos los registros de la base de datos ----------
$filas = $pdo->query("SELECT * FROM empleados ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// ---------- Reconstruir los objetos reales a partir de las filas ----------
$nomina = new Nomina();
$idsRegistro = [];
$encargadosConEquipo = [];

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
            $encargadosConEquipo[] = $empleado;
            break;
        default:
            continue 2;
    }
    $nomina->agregarEmpleado($empleado);
    $idsRegistro[] = $fila['id'];
}

$empleadosLista = $nomina->getEmpleados();
$total = $nomina->calcularTotalNomina();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Gestión de Nómina</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f2f4f7; margin: 0; padding: 20px; color: #222; }
        .contenedor { max-width: 900px; margin: 0 auto; }
        h1 { text-align: center; color: #2c3e50; }
        .tarjeta { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
        label { display: block; margin-top: 10px; font-weight: bold; font-size: 14px; }
        input, select { width: 100%; padding: 8px; margin-top: 4px; border: 1px solid #ccc; border-radius: 4px; }
        .campos-extra { display: none; border-left: 3px solid #3498db; padding-left: 12px; margin-top: 10px; }
        .campos-extra.activo { display: block; }
        button { margin-top: 16px; padding: 10px 18px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        button:hover { background: #2980b9; }
        button.eliminar { background: #e74c3c; padding: 4px 10px; font-size: 12px; }
        button.eliminar:hover { background: #c0392b; }
        button.vaciar { background: #7f8c8d; }
        button.vaciar:hover { background: #636e72; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #eee; font-size: 14px; }
        .total { text-align: right; font-size: 18px; font-weight: bold; margin-top: 10px; color: #27ae60; }
        .error { background: #fdecea; color: #c0392b; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .tipo-tag { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 12px; color: white; }
        .tag-tiempoCompleto { background: #3498db; }
        .tag-porHoras { background: #f39c12; }
        .tag-encargado { background: #8e44ad; }
    </style>
</head>
<body>
<div class="contenedor">
    <h1>Sistema de Gestión de Nómina</h1>

    <?php if ($error): ?>
        <div class="error">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="tarjeta">
        <h2>Agregar empleado</h2>
        <form method="POST" id="formEmpleado">
            <input type="hidden" name="accion" value="agregar">

            <label>Tipo de empleado</label>
            <select name="tipo" id="tipo" required>
                <option value="">-- Selecciona --</option>
                <option value="tiempoCompleto">Empleado de tiempo completo</option>
                <option value="porHoras">Empleado por horas</option>
                <option value="encargado">Encargado</option>
            </select>

            <label>Nombre completo</label>
            <input type="text" name="nombre" required>

            <label>ID / Código de empleado</label>
            <input type="text" name="id" required>

            <label>Fecha de contratación</label>
            <input type="date" name="fecha" required>

            <div class="campos-extra" id="campos-tiempoCompleto">
                <label>Salario mensual</label>
                <input type="number" step="0.01" name="salarioMensual" min="0">
                <label>Bono anual</label>
                <input type="number" step="0.01" name="bonoAnual" min="0" value="0">
            </div>

            <div class="campos-extra" id="campos-porHoras">
                <label>Horas trabajadas (en el periodo)</label>
                <input type="number" step="0.01" name="horasTrabajadas" min="0">
                <label>Tarifa por hora</label>
                <input type="number" step="0.01" name="tarifaPorHora" min="0.01">
            </div>

            <div class="campos-extra" id="campos-encargado">
                <label>Bono de liderazgo</label>
                <input type="number" step="0.01" name="bonoLiderazgo" min="0" value="0">
            </div>

            <button type="submit">Agregar empleado</button>
        </form>
    </div>

    <div class="tarjeta">
        <h2>Reporte de nómina</h2>

        <?php if (empty($empleadosLista)): ?>
            <p>Aún no hay empleados registrados.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>Nombre</th><th>Tipo</th><th>Salario calculado</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($empleadosLista as $indice => $empleado): ?>
                        <?php $fila = $filas[$indice]; ?>
                        <tr>
                            <td><?= htmlspecialchars($empleado->getId()) ?></td>
                            <td><?= htmlspecialchars($empleado->getNombre()) ?></td>
                            <td><span class="tipo-tag tag-<?= $fila['tipo'] ?>"><?= $fila['tipo'] ?></span></td>
                            <td>$<?= number_format($empleado->calcularSalario(), 2) ?></td>
                            <td>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="idRegistro" value="<?= $idsRegistro[$indice] ?>">
                                    <button type="submit" class="eliminar">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="total">TOTAL A PAGAR: $<?= number_format($total, 2) ?></div>

            <form method="POST" onsubmit="return confirm('¿Vaciar toda la nómina?');">
                <input type="hidden" name="accion" value="vaciar">
                <button type="submit" class="vaciar">Vaciar toda la nómina</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    const selectTipo = document.getElementById('tipo');
    const camposTiempoCompleto = document.getElementById('campos-tiempoCompleto');
    const camposPorHoras = document.getElementById('campos-porHoras');
    const camposEncargado = document.getElementById('campos-encargado');

    selectTipo.addEventListener('change', function () {
        camposTiempoCompleto.classList.remove('activo');
        camposPorHoras.classList.remove('activo');
        camposEncargado.classList.remove('activo');

        if (this.value === 'tiempoCompleto') {
            camposTiempoCompleto.classList.add('activo');
        } else if (this.value === 'porHoras') {
            camposPorHoras.classList.add('activo');
        } else if (this.value === 'encargado') {
            camposTiempoCompleto.classList.add('activo');
            camposEncargado.classList.add('activo');
        }
    });
</script>
</body>
</html>