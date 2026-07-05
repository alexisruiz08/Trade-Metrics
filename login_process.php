<?php

// �0�3La sesi��n DEBE iniciarse en la primera l��nea!
session_start(); 
require 'api/db_connect.php';

if (!isset($_POST['email']) || !isset($_POST['password'])) {
    header('Location: login.php?error=1');
    exit;
}

$email = $_POST['email'];
$password = $_POST['password'];

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

    // 2. Verificar la contrase�0�9a
    if (password_verify($password, $user['password_hash'])) {
        // �0�3�0�7xito! Contrase�0�9a correcta.
        
        // 3. Guardar el ID del usuario en la sesi��n
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $email;

        header('Location: app.php');
        exit;
    }
}

// Si algo falla (email no existe o contrase�0�9a incorrecta)
header('Location: login.php?error=1');
exit;
?>