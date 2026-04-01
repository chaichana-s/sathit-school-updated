<?php
session_start();

// Application root paths
define('APP_ROOT', str_replace('\\', '/', dirname(__DIR__)));

/**
 * Compute web-relative base URL for the application.
 * e.g. '/sathit-school'
 */
function get_base_url() {
    static $base = null;
    if ($base === null) {
        $doc_root = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/\\'));
        $base = str_replace($doc_root, '', APP_ROOT);
        if (empty($base)) $base = '';
    }
    return $base;
}

// Include Database
require_once APP_ROOT . '/db/config.php';

/**
 * Checks if a user is currently logged in.
 * If not, redirects them securely to the login page.
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        $base = get_base_url();
        header("Location: {$base}/modules/auth/login.php");
        exit();
    }
}

/**
 * Checks if a user has a specific role.
 */
function require_role($allowed_roles) {
    require_login();
    
    $current_role = $_SESSION['user_role'] ?? '';
    
    if (is_array($allowed_roles)) {
        if (!in_array($current_role, $allowed_roles)) {
            die("Access Denied: You do not have permission to view this page.");
        }
    } else {
        if ($current_role !== $allowed_roles) {
            die("Access Denied: You do not have permission to view this page.");
        }
    }
}

/**
 * Convenience functions for roles
 */
function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin';
}
function is_teacher() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Teacher';
}
function is_student() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Student';
}

/**
 * Login function
 */
function attempt_login($pdo, $username, $password) {
    $stmt = $pdo->prepare("SELECT id, username, password_hash, role, reference_id FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['reference_id'] = $user['reference_id'];
        
        return true;
    }
    
    return false;
}

/**
 * Logout function
 */
function perform_logout() {
    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
}
?>
