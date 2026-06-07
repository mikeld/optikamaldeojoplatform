<?php
/**
 * Sistema de Autenticación Compartido
 * Optikamaldeojo Platform
 */

class Auth {
    private $pdo;
    public const ROL_EMPLEADO = 'empleado';
    public const ROL_ENCARGADO = 'encargado';
    public const ROL_ADMIN = 'admin';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Verifica si el usuario está autenticado
     */
    public static function estaAutenticado() {
        return isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id']);
    }

    public static function basePath() {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        return str_starts_with($scriptName, '/test/') ? '/test' : '';
    }

    public static function loginUrl() {
        return self::basePath() . '/index.php';
    }

    public static function homeUrl() {
        return self::basePath() . '/home.php';
    }
    
    /**
     * Verifica que el usuario esté autenticado, sino redirige al login
     */
    public static function verificarSesion() {
        if (!self::estaAutenticado()) {
            header('Location: ' . self::loginUrl());
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

    public static function rolActual() {
        return $_SESSION['usuario_rol'] ?? null;
    }

    public static function tieneRol($roles) {
        if (!self::estaAutenticado()) {
            return false;
        }

        $roles = is_array($roles) ? $roles : [$roles];
        return in_array(self::rolActual(), $roles, true);
    }

    public static function esAdmin() {
        return self::tieneRol(self::ROL_ADMIN);
    }

    public static function puedeGestionar() {
        return self::tieneRol([self::ROL_ADMIN, self::ROL_ENCARGADO]);
    }

    public static function puedeAccederFacturas() {
        return self::puedeGestionar();
    }

    public static function verificarRoles($roles, $redirect = null) {
        self::verificarSesion();

        if (!self::tieneRol($roles)) {
            header('Location: ' . ($redirect ?? self::homeUrl()));
            exit();
        }
    }

    public static function verificarRolesJson($roles) {
        if (!self::estaAutenticado()) {
            http_response_code(401);
            echo json_encode(['error' => 'No autenticado']);
            exit();
        }

        if (!self::tieneRol($roles)) {
            http_response_code(403);
            echo json_encode(['error' => 'No tienes permisos para acceder a este recurso']);
            exit();
        }
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
