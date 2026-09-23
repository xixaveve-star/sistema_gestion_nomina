<?php

// ---------- Clase abstracta base ----------
abstract class Empleado {
    // Encapsulamiento: atributos protegidos, no accesibles directamente desde fuera
    protected string $nombre;
    protected string $id;
    protected string $fechaContratacion;

    public function __construct(string $nombre, string $id, string $fechaContratacion) {
        $this->nombre = $nombre;
        $this->id = $id;
        $this->fechaContratacion = $fechaContratacion;
    }

    // Getters (acceso controlado a los datos)
    public function getNombre(): string {
        return $this->nombre;
    }

    public function getId(): string {
        return $this->id;
    }

    public function getFechaContratacion(): string {
        return $this->fechaContratacion;
    }

    // Método abstracto: cada subclase lo implementa a su manera (polimorfismo)
    abstract public function calcularSalario(): float;

    // Método común, pero que usa el polimorfismo internamente
    public function mostrarInfo(): string {
        $salario = number_format($this->calcularSalario(), 2);
        return "[{$this->id}] {$this->nombre} - Salario: \${$salario}";
    }
}
