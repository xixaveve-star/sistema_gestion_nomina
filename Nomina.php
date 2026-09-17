<?php

require_once __DIR__ . '/Empleado.php';

//-- Clase Nomina: administra a todos los empleados --
class Nomina {
    private array $empleados = []; // array de objetos Empleado (mezclados)

    public function agregarEmpleado(Empleado $empleado): void {
        $this->empleados[] = $empleado;
    }

    public function getEmpleados(): array {
        return $this->empleados;
    }

    // Aquí se ve el polimorfismo en acción: no importa el tipo real,
    // todos responden a calcularSalario() a su manera.
    public function calcularTotalNomina(): float {
        $total = 0;
        foreach ($this->empleados as $empleado) {
            $total += $empleado->calcularSalario();
        }
        return $total;
    }

    public function generarReporte(): void {
        echo "===== REPORTE DE NÓMINA =====\n";
        foreach ($this->empleados as $empleado) {
            echo $empleado->mostrarInfo() . "\n";
        }
        echo "------------------------------\n";
        echo "TOTAL A PAGAR: \$" . number_format($this->calcularTotalNomina(), 2) . "\n";
        echo "==============================\n";
    }
}
