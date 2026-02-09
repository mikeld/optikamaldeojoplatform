<?php
class Conexion {
    private $host = "localhost";
    private $db = "db2lgnr4esjpur";
    private $user = "root"; // Cambiar según el usuario de tu MySQL
    private $password = ""; // Cambiar según tu configuración
    private $charset = "utf8mb4";
    public $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host=$this->host;dbname=$this->db;charset=$this->charset", $this->user, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
}
?>