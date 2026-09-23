<?php

class Database {
    private static ?PDO $conexion = null;

    public static function obtenerConexion(): PDO {
        if (self::$conexion === null) {
            $host = 'localhost';
            $dbname = 'nomina_db';
            $usuario = 'root';
            $password = ''; // XAMPP por defecto no tiene contraseña

            try {
                self::$conexion = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $usuario, $password);
                self::$conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                die("Error de conexión a la base de datos: " . $e->getMessage());
            }
        }
        return self::$conexion;
    }
}
