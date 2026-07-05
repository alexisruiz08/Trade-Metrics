<?php

// La sesión debe iniciarse antes de leer/escribir $_SESSION.
require_once __DIR__ . '/api/session_bootstrap.php';
require_once __DIR__ . '/api/csrf.php';
require_once __DIR__ . '/api/rate_limiter.php';
start_secure_session();
require 'api/db_connect.php';

if (!isset($_POST['email']) || !isset($_POST['password']) || !csrf_verify($_POST['csrf_token'] ?? null)) {
    header('Location: login.php?error=1');
    exit;
}

$email = $_POST['email'];
$password = $_POST['password'];
$ip = client_ip();

// Límite por IP (cualquier cuenta) y por IP+cuenta (evita fuerza bruta dirigida a un email puntual).
if (!rate_limit_check('login_ip', $ip, 20, 600) || !rate_limit_check('login_account', $ip . '|' . strtolower($email), 5, 600)) {
    header('Location: login.php?error=2');
    exit;
}

// 1. Buscar al usuario
$stmt = $conn->prepare("
    SELECT
        id,
        password_hash
    FROM users
    WHERE email = ?
    LIMIT 1
");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // 2. Verificar la contraseña
    if (password_verify($password, $user['password_hash'])) {
        // 3. Regenerar el ID de sesión para evitar fijación de sesión, y guardar el usuario logueado.
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $email;

        header('Location: app.php');
        exit;
    }
}

// Si algo falla (email no existe o contraseña incorrecta), mismo mensaje genérico en ambos casos.
header('Location: login.php?error=1');
exit;
