<?php
require_once __DIR__ . '/session_bootstrap.php';
start_secure_session();
header('Content-Type: application/json');

// Desactiva mostrar errores en el JSON para no romper el formato, pero repórtalos internamente
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    // 1. Verificar Sesión
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('No has iniciado sesión');
    }
    $user_id = $_SESSION['user_id'];

    // 2. Conexión Robusta
    if (file_exists('db_connect.php')) {
        require_once 'db_connect.php';
    } elseif (file_exists('../api/db_connect.php')) {
        require_once '../api/db_connect.php';
    } else {
        throw new Exception('Error: No se encuentra el archivo db_connect.php');
    }

    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    // ==========================================
    // ACCIÓN: OBTENER DATOS (GET) - POR MES
    // ==========================================
    if ($method === 'GET' && $action === 'get_data') {
        
        // Obtener mes y año de los parámetros, o usar actuales por defecto
        $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
        $year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

        // Calcular fecha de inicio (YYYY-MM-01) y fin del mes
        // mktime(0,0,0, mes, dia, año)
        $startDate = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
        $endDate   = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year)); // 't' da el último día del mes

        // A. OBTENER TRADES DESDE 'TRANSACTIONS'
        // Filtramos por rango de fechas del mes seleccionado
        $sqlTrades = "SELECT DATE(timestamp) as dia, COUNT(id) as total_trades, SUM(amount) as total_pnl 
                      FROM transactions 
                      WHERE user_id = ? 
                        AND type = 'trade'
                        AND DATE(timestamp) BETWEEN ? AND ?
                      GROUP BY DATE(timestamp)";
        
        $stmt = $conn->prepare($sqlTrades);
        if (!$stmt) throw new Exception("Error SQL Transactions: " . $conn->error);
        
        $stmt->bind_param("iss", $user_id, $startDate, $endDate);
        $stmt->execute();
        $resTrades = $stmt->get_result();
        
        $tradesData = [];
        while ($row = $resTrades->fetch_assoc()) {
            $dia = $row['dia']; // Formato YYYY-MM-DD
            $tradesData[$dia] = [
                'count' => (int)$row['total_trades'],
                'pnl'   => (float)$row['total_pnl']
            ];
        }
        $stmt->close();

        // B. OBTENER DIARIO (Tabla 'diario_emocional')
        // Filtramos por rango de fechas del mes seleccionado
        $sqlJournal = "SELECT fecha, pregunta_why, pregunta_setup, pregunta_feeling, pregunta_different, comentarios 
                       FROM diario_emocional 
                       WHERE user_id = ? 
                         AND fecha BETWEEN ? AND ?";
        
        $stmt = $conn->prepare($sqlJournal);
        if (!$stmt) throw new Exception("Error SQL Diario: " . $conn->error);

        $stmt->bind_param("iss", $user_id, $startDate, $endDate);
        $stmt->execute();
        $resJournal = $stmt->get_result();
        
        $journalData = [];
        while ($row = $resJournal->fetch_assoc()) {
            $dia = $row['fecha'];
            $journalData[$dia] = [
                'why'       => $row['pregunta_why'],
                'setup'     => $row['pregunta_setup'],
                'feeling'   => $row['pregunta_feeling'],
                'different' => $row['pregunta_different'],
                'comments'  => $row['comentarios']
            ];
        }
        $stmt->close();

        echo json_encode([
            'success' => true,
            'trades' => $tradesData,
            'journal' => $journalData,
            'period' => [
                'month' => $month,
                'year' => $year,
                'start' => $startDate,
                'end' => $endDate
            ]
        ]);
        exit;
    }

    // ==========================================
    // ACCIÓN: GUARDAR (POST)
    // ==========================================
    if ($method === 'POST' && $action === 'save_entry') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || empty($input['date'])) throw new Exception('Faltan datos');

        $fecha = $input['date'];
        $why = $input['why'] ?? '';
        $setup = $input['setup'] ?? '';
        $feeling = $input['feeling'] ?? '';
        $different = $input['different'] ?? '';
        $comments = $input['comments'] ?? '';

        $sql = "INSERT INTO diario_emocional (user_id, fecha, pregunta_why, pregunta_setup, pregunta_feeling, pregunta_different, comentarios)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                pregunta_why = VALUES(pregunta_why),
                pregunta_setup = VALUES(pregunta_setup),
                pregunta_feeling = VALUES(pregunta_feeling),
                pregunta_different = VALUES(pregunta_different),
                comentarios = VALUES(comentarios)";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssss", $user_id, $fecha, $why, $setup, $feeling, $different, $comments);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception("Error SQL al guardar: " . $stmt->error);
        }
        $stmt->close();
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>