<?php

require_once __DIR__ . '/EmpleadoTiempoCompleto.php';
require_once __DIR__ . '/Empleado.php';
require_once __DIR__ . '/Evaluable.php';

// ---------- Encargado (hereda de EmpleadoTiempoCompleto) ----------
class Encargado extends EmpleadoTiempoCompleto {
    protected float $bonoLiderazgo;
    protected array $equipoACargo = []; // lista de objetos Empleado

    public function __construct(string $nombre, string $id, string $fechaContratacion, float $salarioMensual, float $bonoAnual, float $bonoLiderazgo) {
        parent::__construct($nombre, $id, $fechaContratacion, $salarioMensual, $bonoAnual);
        $this->bonoLiderazgo = max(0, $bonoLiderazgo);
    }

    public function agregarEmpleadoAEquipo(Empleado $empleado): void {
        $this->equipoACargo[] = $empleado;
    }

    public function getEquipoACargo(): array {
        return $this->equipoACargo;
    }

    // Polimorfismo: sobreescribe el cálculo del padre y le suma el bono de liderazgo
    public function calcularSalario(): float {
        return parent::calcularSalario() + $this->bonoLiderazgo;
    }

    // El encargado evalúa a los empleados de su equipo
    public function evaluarEquipo(): array {
        $resultados = [];
        foreach ($this->equipoACargo as $empleado) {
            if ($empleado instanceof Evaluable) {
                $resultados[] = $empleado->evaluarDesempeño();
            }
        }
        return $resultados;
    }
}
