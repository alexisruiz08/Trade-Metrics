<?php
// api/csrf.php
// Token CSRF simple por sesión. Requiere que la sesión ya esté iniciada
// (ver session_bootstrap.php) antes de llamar a estas funciones.

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool {
    return !empty($_SESSION['csrf_token']) && !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
}
