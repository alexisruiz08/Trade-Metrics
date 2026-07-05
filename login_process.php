<?php

// ¡La sesión DEBE iniciarse en la primera línea!
session_start(); 
require 'api/db_connect.php';

if (!isset($_POST['email']) || !isset($_POST['password'])) {
    header('Location: login.php?error=1');
    exit;
}

$email = $_POST['email'];
$password = $_POST['password'];

// 1. Buscar al usuario
// ¡MODIFICADO! Comparamos si la FECHA de expiración es >= que la FECHA de hoy
$stmt = $conn->prepare("
    SELECT 
        id, 
        password_hash, 
        subscription_status,
        (subscription_expires_at >= CURDATE()) AS is_still_active
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
        // ¡Éxito! Contraseña correcta.
        
        // 3. Guardar el ID del usuario en la sesión
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $email; 
        
        // 4. --- ¡LÓGICA DE VERIFICACIÓN CORREGIDA! ---
        
        // is_still_active será 1 (true) si la fecha es hoy o futura.
        $is_valid_date = (isset($user['is_still_active']) && $user['is_still_active'] == 1);

        // 5. Redirigir
        // Si el estado es 'active' Y la fecha ES válida
        if ($user['subscription_status'] === 'active' && $is_valid_date) {
            header('Location: app.php');
        } else {
            // Si está 'pending' O si está 'active' pero expiró
            header('Location: pago.php');
        }
        exit;
    }
}

// Si algo falla (email no existe o contraseña incorrecta)
header('Location: login.php?error=1');
exit;
?>