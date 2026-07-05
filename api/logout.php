<?php
// Iniciar la sesión para poder acceder a ella
require_once __DIR__ . '/session_bootstrap.php';
start_secure_session();

// Destruir todas las variables de sesión
$_SESSION = array();

// Borrar la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir la sesión.
session_destroy();

// Redirigir al usuario a la página de login
header("Location: ../login.php");
exit;
?>