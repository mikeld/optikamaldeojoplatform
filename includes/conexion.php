<?php
class Conexion {
    private $host = "localhost"; // En Hostinger suele ser 'localhost'
    private $db = "PON_AQUI_EL_NOMBRE_DE_TU_BD"; 
    private $user = "PON_AQUI_TU_USUARIO_DE_BD"; 
    private $password = "PON_AQUI_TU_PASSWORD_DE_BD";
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