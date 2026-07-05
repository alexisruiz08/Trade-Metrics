<?php
// app.php
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
session_start();

// Si el usuario no ha iniciado sesión, lo echamos al login.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// --- INICIO VERIFICACIÓN DE PAGO (CORREGIDO CON FECHAS) ---
require_once 'api/db_connect.php'; 

// ¡MODIFICADO! Comparamos si la FECHA de expiración es >= que la FECHA de hoy
$stmt_pay_check = $conn->prepare("
    SELECT 
        subscription_status, 
        (subscription_expires_at >= CURDATE()) AS is_still_active 
    FROM users 
    WHERE id = ?
");
$stmt_pay_check->bind_param("i", $user_id);
$stmt_pay_check->execute();
$res_pay_check = $stmt_pay_check->get_result();
$user_pay_status = $res_pay_check->fetch_assoc();
$stmt_pay_check->close();

// is_still_active será 1 (true) si la fecha es hoy o futura
$is_valid_date = (isset($user_pay_status['is_still_active']) && $user_pay_status['is_still_active'] == 1);

// Si el estado NO es 'active' O SI la fecha NO es válida (expiró o es NULL)
if ($user_pay_status['subscription_status'] !== 'active' || !$is_valid_date) {
    
    // Opcional: Si estaba 'active' pero expiró, lo actualizamos a 'pending'
    if ($user_pay_status['subscription_status'] === 'active' && !$is_valid_date) {
        $stmt_expire = $conn->prepare("UPDATE users SET subscription_status = 'pending' WHERE id = ?");
        $stmt_expire->bind_param("i", $user_id);
        $stmt_expire->execute();
        $stmt_expire->close();
    }
    
    $conn->close(); // Cerramos esta conexión
    header('Location: pago.php');
    exit;
}
// --- FIN VERIFICACIÓN DE PAGO ---

// --- OBTENER TOKEN ---
// Reemplaza 'api/db_connect.php' por tu ruta correcta
require_once 'api/db_connect.php'; 
$stmt = $conn->prepare("SELECT mt5_token FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$user_mt5_token = $user_data['mt5_token'];
$stmt->close();
$conn->close();
// --- FIN OBTENER TOKEN ---
?>

<!doctype html>
<html lang="es">
<head>
    <!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-F4G8QC48');</script>
<!-- End Google Tag Manager -->
<link rel="icon" type="image/png" href="logo3.png">
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />

<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">

<title>Trade Metrics</title>
<style>
  :root{
    --bg:#0f1724; --card:#161a22; --muted:#9aa4b2; --accent:#0ea600; --danger:#d04040;
    --glass: rgba(255,255,255,0.03);
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
  }
  html,body{height:100%;margin:0;background:linear-gradient(180deg,#192731 0%, #192731 100%);color:#e6eef6} 
  
  /* --- RESPONSIVE: Ajuste del cuerpo para la sidebar --- */
  body {
      padding-left: 80px; 
      box-sizing: border-box;
      overflow-x: hidden; 
  }
  
  #periodSelector option {
    background-color: #161a22; /* El color de fondo de tu panel */
    color: white;              /* Color del texto */
    padding: 10px;
}

    #accountSelector option {
        background-color: #161a22; /* El color de fondo de tu panel */
        color: white;              /* Color del texto */
        padding: 10px;
    }

  /* --- RESPONSIVE: Wrapper principal con ancho MÁXIMO AJUSTADO --- */
  .wrap {
      width: 96%; 
      max-width: 1600px; 
      margin: 25px auto 0 auto;
      padding-right: 20px; 
      box-sizing: border-box;
  }
  
  header{display:flex;align-items:center;gap:18px;margin-bottom:10px; flex-wrap: wrap;}
  header h1{margin:0;font-size: clamp(24px, 2.5vw, 30px); letter-spacing:0.2px} 
  
  .grid{display:grid;grid-template-columns: 1fr;gap:18px}
  
  .card{background:var(--card);padding:14px;border-radius:10px; box-sizing: border-box;}
  
  /* Inputs y controles */
  label{display:block;font-size:13px;color:var(--muted);margin-bottom:6px}
  input,select{width:150px;padding:8px 10px;border-radius:8px;border:1px solid rgba(255,255,255,0.04);background:var(--glass);color:inherit;font-size:14px; box-sizing: border-box;}
  .btn{display:inline-block;padding:8px 10px;border-radius:8px;background:#0ea600;color:#012322;border:none;cursor:pointer;font-weight:600}
  .btn.warn{background:#0ea600;color:#221200;box-shadow: 0 0 10px #16ff0045}
  
  /* Tablas responsivas */
  #tableContainer { overflow-x: auto; } 
  table{width:100%;border-collapse:collapse;margin-top:8px; min-width: 600px;}
  th,td{padding:8px;text-align:left;border-bottom:1px dashed rgba(255,255,255,0.03);font-size:13px}
  th{color:var(--muted);font-weight:600}
  
  .chart-container { margin-top: 12px; height: 420px; width: 100%; } 

  /* --- RESPONSIVE: Grid de Métricas (Botones) --- */
  .metrics {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
      gap: 8px;
      margin-top: 10px;
  }
  
  .ticker-item.up .arrow { color: var(--accent); }
  .ticker-item.down .arrow { color: var(--danger); }

  .metric{background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));padding:10px;border-radius:8px}
  .metric b{display:block;font-size:18px}
  
  /* --- RESPONSIVE: Contenedores de Filas --- */
  .row-container {
    display: grid;
    gap: 18px;
    margin-top: 18px;
  }
  
  /* Para los inputs (cajas de una línea) */
    input:focus {
        outline: none;
        border-color: #0ea600 !important;          /* Borde verde */
        box-shadow: 0 0 0 3px rgba(14, 166, 0, 0.3) !important; /* Ring verde suave */
        transition: all 0.2s ease;
    }
    
    /* Mantenemos el estilo original para textarea y select (o el que tenías) */
    textarea:focus, select:focus {
        outline: none;
        border-color: var(--accent) !important;    /* O el color que ya tenías */
        box-shadow: none !important;               /* Sin ring extra si no lo querés */
        transition: all 0.2s ease;
    }
  

  /* CLASE EXISTENTE: Para dividir gráfico Pie (izq) y Métricas (der) */
  .grid-split-dashboard {
      grid-template-columns: 1fr; 
  }
  @media (min-width: 1200px) { 
      .grid-split-dashboard {
          grid-template-columns: 320px 1fr 320px; 
      }
  }

  /* CLASE EXISTENTE: Para gráficos lado a lado (50% / 50%) */
  .grid-dual-charts {
      grid-template-columns: 1fr; 
  }
  @media (min-width: 1024px) { 
      .grid-dual-charts {
          grid-template-columns: 1fr 1fr;
      }
  }
  
  /* --- CLASE NUEVA: Para 25% | 25% | 50% --- */
  .grid-triple-split {
      grid-template-columns: 1fr; /* Móvil: Apilado */
  }
  @media (min-width: 1200px) { /* Monitor grande */
      .grid-triple-split {
          /* 1fr 1fr 2fr = 25% 25% 50% */
          grid-template-columns: 1fr 1fr 2fr;
      }
  }

  /* Nueva clase para tres columnas iguales (Distribución, Radar, Día de Semana) */
  .grid-triple-charts {
      grid-template-columns: 1fr; /* Móvil: Apilado */
  }
  @media (min-width: 1024px) { 
      .grid-triple-charts {
          grid-template-columns: 1fr 1fr 1fr; /* Partes iguales en desktop */
      }
  }
  /* ------------------------------------------- */


  /* Sidebar Styles (Mantenidos) */
  .sidebar {
      position: fixed; top: 0; left: 0; height: 100%; width: 80px;
      background: var(--card); border-right: 1px solid rgba(255,255,255,0.05);
      display: flex; flex-direction: column; align-items: center; padding: 20px 0;
      z-index: 2000; box-shadow: 4px 0 15px rgba(0,0,0,0.3);
  }
  .sidebar-logo { margin-bottom: 40px; width: 40px; height: 40px; background-image: url('logo5.png'); background-size: cover; border-radius: 50%; opacity: 0.8; }
  .nav-item { width: 50px; height: 50px; border-radius: 12px; background: transparent; border: none; color: var(--muted); cursor: pointer; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; transition: all 0.3s ease; position: relative; }
  .nav-item svg { width: 24px; height: 24px; stroke-width: 2; }
  .nav-item:hover { background: rgba(255,255,255,0.05); color: #e6eef6; }
  .nav-item.active { background: var(--accent); color: #012322; box-shadow: 0 0 10px #16ff0045; }
  .nav-item.logout { margin-bottom: 45px; color: var(--danger); }
  a.nav-item { text-decoration: none; display: flex; align-items: center; justify-content: center; }

  /* Helpers */
  .chart-card-content { height: 100%; display: flex; flex-direction: column; }
  .chart { flex-grow: 1; height: auto; min-height: 100px; display: flex; align-items: center; justify-content: center; }
  footer{margin-top:16px;color:var(--muted);font-size:13px}
  .help{font-size:13px;color:var(--muted);line-height:1.4}
  
  /* Ticker */
  .ticker-wrapper {
    width: 100%;
    background: #000;
    overflow: hidden;
    padding: 5px 0;
    box-shadow: none;

    position: fixed;
    top: 0;
    left: 0;
    z-index: 1000;
}

  .ticker-content { display: inline-block; white-space: nowrap; animation: tickerScroll 60s linear infinite; }
  .ticker-item { display: inline-block; padding: 0 15px; font-size: 14px; }
  .ticker-item.up .price { color: var(--accent); } .ticker-item.down .price { color: var(--danger); }
  @keyframes tickerScroll { 
    from { transform: translateX(100vw); } 
    to { transform: translateX(-100%); } 
}
 .green-line {
    height: 3px;
    background-color: #0ea600;
    width: 100%;

    position: fixed;
    top: 32px;      /* debajo del ticker */
    left: 0;
    z-index: 999;
}

.green-line-bottom {
    position: fixed;
    top: 42px;
    left: 0;
    width: 100%;
    height: 1px;
    background: #1f1f1f;
}

  /* Controles de filtro */
  .filter-controls, .sort-controls { display: flex; gap: 8px; align-items: center; margin-top: 5px; flex-wrap: wrap; }
  
  /* Settings Modal */
  .settings-btn { position: fixed; top: 52px; right: 3px; font-size: 24px; background: none; border: none; color: var(--muted); cursor: pointer; z-index: 1100; }
  .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; backdrop-filter: blur(8px); background: rgba(0, 0, 0, 0.4); opacity: 0; visibility: hidden; z-index: 1000; transition: 0.3s; }
  .overlay.active { opacity: 1; visibility: visible; }
  .modal { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.95); background: var(--card); padding: 25px; border-radius: 15px; width: 90%; max-width: 400px; opacity: 0; visibility: hidden; z-index: 1101; transition: 0.3s; }
  .modal.active { opacity: 1; visibility: visible; transform: translate(-50%, -50%) scale(1); }
  .modal input { width: 100%; padding: 8px; margin-bottom: 10px; border-radius: 8px; background: var(--glass); color: white; border: 1px solid #333; }
  .modal .btn { width: 100%; margin-top: 10px;box-shadow: 0 0 10px #16ff0045}
  .small {font-size: 13px;color: var(--muted);}

  /* Media query para pantallas muy pequeñas (Móvil vertical) */
  @media (max-width: 768px) {
      body { padding-left: 0; padding-bottom: 60px; } 
      .sidebar { width: 100%; height: 60px; bottom: 0; top: auto; flex-direction: row; justify-content: space-around; border-right: none; border-top: 1px solid rgba(255,255,255,0.05); }
      .sidebar-logo { display: none; }
      .nav-item { margin-bottom: 0; }
      .nav-item::after { display: none; } 
      .wrap { width: 92%; margin-left: auto; margin-right: auto; }
      header h1 { font-size: 24px; }
  }
</style>
<script src="https://cdn.jsdelivr.net/npm/papaparse@5.4.1/papaparse.min.js"></script>
</head>
<body>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-F4G8QC48"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
<nav class="sidebar">
    <div class="sidebar-logo"></div> 

    <a href="social.php" class="nav-item" id="navProfile" data-tooltip="Red Social">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
        </svg>
    </a>

    <a href="app.php" class="nav-item active" id="navChart" data-tooltip="Trade Metrics">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z" />
        </svg>
    </a>

    <a href="diario.php" class="nav-item" id="navJournal" data-tooltip="Diario">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
        </svg>
    </a>

    <button class="nav-item" id="navSettings" data-tooltip="Configuración">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
    </button>

    <button class="nav-item logout" id="navLogout" data-tooltip="Cerrar Sesión">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
        </svg>
    </button>
</nav>

<div class="ticker-wrapper">
    <div id="ticker" class="ticker-content">
    </div>
</div>
<div class="green-line"></div>
<div id="datetime" style="color:var(--muted); font-size:13px; margin-top: 40px; text-align:right; font-weight:bold; padding-right: 10px;"></div>
<div class="wrap">
    

<div id="overlay" class="overlay"></div>

<div id="settingsModal" class="modal">
    <div class="modal-content">
        <h2>Configuración de Cuenta</h2>

        <section>
            <h3>Cambiar Contraseña</h3>
            <form id="changePasswordForm">
                <label>Contraseña Actual</label>
                <input id="current_password" type="password" required>

                <label>Nueva Contraseña</label>
                <input id="new_password" type="password" required>

                <label>Confirmar Nueva Contraseña</label>
                <input id="confirm_password" type="password" required>

                <button type="submit" class="btn" style="margin-bottom:10px">Actualizar Contraseña</button>
                <div id="passwordMessage" class="message"></div>
            </form>
        </section>

        <hr>

        <section>
            <h3>Conexión MT5</h3>
            <label>Tu Token Personal (InpSecretToken)</label>
            <input type="text" readonly value="<?php echo htmlspecialchars($user_mt5_token); ?>">

            <label>InpWebServerURL (Trades)</label>
            <input type="text" readonly value="https://trademetrics.online/api/mt5_webhook.php">
            
            <button id="downloadBtn" class="btn">Descargar EA.ex5</button>
        </section>

    </div>
</div>
    
  <header style="margin-left: 10px; margin-bottom:20px">

    <img src="logo5.png" alt="Trade Metrics Logo" width="85" height="85">
    <div>
      <h1>
        <span style="font-size: 40px; display:block">Trade Metrics</span>
        <span>Equity: <span style="color:#0ea600">USD <span id="currentEquity">0.00</span></span></span>
        
      </h1>
      <div class="small">Análisis de rendimiento de cuenta TWRR. Sincronizado automáticamente desde MT5.</div>
    </div>
  </header>

    <div class="grid">

    <div> <div id="cardContainer" style="position: relative;">
        <div id="metricsCard" class="card" style="box-shadow: 0 6px 18px rgba(2, 6, 23, 0.6)">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <h3 style="margin:0; font-size: 27px; margin-bottom: 10px;">Estadísticas de Rendimiento</h3>
        <div class="small">Operaciones totales: <span id="totalTrades">0</span></div>
    </div>

    <div class="filter-controls">
        <label for="periodSelector" style="margin:0; min-width: 60px;">Período:</label>
        <select id="periodSelector">
            <option value="global">Global</option>
            <option value="weekly">Semanal</option>
            <option value="monthly">Mensual</option>
            <option value="yearly">Anual</option>
        </select>

    </div>

    <!-- NUEVO: Selector de Cuenta -->
    <div class="filter-controls">
        <label for="accountSelector" style="margin:0; min-width: 60px;">Cuenta:</label>
        <select id="accountSelector">
        </select>
    </div>

    <div class="sort-controls">
        <!--<label for="sortSelector" style="margin:0; min-width: 60px;">Ordenar:</label>-->
        <select id="sortSelector">
            <option value="insertion">Inserción</option>
            <option value="date-desc">Fecha (Acendente)</option>
            <option value="date-asc">Fecha (Decendente)</option>
            <option value="gain">Ganancia</option>
            <option value="loss">Pérdida</option>
        </select>
    </div>
    
    <div class="row-container grid-split-dashboard">
        
        <div class="card" style="background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)); min-height: 300px;">
            <h3 style="color: #e6eef6; margin-top: 0%;">Win Rate Global ($)</h3>
            <div style="position: relative; height: 250px;">
                <canvas id="winLossPieChart"></canvas>
            </div>
        </div>

        <div class="metrics" style="grid-template-columns: repeat(3, 1fr); margin-top:0;">
            <div class="metric"><small class="small">Win Rate (wins / total)</small><b id="winRate">—</b><div class="small"></div></div>
            <div class="metric"><small class="small">Expectancy ($ por operación)</small><b id="expectancy">—</b></div>
            <div class="metric"><small class="small">Profit Factor</small><b id="profitFactor">—</b></div>
            <div class="metric"><small class="small">Sharpe (sobre R)</small><b id="sharpe">—</b></div>
            <div class="metric"><small class="small">Kelly (full, sobre R)</small><b id="kellyFull">—</b></div>
            <div class="metric"><small class="small">Kelly (½, sobre R)</small><b id="kellyHalf">—</b></div>
            <div class="metric"><small class="small">Max Drawdown (%)</small><b id="maxDD">—</b></div>
            <div class="metric"><small class="small">Calmar (Anualizado)</small><b id="calmar">—</b></div>
            <div class="metric"><small class="small">Total Wins (filtrado)</small><b id="dayWins">—</b></div>
            <div class="metric"><small class="small">Avg Win/Loss Ratio ($)</small><b id="avgWinLossRatio">—</b></div>
            <div class="metric"><small class="small">Retorno Total (TWRR)</small><b id="totalReturn">—</b></div>
            <div class="metric"><small class="small">R-Corregido (Avg R)</small><b id="rCorrected">—</b></div>
        </div>
        
        <div class="card" style="background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)); min-height: 300px; padding:10px;">
             <div class="chart-card-content">
                <h3 style="color: #e6eef6; margin: 0 0 15px 0; font-size: 1.17em; font-weight: bold;">PnL Long vs Short</h3>
                <div style="position: relative; flex-grow: 1;"><canvas id="longShortChart"></canvas></div>
            </div>
        </div>
        
    </div>

    <div class="row-container" style="display: grid; margin-top: 8px; grid-template-columns: 1fr; gap: 18px;">
        <div class="chart-container">
            <div class="card" style="background: linear-gradient(180deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.01)); padding:10px; height: 550px;">
                <div class="chart-card-content">
                    <div style="margin-bottom: 15px; font-size: 1.17em; font-weight: bold; color: #e6eef6;" class="small">Curva de Equity</div>
                    <canvas id="equityChart" class="chart"></canvas>
                    <div style="margin-top:8px" class="small">Retorno Total: <b id="totalReturn_footer">—</b> | R-Corregido (avg R/operación): <b id="rCorrected_footer">—</b></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row-container" style="display: grid; margin-top: 150px; grid-template-columns: 1fr 1fr; gap: 18px;">
        
        <div class="card" style="background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)); min-height: 400px; padding:10px;"> 
            <div class="chart-card-content">
                <h3 style="color: #e6eef6; margin-top: 0; margin-bottom: 15px; font-size: 1.17em; font-weight: bold;">Dispersión PnL ($) vs R</h3>
                <div style="position: relative; flex-grow: 1;">
                    <canvas id="rFactorScatterChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="card" style="background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)); min-height: 400px; padding:10px;"> 
             <div class="chart-card-content">
                <h3 style="color: #e6eef6; margin-top: 0; margin-bottom: 15px; font-size: 1.17em; font-weight: bold;">PnL Acumulado por Mes (USD)</h3>
                <div style="position: relative; flex-grow: 1;">
                    <canvas id="monthlyPnlChart"></canvas>
                </div>
            </div>
        </div>

    </div>
    
    <div class="row-container grid-triple-charts">
        
        <div class="card" style="background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)); min-height: 400px; padding:10px;">
             <div class="chart-card-content">
                <h3 style="color: #e6eef6; margin-top: 0; margin-bottom: 15px; font-size: 1.17em; font-weight: bold;">Distribución de Resultados (% de R)</h3>
                <div style="position: relative; flex-grow: 1;">
                    <canvas id="pnlDistributionChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card" style="background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)); min-height: 400px; padding:20px;">
            <div class="chart-card-content">
                <h3 style="color: #e6eef6; margin-top: 0; margin-bottom: 15px; font-size: 1.17em; font-weight: bold;">Análisis de Fortalezas/Debilidades (Radar)</h3>
                <div style="position: relative; flex-grow: 1; max-width: 500px;">
                    <canvas id="radarChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card" style="background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)); min-height: 400px; padding:10px;"> 
             <div class="chart-card-content">
                <h3 style="color: #e6eef6; margin-top: 0; margin-bottom: 15px; font-size: 1.17em; font-weight: bold;">PnL por Día de la Semana</h3>
                <div style="position: relative; flex-grow: 1;">
                    <canvas id="dayOfWeekChart"></canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- FILA 5 (NUEVA): Long/Short y Drawdown -->
    <div class="row-container">
        
        <div class="card" style="background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)); min-height: 500px; padding:10px;"> 
             <div class="chart-card-content">
                <h3 style="color: #e6eef6; margin: 0 0 15px 0; font-size: 1.17em; font-weight: bold;">Curva de Drawdown Submarina</h3>
                <div style="position: relative; flex-grow: 1;"><canvas id="drawdownChart"></canvas></div>
            </div>
        </div>
    </div>
    
    <div style="color: #012322;">.</div>
    
    <!-- BOTÓN PARA OCULTAR/MOSTRAR TABLA -->
    <div style="text-align: center; margin-top: 10px; margin-bottom: 15px;">
        <button id="toggleTableBtn" class="btn warn" style="width: auto; padding: 10px 15px; font-size: 12px;">Ocultar Tabla de Trades</button>
    </div>

    <!-- CONTENEDOR DE LA TABLA -->
    <div id="tableContainer">
        <table id="tradesTable" style="margin-top:0px">
            <thead>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    
</div>

      </div>

      <div class="card" style="margin-top:12px">
        <h3 style="margin:0">Ayuda</h3>
        <div class="help" style="margin-top:8px">
          <p><b>Win Rate:</b> Mide la frecuencia con la que ganas. Indica cuánto % de operaciones cierras en beneficio del total, demostrando si tu estrategia se basa en acertar a menudo o en obtener grandes ganancias ocasionales.</p>
          <p><b>Expectancy:</b> Muestra el beneficio promedio que puedes esperar de cada operación. Un valor positivo indica una estrategia rentable a largo plazo. Se calcula como <code>(Win Rate * Ganancia Promedio) - (Loss Rate * Pérdida Promedio)</code>.</p>
          <p><b>Profit Factor:</b> Es la relación entre la suma total de tus ganancias y la suma total de tus pérdidas. Un <b>Profit Factor</b> de 2, por ejemplo, significa que por cada dólar que pierdes, ganas 2 dólares.</p>
          <p><b>Sharpe Ratio:</b> Es la calidad de tus retornos ajustados por el riesgo, funcionando como una "Relación Retorno-Ruido". Un valor alto indica que tu cuenta crece de manera suave y consistente, sin volatilidad excesiva o saltos erráticos.</p>
          <p><b>Kelly (full) y Kelly (½):</b> Determina el riesgo óptimo de crecimiento. Es el porcentaje exacto de tu capital que deberías arriesgar por operación para alcanzar la tasa de crecimiento más rápida. Generalmente se usa Kelly (½) para reducir la volatilidad y el riesgo de quiebra.</p>
          <p><b>Max Drawdown (%):</b> Representa el peor escenario de dolor financiero. Mide la mayor caída porcentual desde un pico de capital hasta un valle subsiguiente, siendo la métrica principal para evaluar el control de riesgo de tu sistema.</p>
          <p><b>Calmar (Return / MaxDD):</b> Es tu eficiencia de recuperación. Mide la magnitud de tu retorno total frente a tu máxima caída histórica (Max Drawdown). Un valor alto sugiere que obtienes grandes beneficios sin experimentar caídas profundas en tu capital.</p>
          
          ---

          <p><b>R-Corregido:</b> Esta métrica es la Expectancy ajustada por la unidad de riesgo (R). Indica, en promedio, cuántas "R" ganas por operación. Es una forma de medir el rendimiento de tu estrategia independientemente del tamaño de tu capital, lo solido, deseable es 0.2 a 0.5 R.</p>
          
          ---

          <p><b>Distribución de Resultados:</b> Esta gráfica es un histograma que clasifica todas sus operaciones según el porcentaje que ganaron o perdieron.
          <b>¿Qué Mide? </b>La frecuencia y la magnitud de sus resultados. Le muestra cuántas veces obtuvo una ganancia pequeña (+1% a +2%), una pérdida grande (-5% a -6%), etc.</p>
          <p><b>Dispersión PnL ($) vs R:</b> Este gráfico de dispersión (scatter plot) es la herramienta más importante para evaluar la gestión del riesgo y recompensa. <b>Eje X (Horizontal): Factor R</b> (Múltiplos de riesgo). Muestra cuánto ganó o perdió en relación con la cantidad que arriesgó. Por ejemplo, un punto en +3R significa que ganó 3 veces su riesgo inicial. <b>Eje Y (Vertical): PnL ($)</b> (Ganancia o Pérdida en dólares). Muestra el monto real de dinero ganado o perdido por la operación.</p>
          <p><b>PnL Acumulado por Mes:</b> Esta gráfica de línea de tiempo es la representación directa de la evolución de su cuenta. <b>Qué Mide?</b> El crecimiento neto de su capital, sumando todas las ganancias y restando todas las pérdidas hasta la fecha, agrupadas por mes.</p>

          ---

          <p><b>PnL por Día de la Semana:</b> Gráfico de barras que acumula sus ganancias y pérdidas por cada día (Lun-Vie). Útil para detectar si ciertos días son sistemáticamente rentables o perdedores para su estilo.</p>
          
          <p><b>Long vs Short:</b> Compara su rendimiento en compras (Long) vs ventas (Short). Ayuda a identificar si tiene un sesgo direccional o si es más efectivo operando en una dirección específica del mercado.</p>
          
          <p><b>Curva de Drawdown Submarina:</b> Gráfico de área que visualiza qué tan lejos está su cuenta de su máximo histórico (0%). Muestra la profundidad (en %) y duración de sus "malas rachas", siendo vital para medir la salud emocional y el riesgo de la cuenta.</p>

          <p><b>Análisis de Fortalezas/Debilidades (Radar):</b> Visualiza métricas clave en un gráfico radial. Áreas grandes indican fortalezas (e.g., alto Profit Factor = buena rentabilidad); áreas pequeñas, debilidades (e.g., bajo Recovery Factor = lenta recuperación de pérdidas). Normalizado 0-100 para comparación.</p>
        </div>
      </div>

      <footer>
        <div style="margin-bottom: 20px;display:flex;justify-content:space-between;align-items:center">
          <div>Sincronizado desde MT5.</div>
          <div class="small">Trade Metrics v2.5</div>
        </div>
      </footer>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

const COLOR_MUTED = '#9aa4b2';  // Gris original de las etiquetas

// --- Lógica del Modal (Sin Cambios) ---
const openBtn = document.getElementById('openSettings');
    const modal = document.getElementById('settingsModal');
    const overlay = document.getElementById('overlay');

    if (openBtn) {
        openBtn.addEventListener('click', () => {
            modal.classList.toggle('active');
            overlay.classList.toggle('active');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', () => {
            modal.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

/* === LÓGICA SIDEBAR (Sin Cambios) === */

    // 1. Botón Ajustes (Tuerca)
    const navSettingsBtn = document.getElementById('navSettings');
    if (navSettingsBtn) {
        navSettingsBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const modal = document.getElementById('settingsModal');
            const overlay = document.getElementById('overlay');
            
            if (modal.classList.contains('active')) {
                 modal.classList.remove('active');
                 overlay.classList.remove('active');
            } else {
                 modal.classList.add('active');
                 overlay.classList.add('active');
            }
        });
    }

    // 2. Botón Logout
    const navLogoutBtn = document.getElementById('navLogout');
    if (navLogoutBtn) {
        navLogoutBtn.addEventListener('click', () => {
            if(confirm('¿Estás seguro que quieres cerrar sesión?')) {
                window.location.href = 'api/logout.php';
            }
        });
    }

// --- Lógica del Botón Descargar (Sin Cambios) ---
const downloadBtn = document.getElementById('downloadBtn');
if (downloadBtn) {
    downloadBtn.addEventListener('click', () => {
        const link = document.createElement('a');
        link.href = 'scripts/TradeHistory_WebRequest.ex5';
        link.download = 'TradeHistory_WebRequest.ex5';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      });
}


/*
  App de métricas de trading.
  MODIFICADO PARA USAR LÓGICA TWRR (v2.4 - Robusta)
*/

document.addEventListener('DOMContentLoaded', () => {
  (async function() {

    // =======================================================
    // --- NÚCLEO DE API (TWRR) ---
    // =======================================================
    
    const API_URL = 'api/';

    // NUEVO: Cargar lista de cuentas (account_tag)
    const loadAccounts = async function() {
      try {
        const response = await fetch(API_URL + 'get_accounts.php');
        if (!response.ok) throw new Error('Network response was not ok');
        return await response.json();
      } catch (e) {
        console.error('Error al cargar cuentas:', e);
        return [];
      }
    };

    // MODIFICADO: Acepta account_tag para filtrar
    const loadTransactions = async function(account_tag = null) {
      try {
        let url = API_URL + 'get_transactions.php';
        if (account_tag) {
          url += `?account_tag=${encodeURIComponent(account_tag)}`;
        }
        const response = await fetch(url);
        if (!response.ok) throw new Error('Network response was not ok');
        const data = await response.json();
        return data.map(tx => ({
            ...tx,
            amount: parseFloat(tx.amount),
            balance_after: parseFloat(tx.balance_after),
            risk_r: parseFloat(tx.risk_r),
            buysell_type: tx.buysell_type
        }));
      } catch (e) {
        console.error('Error al cargar transacciones:', e);
        return [];
      }
    };
    
    // =======================================================
    // --- Helpers (Sin Cambios) ---
    // =======================================================
    
    const q = (id) => document.getElementById(id);
    const fmt = (n, dec=2) => (n === null || isNaN(n)) ? '—' : Number(n).toFixed(dec); 
    const parseFloatSafe = (v) => { const n = parseFloat(v); return isFinite(n) ? n : null; };

    let allTransactions = []; 
    let myChart = null; 
    let pnlDistributionChartInstance;
    let winLossPieChartInstance;
    let rFactorScatterChartInstance;
    let monthlyPnlChartInstance;
    let dayOfWeekChartInstance;
    let longShortChartInstance;
    let drawdownChartInstance;
    let currentPeriod = 'global'; 
    let currentAccount = '';  // NUEVO: Cuenta seleccionada

    const COLOR_ACCENT_HEX = '#0ea600';
    const COLOR_DANGER_HEX = '#d04040';
    const COLOR_ACCENT_RGBA = '#0ea600';
    const COLOR_DANGER_RGBA = '#d04040';
    const COLOR_SHADOW_RGBA = 'rgba(165, 191, 19, 0.1)';
    const COLOR_TEXT_WHITE = '#E6EEF6';
    const COLOR_BG_DARK = '#0F1724';
    const COLOR_CARD_DARK = '#161A22';
    const GRID_COLOR = 'rgba(230, 238, 246, 0.15)';


    const calculateCapitalChange = function(startCapital, currentEquity) {
        if (startCapital === null || isNaN(startCapital)) {
            return { pctChange: '—', usdChange: '—' };
        }
        const usdChange = currentEquity - startCapital;
        let pctChange = (startCapital === 0) ? (usdChange === 0 ? '0.00%' : 'N/A') : (usdChange / startCapital) * 100;
        return {
            pctChange: isFinite(pctChange) ? fmt(pctChange, 2) + ' %' : '—',
            usdChange: `USD ${fmt(usdChange, 2)}`
        };
    };

    const filterTransactionsByPeriod = function(transactions, period) {
      if (period === 'global' || transactions.length === 0) {
        return transactions;
      }
      const now = new Date();
      let startDate;
      if (period === 'weekly') {
        const day = now.getDay() || 7;
        startDate = new Date(now.getFullYear(), now.getMonth(), now.getDate() - day + 1);
      } else if (period === 'monthly') {
        startDate = new Date(now.getFullYear(), now.getMonth(), 1);
      } else if (period === 'yearly') {
        startDate = new Date(now.getFullYear(), 0, 1);
      }
      startDate.setHours(0, 0, 0, 0);
      const startTime = startDate.getTime();
      const startIndex = transactions.findIndex(tx => new Date(tx.timestamp).getTime() >= startTime);
      if (startIndex === -1) return [];
      if (startIndex > 0) return transactions.slice(startIndex - 1);
      return transactions;
    };

    const updateEquityDisplay = (currentEquity, startCapital) => {
        const currentEquityEl = q('currentEquity');
        if (currentEquityEl) { 
            currentEquityEl.innerText = fmt(currentEquity, 2);
        }
        
        // ¡ELIMINADO! La calculadora ya no existe
        // const calcCapitalInput = q('calcCapital');
        // if (calcCapitalInput) {
        //     calcCapitalInput.value = fmt(currentEquity, 2);
        // }

        const change = calculateCapitalChange(startCapital, currentEquity); 
        const changePctEl = q('capitalChangePct');
        const changeUSDEl = q('capitalChangeUSD');
        if (changePctEl && changeUSDEl) {
            const color = (currentEquity - startCapital) >= 0 ? 'var(--accent)' : 'var(--danger)';
            changePctEl.innerHTML = `<span style="color:${color}">${change.pctChange}</span>`;
            changeUSDEl.innerHTML = `<span style="color:${color}">${change.usdChange}</span>`;
        }
    };


    /**
     * (MOTOR TWRR v2.4 - ¡CORREGIDO!)
     * Lógica de cálculo robusta para TWRR, DD, R, $ y Calmar.
     */
    const calcMetrics = function() {
      const filteredTxs = filterTransactionsByPeriod(allTransactions, currentPeriod);
      
      if (filteredTxs.length === 0) {
          updateEquityDisplay(0, 0); 
          return null;
      }

      // --- A. Métricas de P/L ($) (Basadas en TODOS los trades) ---
      const allTradesInPeriod = filteredTxs.filter(tx => tx.type === 'trade');
      const allTrades_N = allTradesInPeriod.length;
      
      const dollarWins = allTradesInPeriod.filter(tx => tx.amount > 0);
      const dollarLosses = allTradesInPeriod.filter(tx => tx.amount < 0);
      
      const dollarWinRate = allTrades_N > 0 ? dollarWins.length / allTrades_N : 0;
      
      const sumGains = dollarWins.reduce((s, r) => s + r.amount, 0);
      const sumAbsLosses = Math.abs(dollarLosses.reduce((s, r) => s + r.amount, 0));
      
      const avgWinUSD = dollarWins.length ? sumGains / dollarWins.length : 0;
      const avgLossUSD = dollarLosses.length ? sumAbsLosses / dollarLosses.length : 0;

      const profitFactor = sumAbsLosses === 0 ? (sumGains > 0 ? Infinity : NaN) : sumGains / sumAbsLosses;
      const avgWinLossRatio = avgLossUSD === 0 ? (avgWinUSD > 0 ? Infinity : NaN) : avgWinUSD / avgLossUSD;
      const expectancy = allTrades_N > 0 ? (sumGains - sumAbsLosses) / allTrades_N : 0;

      const totalNetProfit = sumGains - sumAbsLosses;


      // --- B. Métricas de Riesgo (R) (Basadas SÓLO en trades con R > 0) ---
      const tradesWithRisk = filteredTxs.filter(tx => tx.type === 'trade' && tx.risk_r > 0);
      const N_risk = tradesWithRisk.length; 
      
      const r_multiples = N_risk > 0 ? tradesWithRisk.map(tx => tx.amount / tx.risk_r) : [];
      
      const rWins = r_multiples.filter(r => r > 0);
      
      // --- ¡CORRECCIÓN v2.4! ---
      // Las "no-ganancias" (pérdidas Y breakeven) se agrupan.
      const rLosses = r_multiples.filter(r => r <= 0); 
      // --- FIN CORRECCIÓN ---
      
      const rWinRate = N_risk > 0 ? rWins.length / N_risk : 0;
      
      const avgWinR = rWins.length ? rWins.reduce((s, r) => s + r, 0) / rWins.length : 0;
      const avgLossR = rLosses.length ? Math.abs(rLosses.reduce((s, r) => s + r, 0)) / rLosses.length : 0;
      
      const expectancyR = N_risk > 0 ? r_multiples.reduce((s, r) => s + r, 0) / N_risk : 0;

      // Sharpe
      const meanRet = expectancyR; 
      const variance = N_risk > 0 ? r_multiples.reduce((s, x) => s + Math.pow(x - meanRet, 2), 0) / N_risk : 0;
      const std = Math.sqrt(variance);
      const sharpe = std === 0 ? (meanRet === 0 ? 0 : Infinity) : meanRet / std;
      
      // Kelly
      const b = avgLossR === 0 ? (avgWinR > 0 ? Infinity : 0) : (avgWinR / avgLossR); 
      const p = rWinRate;
      const qProb = 1 - p;
      const kellyFull = (b === Infinity) ? p : ((b * p - qProb) / b);
      const kellyHalf = kellyFull / 2;


      // --- C. Métricas de Cuenta (TWRR) (v2.3 - Corregido) ---
let equitySeries = [];
let drawdownSeries = []; // Para curva submarina
let periodReturns = [];
const firstTx = filteredTxs[0];
const firstTime = new Date(firstTx.timestamp).getTime();
const lastTime = new Date(filteredTxs[filteredTxs.length - 1].timestamp).getTime();
const daysInPeriod = Math.max(1, (lastTime - firstTime) / (1000 * 60 * 60 * 24));

let startBalance = firstTx.balance_after - firstTx.amount;
equitySeries.push(startBalance); 
drawdownSeries.push(0);

let periodStartBalance;
let peakEquity;
let txsToLoop; 

if (firstTx.type === 'deposit' || firstTx.type === 'withdrawal') {
    periodStartBalance = firstTx.balance_after;
    peakEquity = firstTx.balance_after;
    if(startBalance !== firstTx.balance_after) { 
      equitySeries.push(firstTx.balance_after); 
      drawdownSeries.push(0);
    }
    txsToLoop = filteredTxs.slice(1); 
} else {
    periodStartBalance = startBalance;
    peakEquity = startBalance;
    txsToLoop = filteredTxs; 
}

let currentMaxDD = 0;
let currentValley = peakEquity;

let maxDDUSD = 0;

txsToLoop.forEach(tx => {
    const balance_before_tx = tx.balance_after - tx.amount;
    
    currentValley = Math.min(currentValley, balance_before_tx);
    if (peakEquity > 0) currentMaxDD = Math.max(currentMaxDD, (peakEquity - currentValley) / peakEquity);
    
    maxDDUSD = Math.max(maxDDUSD, peakEquity - currentValley);

    if (tx.type === 'deposit' || tx.type === 'withdrawal') {
        if (periodStartBalance !== 0 && isFinite(periodStartBalance)) { 
            const periodReturn = (balance_before_tx / periodStartBalance) - 1;
            periodReturns.push(periodReturn);
        }
        periodStartBalance = tx.balance_after;
        peakEquity = tx.balance_after; 
        currentValley = tx.balance_after;
        drawdownSeries.push(0); 
    
    } else { 
        if (tx.balance_after > peakEquity) {
            peakEquity = tx.balance_after;
            currentValley = tx.balance_after;
        }
        currentValley = Math.min(currentValley, tx.balance_after); // Actualizar valle después del trade
        if (peakEquity > 0) currentMaxDD = Math.max(currentMaxDD, (peakEquity - currentValley) / peakEquity);
        
        // Calcular Drawdown actual %
        let currentDrawdownPct = (peakEquity > 0) ? ((tx.balance_after - peakEquity) / peakEquity) * 100 : 0;
        drawdownSeries.push(currentDrawdownPct);
    }
    equitySeries.push(tx.balance_after);
});

const finalBalance = equitySeries[equitySeries.length - 1];
if (periodStartBalance !== 0 && isFinite(periodStartBalance)) { 
    const lastPeriodReturn = (finalBalance / periodStartBalance) - 1;
    periodReturns.push(lastPeriodReturn);
}

const totalReturn = (periodReturns.reduce((acc, r) => acc * (1 + r), 1) - 1);
const totalReturnPct = totalReturn * 100;
const maxDDpct = currentMaxDD * 100; 

let annualizedReturn = (Math.pow(1 + totalReturn, 365 / daysInPeriod)) - 1;

// NUEVO: Umbral para períodos cortos (menos de 30 días) y chequeo de finito
if (daysInPeriod < 30 || !isFinite(annualizedReturn)) {
    annualizedReturn = NaN; // O usa totalReturn si preferís no-anualizado: totalReturn
}

// NUEVO: Manejo extra para MaxDD muy bajo
const calmar = (maxDDpct === 0 || maxDDpct < 0.01 || !isFinite(annualizedReturn)) 
    ? NaN 
    : (annualizedReturn * 100 / maxDDpct);

      // --- D. Actualizar Equity Total (Cabecera) (Sin Cambios) ---
      if (allTransactions.length > 0) {
        const lastGlobalTx = allTransactions[allTransactions.length - 1];
        const globalFinalBalance = lastGlobalTx.balance_after;
        const firstGlobalTx = allTransactions[0];
        const globalStartBalance = firstGlobalTx.balance_after - firstGlobalTx.amount;
        updateEquityDisplay(globalFinalBalance, globalStartBalance);
      } else {
        updateEquityDisplay(0, 0);
      }

      // --- E. Preparar datos para gráficos (Sin Cambios) ---
      const tradesForCharts = tradesWithRisk.map((tx, i) => ({
          profitUSD: tx.amount,
          profitPct: tx.risk_r !== 0 ? (tx.amount / tx.risk_r) * 100 : 0, 
          profitR: r_multiples[i], 
          date: tx.timestamp.split(' ')[0]
      }));
      
      // Calcular PnL por Día de la Semana
      const dayPnL = {0:0, 1:0, 2:0, 3:0, 4:0, 5:0, 6:0};
      allTradesInPeriod.forEach(tx => {
          const d = new Date(tx.timestamp).getDay();
          dayPnL[d] += tx.amount;
      });

      // Calcular Long vs Short usando 'buysell_type' de la BD
      let longPnL = 0, shortPnL = 0;
      allTradesInPeriod.forEach(tx => {
          // Verificamos el nuevo campo que viene de la base de datos
          if (tx.buysell_type === 'buy') {
              longPnL += tx.amount;
          } else if (tx.buysell_type === 'sell') {
              shortPnL += tx.amount;
          }
      });
      
      // --- F. Retornar el objeto de métricas ---
      return {
          // Métricas de P/L ($) - Basadas en N = allTrades_N
          N: allTrades_N,
          totalWins: `${dollarWins.length} / ${allTrades_N}`,
          winRate: dollarWinRate, 
          expectancy: expectancy, 
          profitFactor: profitFactor,
          avgWinLossRatio: avgWinLossRatio,
          
          // Métricas de Cuenta (TWRR)
          totalReturnPct: totalReturnPct, 
          maxDDpct: maxDDpct, 
          calmar: calmar, 
          equitySeries: equitySeries,
          drawdownSeries: drawdownSeries,
          
          // Métricas de Riesgo (R) - Basadas en N = N_risk
          sharpe: sharpe, 
          kellyFull: kellyFull, 
          kellyHalf: kellyHalf, 
          expectancyR: expectancyR, 
          
          tradesForCharts: tradesForCharts,
          dayPnL: dayPnL,
          longPnL: longPnL,
          shortPnL: shortPnL,
          
          totalNetProfit: totalNetProfit,
          maxDDUSD: maxDDUSD
      };
    };


    /**
     * renderTradesTable (Sin Cambios)
     */
    const renderTradesTable = function() {
        const tbody = q('tradesTable').querySelector('tbody');
        const thead = q('tradesTable').querySelector('thead');
        
        if (!tbody || !thead) return;
        tbody.innerHTML = '';
        
        thead.innerHTML = `
            <tr>
                <th>Ticket</th>
                <th>Fecha/Hora</th>
                <th>Tipo</th>
                <th>Símbolo</th>
                <th>Monto ($)</th>
                <th>Balance ($)</th>
                <th>Riesgo ($)</th>
                <th>R-Múltiple</th>
            </tr>`;

        const tradesToRender = filterTransactionsByPeriod(allTransactions, currentPeriod);

        tradesToRender.forEach((tx) => {
            const trEl = document.createElement('tr');
            
            let amountCellHtml;
            if (tx.amount > 0) {
                amountCellHtml = `<span style="color:var(--accent);">$${fmt(tx.amount, 2)}</span>`;
            } else if (tx.amount < 0) {
                amountCellHtml = `<span style="color:var(--danger);">$${fmt(tx.amount, 2)}</span>`;
            } else {
                amountCellHtml = `<span>$${fmt(tx.amount, 2)}</span>`;
            }

            const typeDisplay = tx.type.charAt(0).toUpperCase() + tx.type.slice(1);
            
            let riskHtml = '—';
            let rMultipleHtml = '—';
            
            if (tx.type === 'trade' && tx.risk_r > 0) {
                riskHtml = `$${fmt(tx.risk_r, 2)}`;
                const rMultiple = tx.amount / tx.risk_r;
                const rColor = rMultiple > 0 ? 'var(--accent)' : 'var(--danger)';
                rMultipleHtml = `<span style="color:${rColor}">${fmt(rMultiple, 2)} R</span>`;
            }

            trEl.innerHTML = `
                <td>${tx.ticket}</td>
                <td>${tx.timestamp}</td>
                <td>${typeDisplay}</td>
                <td>${tx.symbol || '—'}</td>
                <td>${amountCellHtml}</td>
                <td>${fmt(tx.balance_after, 2)}</td>
                <td>${riskHtml}</td>
                <td>${rMultipleHtml}</td>
            `;
            tbody.appendChild(trEl);
        });
    };


    /**
     * renderEquityChart (Sin Cambios)
     */
    const renderEquityChart = function(equitySeries) {
        const equityChartEl = q('equityChart');
        if (!equityChartEl) return;

        if (myChart) {
            myChart.destroy();
        }

        if (equitySeries && equitySeries.length > 1) {
            const labels = equitySeries.map((_, i) => `Tx ${i}`); 

            const data = {
                labels: labels,
                datasets: [{
                           label: "Equity",
                           data: equitySeries,
                           borderColor: "#3b82f6",
                           backgroundColor: "rgba(59, 130, 246, 0.12)",
                           fill: true,
                           tension: 0.3,                 // Suaviza la línea igual que la otra
                            borderWidth: 2,               // Grosor de línea 2
                            pointRadius: 3,               // Muestra los puntos
                            pointBackgroundColor: "#3b82f6", // Color del punto (Azul)
                            pointHoverRadius: 5, 
                            pointHitRadius: 10, // Efecto al pasar el mouse
                        }]

            };

            const config = {
                type: 'line',
                data: data,
                options: {
                    
                    interaction: {
                        mode: 'point',
                        intersect: true
                    },

                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            bodyColor: COLOR_TEXT_WHITE,
                            titleColor: COLOR_TEXT_WHITE,
                            backgroundColor: COLOR_CARD_DARK,
                            callbacks: {
                                label: function(context) {
                                    return `Equity: USD ${context.parsed.y.toFixed(2)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: { 
                            display: true,
                            offset: false,
                            grid: { 
                                display: true,
                                color: 'rgba(255, 255, 255, 0.1)'          // ← Líneas verticales en gris muted
                            },
                            ticks: { 
                                color: COLOR_MUTED          // ← Números del eje X también en gris (por si los mostrás)
                            }
                        },
                        y: {
                            title: { 
                                display: true,
                                color: COLOR_MUTED          // ← Título del eje Y en gris
                            },
                            ticks: { 
                                color: COLOR_MUTED 
                            },
                            grid: { 
                                color: 'rgba(255, 255, 255, 0.1)'          // ← Líneas horizontales en gris muted
                            }
                        }
                    }
                },
            };

            myChart = new Chart(equityChartEl, config);

        } else {
            const ctx = equityChartEl.getContext('2d');
            ctx.clearRect(0, 0, equityChartEl.width, equityChartEl.height);
            ctx.font = '14px Inter';
            ctx.textAlign = 'center';
            ctx.fillStyle = '#9aa4b2';
            ctx.fillText('Sin datos', equityChartEl.width / 2, equityChartEl.height / 2);
        }
    };


    /**
     * renderMetrics (¡CORREGIDO!)
     */
    const renderMetrics = function(m) {
      
      const metricsIds = ['winRate', 'expectancy', 'profitFactor', 'sharpe', 'kellyFull', 'kellyHalf', 'maxDD', 'calmar', 'totalReturn', 'rCorrected', 'avgWinLossRatio', 'dayWins'];

      if (!m) {
        metricsIds.forEach(id => { 
            const el = q(id);
            if (el) el.innerText = '—';
        });
        const trf = q('totalReturn_footer');
        const rcf = q('rCorrected_footer');
        const tt = q('totalTrades');
        if (trf) trf.innerText = '—';
        if (rcf) rcf.innerText = '—';
        if (tt) tt.innerText = '0';
        
        renderEquityChart(null); 
        return;
      }
      
      q('winRate').innerText = fmt(m.winRate * 100, 2) + '%';
      q('expectancy').innerText = fmt(m.expectancy, 2) + ' $';
      q('profitFactor').innerText = (m.profitFactor === Infinity) ? '∞' : fmt(m.profitFactor, 3);
      q('sharpe').innerText = (m.sharpe === Infinity) ? '∞' : (isNaN(m.sharpe) ? '—' : fmt(m.sharpe, 3));
      q('kellyFull').innerText = (isFinite(m.kellyFull) ? fmt(m.kellyFull * 100, 3) + '%' : '—');
      q('kellyHalf').innerText = (isFinite(m.kellyHalf) ? fmt(m.kellyHalf * 100, 3) + '%' : '—');
      q('maxDD').innerText = (isNaN(m.maxDDpct) ? '—' : fmt(m.maxDDpct, 2) + '%');
      q('calmar').innerText = (isFinite(m.calmar) ? fmt(m.calmar, 3) : '—');
      q('totalReturn').innerText = fmt(m.totalReturnPct, 2) + '%';
      q('rCorrected').innerText = fmt(m.expectancyR, 3) + ' R';
      
      q('totalReturn_footer').innerText = fmt(m.totalReturnPct, 2) + '%';
      q('rCorrected_footer').innerText = fmt(m.expectancyR, 3) + ' R'; 
      
      q('dayWins').innerText = m.totalWins;
      q('avgWinLossRatio').innerText = (m.avgWinLossRatio === Infinity) ? '∞' : fmt(m.avgWinLossRatio, 3);
      q('totalTrades').innerText = m.N;

      renderEquityChart(m.equitySeries);
    };

    // =======================================================
    // === FUNCIONES DE GRÁFICOS (Sin Cambios) ===
    // =======================================================
    
    const calculateDistribution = function(trades, binSize = 10.0) { 
        const distribution = {};
        if (!trades) return { labels: [], data: [] }; 
        trades.forEach(trade => {
            const pnl = trade.profitPct; 
            if (pnl !== null && !isNaN(pnl) && isFinite(pnl)) {
                const bin = Math.floor(pnl / binSize) * binSize;
                distribution[bin] = (distribution[bin] || 0) + 1;
            }
        });
        const sortedBins = Object.keys(distribution).map(Number).sort((a, b) => a - b);
        const labels = sortedBins.map(bin => `${bin.toFixed(0)}%`); 
        const data = sortedBins.map(bin => distribution[bin]);
        return { labels, data };
    };

    const renderDistributionChart = function(trades) {
        const ctx = document.getElementById('pnlDistributionChart');
        if (!ctx) return;
        if (pnlDistributionChartInstance) pnlDistributionChartInstance.destroy(); 
        
        const distributionData = calculateDistribution(trades); 

        pnlDistributionChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: distributionData.labels,
                datasets: [{
                    label: 'Frecuencia de Trades',
                    data: distributionData.data,
                    backgroundColor: (context) => {
                        if (!distributionData.labels[context.dataIndex]) return COLOR_ACCENT_RGBA;
                        const binLabel = distributionData.labels[context.dataIndex].replace('%', '');
                        const binValue = parseFloat(binLabel);
                        return binValue >= 0 ? COLOR_ACCENT_RGBA : COLOR_DANGER_RGBA; 
                    },
                    borderColor: (context) => {
                        if (!distributionData.labels[context.dataIndex]) return COLOR_ACCENT_HEX;
                        const binLabel = distributionData.labels[context.dataIndex].replace('%', '');
                        const binValue = parseFloat(binLabel);
                        return binValue >= 0 ? COLOR_ACCENT_HEX : COLOR_DANGER_HEX; 
                    },
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { 
                        title: { display: true, text: 'Rango de PnL (% de R)', color: COLOR_MUTED },
                        ticks: { color: COLOR_MUTED },
                        grid: { color: 'rgba(255,255,255,0.05)' }
                    },
                    y: { 
                        title: { display: true, text: 'Número de Trades', color: COLOR_MUTED }, 
                        beginAtZero: true,
                        ticks: { color: COLOR_MUTED },
                        grid: { color: 'rgba(255,255,255,0.05)' }
                    }
                },
                plugins: { 
                    legend: { display: false },
                    tooltip: { bodyColor: COLOR_TEXT_WHITE, titleColor: COLOR_TEXT_WHITE, backgroundColor: COLOR_CARD_DARK }
                }
            }
        });
    };

    const renderDayOfWeekChart = function(dayPnL) {
        const ctx = document.getElementById('dayOfWeekChart');
        if (!ctx) return;
        if (dayOfWeekChartInstance) dayOfWeekChartInstance.destroy();
        
        const labels = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        const data = [
            dayPnL[0], // Domingo
            dayPnL[1], // Lunes
            dayPnL[2], // Martes
            dayPnL[3], // Miércoles
            dayPnL[4], // Jueves
            dayPnL[5], // Viernes
            dayPnL[6]  // Sábado
        ];

        dayOfWeekChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'PnL ($)',
                    data: data,
                    backgroundColor: data.map(v => v >= 0 ? COLOR_ACCENT_RGBA : COLOR_DANGER_RGBA),
                    borderColor: data.map(v => v >= 0 ? COLOR_ACCENT_HEX : COLOR_DANGER_HEX),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { 
                        title: { display: true, text: 'Rango de PnL (Perdida o Ganancia diaria acumulada)', color: COLOR_MUTED },
                        ticks: { color: COLOR_MUTED },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    },
                    y: { 
                        ticks: { color: COLOR_MUTED },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    }
                }
            }
        });
    };

    const renderLongShortChart = function(long, short) {
        const ctx = document.getElementById('longShortChart');
        if (!ctx) return;
        if (longShortChartInstance) longShortChartInstance.destroy();
        
        longShortChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Long (Buy)', 'Short (Sell)'],
                datasets: [{
                    label: 'PnL ($)',
                    data: [long, short],
                    backgroundColor: [
                        long >= 0 ? COLOR_ACCENT_RGBA : COLOR_DANGER_RGBA,
                        short >= 0 ? COLOR_ACCENT_RGBA : COLOR_DANGER_RGBA
                    ],
                    borderColor: [
                        long >= 0 ? COLOR_ACCENT_HEX : COLOR_DANGER_HEX,
                        short >= 0 ? COLOR_ACCENT_HEX : COLOR_DANGER_HEX
                    ],
                    borderWidth: 1,
                    barThickness: 75, 
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
            x: { 
                title: { display: true, text: 'Total de Long vs Short', color: COLOR_MUTED },
                ticks: { color: COLOR_MUTED },
                grid: { color: 'rgba(255, 255, 255, 0.1)' }
            },
            y: { 
                ticks: { color: COLOR_MUTED },
                grid: { color: 'rgba(255, 255, 255, 0.1)' }
            }
        }

            }
        });
    };

    const renderDrawdownChart = function(ddSeries) {
        const ctx = document.getElementById('drawdownChart');
        if (!ctx) return;
        if (drawdownChartInstance) drawdownChartInstance.destroy();
        
        // Filtrar para mostrar solo los puntos de drawdown (aunque ddSeries ya tiene valores para cada tx)
        const labels = ddSeries.map((_, i) => i); // O usar fechas si estuvieran disponibles

        drawdownChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Drawdown %',
                    data: ddSeries,
                    borderColor: COLOR_DANGER_HEX,
                    backgroundColor: 'rgba(255, 107, 107, 0.2)', // Área roja semitransparente
                    fill: true,
                    borderWidth: 2,              
                    pointRadius: 3,             
                    pointBackgroundColor: COLOR_DANGER_HEX, 
                    pointHoverRadius: 5,        
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { 
                        title: { display: true, text: 'Perdida maxima (% de la Cuenta)', color: COLOR_MUTED },
                        ticks: { color: COLOR_MUTED },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        display: true },
                    y: { 
                            ticks: { color: COLOR_MUTED },
                            grid: { color: 'rgba(255, 255, 255, 0.1)' },
                            suggestedMin: Math.min(...ddSeries) * 1.1,
                            max: 0
                        }

                }
            }
        });
    };

    const renderWinLossPieChart = function(m) {
        const ctx = document.getElementById('winLossPieChart');
        if (!ctx) return;
        if (winLossPieChartInstance) winLossPieChartInstance.destroy(); 

        const winRate = m.winRate * 100;
        const lossRate = (1 - m.winRate) * 100;

        winLossPieChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Ganadoras', 'Perdedoras'],
                datasets: [{
                    data: [winRate, lossRate],
                    backgroundColor: [COLOR_ACCENT_HEX, COLOR_DANGER_HEX],
                    hoverOffset: 4,
                    borderColor: COLOR_BG_DARK
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'bottom',
                        labels: { color: COLOR_TEXT_WHITE } 
                    },
                    tooltip: { bodyColor: COLOR_TEXT_WHITE, titleColor: COLOR_TEXT_WHITE, backgroundColor: COLOR_CARD_DARK }
                }
            }
        });
    };

    const renderRScatterChart = function(trades) {
        const ctx = document.getElementById('rFactorScatterChart');
        if (!ctx) return;
        if (rFactorScatterChartInstance) rFactorScatterChartInstance.destroy(); 
        
        if (!trades) return; 

        const rData = trades.map(t => ({
                x: t.profitR,
                y: t.profitUSD,
                isWin: t.profitUSD > 0
            })).filter(t => isFinite(t.x) && isFinite(t.y));
            
        const winsData = rData.filter(d => d.isWin);
        const lossesData = rData.filter(d => !d.isWin);

        rFactorScatterChartInstance = new Chart(ctx, {
            type: 'scatter',
            data: {
                datasets: [{
                    label: 'Ganadoras',
                    data: winsData,
                    backgroundColor: COLOR_ACCENT_HEX, 
                    pointRadius: 4,
                }, {
                    label: 'Perdedoras',
                    data: lossesData,
                    backgroundColor: COLOR_DANGER_HEX,
                    pointRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        type: 'linear',
                        title: { display: true, text: 'Factor R (Múltiples de riesgo)', color: COLOR_MUTED },
                        ticks: { color: COLOR_MUTED },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    },
                    y: {
                        type: 'linear',
                        title: { display: true, text: 'PnL (USD)', color: COLOR_MUTED },
                        beginAtZero: false,
                        ticks: { color: COLOR_MUTED },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    }
                },
                plugins: {
                    legend: { labels: { color: COLOR_MUTED } },
                    tooltip: {
                        bodyColor: COLOR_TEXT_WHITE, titleColor: COLOR_TEXT_WHITE, backgroundColor: COLOR_CARD_DARK,
                        callbacks: {
                            label: (context) => (context.dataset.label === 'Ganadoras' ? 'Ganadora' : 'Perdedora') + `: R: ${context.parsed.x.toFixed(2)}, PnL: $${context.parsed.y.toFixed(2)}`
                        }
                    }
                }
            }
        });
    };

    const calculateMonthlyPnl = function(trades) {
        const monthlyPnl = {};
        
        if (!trades) return { labels: [], data: [] }; 

        trades
            .filter(t => t.date && t.profitUSD !== null && !isNaN(t.profitUSD))
            .forEach(trade => {
                const date = new Date(trade.date + 'T00:00:00');
                const key = `${date.getFullYear()}-${(date.getMonth() + 1).toString().padStart(2, '0')}`;
                monthlyPnl[key] = (monthlyPnl[key] || 0) + trade.profitUSD;
            });

        const sortedMonths = Object.keys(monthlyPnl).sort();
        const labels = sortedMonths.map(key => {
            const [year, month] = key.split('-');
            const monthName = new Date(year, parseInt(month) - 1).toLocaleString('es-ES', { month: 'short' });
            return `${monthName.toUpperCase()}-${year.slice(2)}`;
        });

        let cumulativePnl = 0;
        const data = sortedMonths.map(key => {
            cumulativePnl += monthlyPnl[key];
            return cumulativePnl;
        });

        return { labels, data };
    };

    const renderMonthlyPnlChart = function(trades) {
        const ctx = document.getElementById('monthlyPnlChart');
        if (!ctx) return;
        if (monthlyPnlChartInstance) monthlyPnlChartInstance.destroy(); 

        const monthlyData = calculateMonthlyPnl(trades);

        monthlyPnlChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyData.labels,
                datasets: [{
                    label: 'PnL Acumulado Mensual ($)',
                    data: monthlyData.data,
                    borderColor: "#3b82f6",
                    backgroundColor: "rgba(59, 130, 246, 0.12)",
                    fill: true,
                    tension: 0.1
                }]
            },
            options: { scales: {
            x: {
                title: { display: true, text: 'PnL Mensual de la cuenta', color: COLOR_MUTED },
                ticks: { color: COLOR_MUTED },
                grid: { color: 'rgba(255, 255, 255, 0.1)' }
            },
            y: {
                ticks: { color: COLOR_MUTED },
                title: { display: true, color: COLOR_MUTED },
                grid: { color: 'rgba(255, 255, 255, 0.1)' }
            }
        }
         }
        });
    };

    const renderRadarChart = function(m) {
    if (!m) return; // Sin datos? Salta
    const ctx = document.getElementById('radarChart')?.getContext('2d');
    if (!ctx) return; // Canvas no encontrado? Salta

    // Extraer métricas
    const winRatePct = m.winRate * 100;
    const profitFactor = m.profitFactor;
    const sharpe = m.sharpe;
    const expectancyR = m.expectancyR; // Cambiado a R para neutralidad
    const recoveryFactor = Math.abs(m.totalNetProfit / (m.maxDDUSD || 1)); // Evita división por 0

    // NUEVO: Normalización con chequeos de finito
    const radarData = [
        isFinite(winRatePct) ? Math.min(winRatePct, 100) : 0,
        isFinite(profitFactor) ? Math.min((profitFactor / 3) * 100, 100) : 0,
        isFinite(sharpe) ? Math.min((sharpe / 3) * 100, 100) : 0,
        isFinite(recoveryFactor) ? Math.min((recoveryFactor / 5) * 100, 100) : 0,
        isFinite(expectancyR) ? Math.min((expectancyR / 0.5) * 100, 100) : 0  // Ahora usa expectancyR, umbral 0.5R=100
    ];

    // Destruir si existe
    if (window.myRadarChart) window.myRadarChart.destroy();

    window.myRadarChart = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: ['Win Rate %', 'Profit Factor', 'Sharpe Ratio', 'Recovery Factor', 'Expectancy R'], // Cambiado label a R
            datasets: [{
                label: 'Fortalezas/Debilidades',
                data: radarData,
                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                borderColor: '#3b82f6',
                borderWidth: 2,
                pointBackgroundColor: '#3b82f6'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Perfil de Rendimiento y Fortalezas/Debilidades',
                    color: COLOR_MUTED,
                    font: { size: 12 },
                    padding: { top: 10, bottom: 10 }
                },
                legend: { display: false },
                // NUEVO: Tooltips con valores raw (originales sin normalizar)
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const rawValues = [winRatePct, profitFactor, sharpe, recoveryFactor, expectancyR];
                            const raw = rawValues[context.dataIndex];
                            return `${context.label}: ${isFinite(raw) ? raw.toFixed(2) : '—'} (normalizado: ${context.raw})`;
                        }
                    }
                }
            },
            scales: {
                r: {
                    angleLines: { color: 'rgba(255, 255, 255, 0.1)' },
                    grid: { color: 'rgba(255, 255, 255, 0.1)' },
                    pointLabels: { color: COLOR_MUTED, font: { size: 12 } },
                    ticks: {
                        display: false,
                        color: COLOR_MUTED,
                        font: { size: 14 }
                    },
                    suggestedMin: 0,
                    suggestedMax: 100
                }
            }
        }
    });
};
    
    // --- MODIFICADO --- 
    // Llama a todos los gráficos, ya no existe 'renderActiveCharts'
    const renderAll = function() {
      const m = calcMetrics();
      renderMetrics(m); 
      renderTradesTable(); 
      if (m) {
          renderDistributionChart(m.tradesForCharts);
          renderWinLossPieChart(m);
          renderRScatterChart(m.tradesForCharts); 
          renderMonthlyPnlChart(m.tradesForCharts);
          // NUEVOS GRÁFICOS
          renderDayOfWeekChart(m.dayPnL);
          renderLongShortChart(m.longPnL, m.shortPnL);
          renderDrawdownChart(m.drawdownSeries);
          renderRadarChart(m);
      }
    };


    // =======================================================
    // --- LÓGICA DE INICIALIZACIÓN (MODIFICADA) ---
    // =======================================================
    
    // Cargamos cuentas y transacciones iniciales
    const accounts = await loadAccounts();
    const accountSelector = q('accountSelector');
    if (accountSelector) {
      if (accounts.length === 0) {
        accountSelector.innerHTML = '<option value="">No hay cuentas disponibles</option>';
        allTransactions = [];
        renderAll();
      } else {
        accountSelector.innerHTML = accounts.map(acc => `<option value="${acc}">${acc}</option>`).join('');
        currentAccount = accounts[0];
        accountSelector.value = currentAccount;
        allTransactions = await loadTransactions(currentAccount);
        renderAll();

        accountSelector.addEventListener('change', async (event) => {
            currentAccount = event.target.value;
            allTransactions = await loadTransactions(currentAccount);
            renderAll();
        });
      }
    } else {
      // Si no hay selector (fallback, aunque lo agregamos)
      allTransactions = await loadTransactions();
      renderAll();
    }
    
    // --- Lógica de Cambiar Contraseña (Sin Cambios) ---
    const passForm = q('changePasswordForm');
    if (passForm) {
        passForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const current = q('current_password').value;
            const newPass = q('new_password').value;
            const confirmPass = q('confirm_password').value;
            const messageEl = q('passwordMessage');
            if (newPass !== confirmPass) {
                messageEl.textContent = 'Las nuevas contraseñas no coinciden.';
                messageEl.style.color = 'var(--danger)';
                return;
            }
            try {
                const response = await fetch('api/change_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ current: current, new: newPass, confirm: confirmPass })
                });
                const result = await response.json();
                if (result.success) {
                    messageEl.textContent = result.message;
                    messageEl.style.color = 'var(--accent)';
                    q('current_password').value = '';
                    q('new_password').value = '';
                    q('confirm_password').value = '';
                } else {
                    messageEl.textContent = result.message;
                    messageEl.style.color = 'var(--danger)';
                }
            } catch (err) {
                messageEl.textContent = 'Error de conexión.';
                messageEl.style.color = 'var(--danger)';
            }
        });
    }

    // =======================================================
    // --- EVENT LISTENERS RESTANTES (Modificados) ---
    // =======================================================

    const recalcBtn = q('recalcBtn');
    if (recalcBtn) {
      recalcBtn.style.display = 'none'; // Ocultar
    }
    
    const periodSelector = q('periodSelector');
    if (periodSelector) {
        periodSelector.addEventListener('change', (event) => {
            currentPeriod = event.target.value;
            renderAll();
        });
    }

    const sortSelector = q('sortSelector');
    if (sortSelector) {
        sortSelector.style.display = 'none'; // Ocultar
    }

    // --- Calculadora de Posición ELIMINADA ---
    
    // =======================================================
    // --- NUEVO Ticker de Precios (Lado del Servidor) ---
    // =======================================================
    
    function formatPrice(price, symbol) {
        const dec = symbol.endsWith('JPY') ? 3 : 5;
        return parseFloat(price).toFixed(dec);
    }

    function showPrices(prices, prevPrices) {
    const ticker = document.getElementById('ticker');
    if (!ticker) return;
    ticker.innerHTML = ''; // Limpiamos para reconstruir con lo que venga del Excel

    for (const symbol in prices) {
        const currentPrice = prices[symbol];
        const prevPrice = prevPrices[symbol] || currentPrice;
        const diff = currentPrice - prevPrice;
        const isUp = diff >= 0;
        let changeLabel = "";

        // --- LÓGICA INTELIGENTE DE CÁLCULO ---
        
        // 1. COMMODITIES, CRIPTOS E ÍNDICES (Mostramos Porcentaje %)
        // Detecta automáticamente: GLD, SLV, OIL, GAS, BTC, ETH, NAS, US30, SPX, XAU
        const usePercentage = ['GLD','SLV','OIL','GAS','BTC','ETH','NAS','US30','SPX','XAU','XAG']
            .some(keyword => symbol.toUpperCase().includes(keyword));

        if (usePercentage) {
            const percent = prevPrice !== 0 ? ((diff / prevPrice) * 100).toFixed(2) : "0.00";
            changeLabel = `${isUp ? '▲' : '▼'} ${Math.abs(percent)}%`;
        } 
        // 2. DIVISAS JPY (Multiplicador 100)
        else if (symbol.toUpperCase().includes('JPY')) {
            const pips = (diff * 100).toFixed(1);
            changeLabel = `${isUp ? '▲' : '▼'} ${Math.abs(pips)} pips`;
        } 
        // 3. DIVISAS ESTÁNDAR (Multiplicador 10000)
        else {
            const pips = (diff * 10000).toFixed(1);
            changeLabel = `${isUp ? '▲' : '▼'} ${Math.abs(pips)} pips`;
        }

        // --- CREACIÓN DINÁMICA DEL HTML ---
        const item = document.createElement('div');
        item.className = `ticker-item ${isUp ? 'up' : 'down'}`;
        item.innerHTML = `
            <span class="label">${symbol}:</span>
            <span class="price">${currentPrice.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 5})}</span>
            <span class="change" style="color: ${isUp ? '#0ea600' : '#d04040'}; margin-left:5px; font-size:12px;">
                ${changeLabel}
            </span>
        `;
        ticker.appendChild(item);
    }
}

    async function fetchTickerData() {
        try {
            const response = await fetch(API_URL + 'get_ticker_prices.php');
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            if (data && data.prices) {
                console.log("✅ Precios de Ticker cargados desde el servidor.");
                showPrices(data.prices, data.previousPrices || {});
            } else {
                console.warn("Respuesta de Ticker vacía desde el servidor.");
            }
        } catch (e) {
            console.error('Error al cargar datos del Ticker:', e);
        }
    }
    
    if (q('ticker')) {
        fetchTickerData(); // Cargar al inicio
        setInterval(fetchTickerData, 60 * 1000 * 15); // Recargar cada 15 minutos
    }

    // --- Fecha y Hora (Sin Cambios) ---
    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
        const formattedDateTime = now.toLocaleDateString('es-ES', options).replace(',', '');
        const datetimeDiv = document.getElementById('datetime');
        if (datetimeDiv) {
            datetimeDiv.textContent = formattedDateTime;
        }
    }
    setInterval(updateDateTime, 1000);
    updateDateTime();

    // --- Lógica para alternar las cards (ELIMINADA) ---
    const showMetricsBtn = q('showMetricsBtn');
    const showTableBtn = q('showTableBtn');
    if(showMetricsBtn) showMetricsBtn.style.display = 'none';
    if(showTableBtn) showTableBtn.style.display = 'none';

    // --- NUEVA LÓGICA PARA OCULTAR/MOSTRAR TABLA DE TRADES ---
    const toggleTableBtn = document.getElementById('toggleTableBtn');
    const tableContainer = document.getElementById('tableContainer');
    
    if (toggleTableBtn && tableContainer) {
        toggleTableBtn.addEventListener('click', () => {
            if (tableContainer.style.display === 'none') {
                tableContainer.style.display = 'block';
                toggleTableBtn.innerText = 'Ocultar Tabla de Trades';
            } else {
                tableContainer.style.display = 'none';
                toggleTableBtn.innerText = 'Mostrar Tabla de Trades';
            }
        });
    }

    
  })(); // Fin del IIFE async
});

</script>
</body>
</html>