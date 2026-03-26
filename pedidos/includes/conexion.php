<?php
class Conexion
{
    private $host = "localhost";
    private $charset = "utf8mb4";
    public $pdo;

    public function __construct()
    {
        // --- DETECCIÓN DE ENTORNO ---
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $httpHost = $_SERVER['HTTP_HOST'] ?? '';

        $isTest = (
            strpos($requestUri, '/test/') !== false ||
            strpos($httpHost, 'test') !== false ||
            $httpHost === 'localhost' ||
            $httpHost === '127.0.0.1'
            );

        if ($isTest) {
            // CREDENCIALES TEST
            $db = "u373487989_maldeojotest";
            $user = "u373487989_mikeltest";
            $password = "up3iN57I!";
        }
        else {
            // CREDENCIALES PRODUCCIÓN (REAL)
            $db = "u373487989_maldeojo";
            $user = "u373487989_mikel";
            $password = "R4;vk+3pT>Nq";
        }

        try {
            $dsn = "mysql:host={$this->host};dbname={$db};charset={$this->charset}";
            $this->pdo = new PDO($dsn, $user, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        catch (PDOException $e) {
            die("Error de conexión a la base de datos ($db): " . $e->getMessage());
        }
    }
}
?>