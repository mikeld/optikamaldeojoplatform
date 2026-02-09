<?php
class Conexion {
    private $host = "localhost"; // En Hostinger suele ser 'localhost'
    private $db = "u373487989_maldeojo"; 
    private $user = "u373487989_mikel"; 
    private $password = "R4;vk+3pT>Nq";
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