<?php
// api/rate_limiter.php
// Limitador de intentos por archivo (sin depender de una tabla nueva en la BD).
// $bucket agrupa un tipo de acción (ej. "login"), $key identifica a quién se limita
// (ej. IP, o IP+email). No bloquea si el almacenamiento no está disponible: preferimos
// dejar pasar la petición a que un problema de infraestructura tumbe el login para todos.

function rate_limit_check(string $bucket, string $key, int $maxAttempts, int $windowSeconds): bool {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return true;
    }

    $file = $dir . '/rate_' . preg_replace('/[^a-z0-9_-]/i', '_', $bucket) . '.json';
    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return true;
    }

    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
        $data = [];
    }

    $now = time();
    foreach ($data as $k => $timestamps) {
        $data[$k] = array_values(array_filter((array)$timestamps, function ($ts) use ($now, $windowSeconds) {
            return ($now - $ts) < $windowSeconds;
        }));
        if (empty($data[$k])) {
            unset($data[$k]);
        }
    }

    $attempts = $data[$key] ?? [];
    $allowed = count($attempts) < $maxAttempts;
    if ($allowed) {
        $attempts[] = $now;
        $data[$key] = $attempts;
    }

    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($data));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return $allowed;
}

function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}
