<?php

require_once __DIR__ . '/Empleado.php';
require_once __DIR__ . '/Evaluable.php';

// ---------- Empleado de tiempo completo ----------
class EmpleadoTiempoCompleto extends Empleado implements Evaluable {
    protected float $salarioMensual;
    protected float $bonoAnual;

    public function __construct(string $nombre, string $id, string $fechaContratacion, float $salarioMensual, float $bonoAnual = 0) {
        parent::__construct($nombre, $id, $fechaContratacion);
        $this->setSalarioMensual($salarioMensual);
        $this->bonoAnual = max(0, $bonoAnual);
    }

    // Encapsulamiento: validación antes de asignar el salario
    public function setSalarioMensual(float $salario): void {
        if ($salario < 0) {
            throw new InvalidArgumentException("El salario no puede ser negativo.");
        }
        $this->salarioMensual = $salario;
    }

    public function getSalarioMensual(): float {
        return $this->salarioMensual;
    }

    public function calcularSalario(): float {
        return $this->salarioMensual + ($this->bonoAnual / 12);
    }

    public function evaluarDesempeño(): string {
        return "{$this->nombre} tiene una evaluación de desempeño pendiente.";
    }
}
