<?php
/**
 * Sistema de Autenticación Compartido
 * Optikamaldeojo Platform
 */

class Auth {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Verifica si el usuario está autenticado
     */
    public static function estaAutenticado() {
        return isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id']);
    }
    
    /**
     * Verifica que el usuario esté autenticado, sino redirige al login
     */
    public static function verificarSesion() {
        if (!self::estaAutenticado()) {
            header('Location: /index.php');
            exit();
        }
    }
    
    /**
     * Obtiene los datos del usuario actual
     */
    public static function usuarioActual() {
        if (!self::estaAutenticado()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['usuario_id'] ?? null,
            'nombre' => $_SESSION['usuario_nombre'] ?? '',
            'email' => $_SESSION['usuario_email'] ?? '',
            'rol' => $_SESSION['usuario_rol'] ?? 'usuario'
        ];
    }
    
    /**
     * Autentica un usuario con email y password
     */
    public function autenticar($email, $password) {
        $sql = "SELECT * FROM usuarios WHERE email = :email LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario) {
            return false;
        }
        
        $password_valid = false;
        $stored_password = $usuario['password'];
        $user_id = $usuario['id'];
        
        // Check if the stored password is likely an MD5 hash
        if (strlen($stored_password) === 32 && ctype_xdigit($stored_password) && strpos($stored_password, '$') !== 0) {
            if (md5($password) === $stored_password) {
                $password_valid = true;
                // Rehash and update the password
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE usuarios SET password = :password WHERE id = :id";
                $update_stmt = $this->pdo->prepare($update_sql);
                $update_stmt->bindParam(':password', $new_hash, PDO::PARAM_STR);
                $update_stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                $update_stmt->execute();
            }
        } else {
            // Assume it's a modern hash
            if (password_verify($password, $stored_password)) {
                $password_valid = true;
                // Optionally, rehash if algorithm or options change
                if (password_needs_rehash($stored_password, PASSWORD_DEFAULT)) {
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $update_sql = "UPDATE usuarios SET password = :password WHERE id = :id";
                    $update_stmt = $this->pdo->prepare($update_sql);
                    $update_stmt->bindParam(':password', $new_hash, PDO::PARAM_STR);
                    $update_stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
                    $update_stmt->execute();
                }
            }
        }
        
        if ($password_valid) {
            $_SESSION['usuario_id'] = $user_id;
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['usuario_rol'] = $usuario['rol'];
            return true;
        }
        
        return false;
    }
    
    /**
     * Cierra la sesión del usuario
     */
    public static function cerrarSesion() {
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
}
