<?php

require_once __DIR__ . '/Empleado.php';
require_once __DIR__ . '/Evaluable.php';

// ---------- Empleado por horas ----------
class EmpleadoPorHoras extends Empleado implements Evaluable {
    protected float $horasTrabajadas;
    protected float $tarifaPorHora;

    private const LIMITE_HORAS_NORMALES = 40;
    private const FACTOR_HORA_EXTRA = 1.5;

    public function __construct(string $nombre, string $id, string $fechaContratacion, float $horasTrabajadas, float $tarifaPorHora) {
        parent::__construct($nombre, $id, $fechaContratacion);
        $this->setHorasTrabajadas($horasTrabajadas);
        $this->setTarifaPorHora($tarifaPorHora);
    }

    public function setHorasTrabajadas(float $horas): void {
        if ($horas < 0) {
            throw new InvalidArgumentException("Las horas trabajadas no pueden ser negativas.");
        }
        $this->horasTrabajadas = $horas;
    }

    public function setTarifaPorHora(float $tarifa): void {
        if ($tarifa <= 0) {
            throw new InvalidArgumentException("La tarifa por hora debe ser mayor a 0.");
        }
        $this->tarifaPorHora = $tarifa;
    }

    public function calcularSalario(): float {
        if ($this->horasTrabajadas <= self::LIMITE_HORAS_NORMALES) {
            return $this->horasTrabajadas * $this->tarifaPorHora;
        }

        $horasNormales = self::LIMITE_HORAS_NORMALES;
        $horasExtra = $this->horasTrabajadas - self::LIMITE_HORAS_NORMALES;

        return ($horasNormales * $this->tarifaPorHora)
             + ($horasExtra * $this->tarifaPorHora * self::FACTOR_HORA_EXTRA);
    }

    public function evaluarDesempeño(): string {
        return "{$this->nombre} tiene una evaluación de desempeño pendiente.";
    }
}
