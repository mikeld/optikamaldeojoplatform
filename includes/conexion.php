<?php
require_once __DIR__ . '/db_config.php';

class Conexion {
    private $host     = DB_HOST;
    private $db       = DB_NAME;
    private $user     = DB_USER;
    private $password = DB_PASS;
    private $charset  = DB_CHARSET;
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