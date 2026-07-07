<?php
require_once __DIR__ . '/api/session_bootstrap.php';
start_secure_session();

// 1. Seguridad: Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];

// 2. Obtener Token MT5
require_once 'api/db_connect.php'; 
$stmt = $conn->prepare("SELECT mt5_token FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$user_mt5_token = $user_data['mt5_token'] ?? 'No asignado';
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-F4G8QC48');</script>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diario | Trade Metrics</title>
    <link rel="icon" type="image/png" href="logo3.png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* --- ESTILOS GLOBALES --- */
        :root { 
            --bg: #0f1724; 
            --card: #161a22; 
            --muted: #9aa4b2; 
            --accent: #0ea600; 
            --danger: #d04040; 
            --text: #e6eef6; 
        }
        
        body { 
            background: linear-gradient(180deg, #192731 0%, #192731 100%); 
            color: var(--text); 
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, sans-serif; 
            padding-left: 80px; /* Espacio para sidebar */
            margin: 0; 
            overflow-x: hidden; /* Evita scroll horizontal global */
        }

        /* --- BARRA LATERAL (Sidebar) --- */
        .sidebar { 
            position: fixed; top: 0; left: 0; height: 100%; width: 80px; 
            background: var(--card); border-right: 1px solid rgba(255,255,255,0.05); 
            display: flex; flex-direction: column; align-items: center; 
            padding: 20px 0; z-index: 2000; box-shadow: 4px 0 15px rgba(0,0,0,0.3); 
        }
        .sidebar-logo { 
            margin-bottom: 40px; width: 40px; height: 40px; 
            background-image: url('logo5.png'); background-size: cover; 
            border-radius: 50%; opacity: 0.8; 
        }
        .nav-item { 
            width: 50px; height: 50px; border-radius: 12px; 
            background: transparent; border: none; color: var(--muted); 
            cursor: pointer; display: flex; align-items: center; justify-content: center; 
            margin-bottom: 15px; transition: all 0.3s ease; text-decoration: none; position: relative;
        }
        .nav-item:hover { background: rgba(255,255,255,0.05); color: var(--text); }
        .nav-item.active { background: var(--accent); color: #012322; box-shadow: 0 0 10px #16ff0045}
        .nav-item svg { width: 24px; height: 24px; stroke-width: 2; }
        
        .nav-item::after { 
            content: attr(data-tooltip); position: absolute; left: 60px; 
            background: var(--bg); color: #fff; padding: 5px 10px; 
            border-radius: 5px; font-size: 12px; opacity: 0; visibility: hidden; 
            transition: 0.2s; white-space: nowrap; pointer-events: none; 
            border: 1px solid rgba(255,255,255,0.1); z-index: 2001; 
        }
        .nav-item:hover::after { opacity: 1; visibility: visible; }
        .nav-item.logout {margin-bottom: 45px; color: var(--danger); }
        .nav-item.logout:hover { background: rgba(255, 107, 107, 0.15); }

        /* --- TARJETAS CALENDARIO --- */
        .day-card { 
            border: 1px solid rgba(255,255,255,0.05); 
            transition: all 0.2s ease; 
            min-height: 120px;          /* Más compacto para caber sin scroll */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 0.8rem;            /* Padding reducido */
            border-radius: 0.75rem;
            position: relative;
            overflow: hidden;
            font-size: 0.9rem;          /* Texto más pequeño para más espacio */
        }
        .day-card:hover { 
            transform: translateY(-4px); 
            box-shadow: 0 6px 20px rgba(0,0,0,0.5); 
        }
        
        /* Contenedor principal ajustado al 90% del ancho */
        .calendar-wrapper {
            width: 90%;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        #q_setup {
            background-color: #161a22;
            color: white;
            border: 1px solid rgba(255,255,255,0.1);
            padding: 8px 10px;
            border-radius: 8px;
        }
        
        #q_setup option {
            background-color: #161a22;
            color: white;
            padding: 10px;
        }
        
        /* Opcional: cuando está abierto o seleccionado */
        #q_setup:focus {
            border-color: #0ea600;
            outline: none;
            box-shadow: 0 0 0 2px rgba(14, 166, 0, 0.3);
        }

        /* Grid sin scroll forzado */
        #calendar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); /* Ajuste automático */
            gap: 1rem;
        }

        /* --- MODALES --- */
        .overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            backdrop-filter: blur(8px); background: rgba(0, 0, 0, 0.4);
            opacity: 0; visibility: hidden; transition: all 0.3s ease; z-index: 1000;
        }
        .overlay.active { opacity: 1; visibility: visible; }

        .modal-dark { 
            background-color: var(--card); 
            border: 1px solid rgba(255,255,255,0.1); 
            color: var(--text); 
        }
        .modal-header { background-color: rgba(255,255,255,0.02); border-bottom: 1px solid rgba(255,255,255,0.05); }
        .modal-footer { background-color: rgba(255,255,255,0.02); border-top: 1px solid rgba(255,255,255,0.05); }

        /* Modal Configuración */
        .settings-modal {
            position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.95);
            background: var(--card); padding: 25px; border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.6); width: 90%; max-width: 400px;
            opacity: 0; visibility: hidden; transition: all 0.3s ease; z-index: 1101;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .settings-modal.active { opacity: 1; visibility: visible; transform: translate(-50%, -50%) scale(1); }
        .settings-modal h2 { margin-top: 0; text-align: center; color: #fff; font-size: 22px; margin-bottom: 16px; }
        .settings-modal hr { border: 0; border-top: 1px dashed rgba(255,255,255,0.1); margin: 20px 0; }
        .settings-modal label { display: block; font-size: 13px; color: var(--muted); margin-bottom: 6px; margin-top: 15px;}
        
        input, textarea, select { 
            background-color: rgba(255,255,255,0.03) !important; 
            border: 1px solid rgba(255,255,255,0.1) !important; 
            color: var(--text) !important; 
            width: 100%; padding: 8px 10px; border-radius: 8px;
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
        
        .btn { 
            display: inline-block; padding: 10px; border-radius: 8px; 
            background: var(--accent); color: #012322; border: none; 
            cursor: pointer; font-weight: 600; width: 100%; text-align: center; margin-top: 10px;
        }
        .btn:hover { background: #c7de26; }
        
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: #333; border-radius: 4px; }
        
        .max-w-7xl {
            max-width: 90rem !important;  /* 90rem = 1440px aprox. */
        }

        /* En celular, la sidebar fija de 80px pasa a ser una barra inferior (igual que app.php) */
        @media (max-width: 768px) {
            body { padding-left: 0; padding-bottom: 60px; }
            .sidebar {
                width: 100%; height: 60px; top: auto; bottom: 0;
                flex-direction: row; justify-content: space-around; align-items: center;
                border-right: none; border-top: 1px solid rgba(255,255,255,0.05);
            }
            .sidebar-logo { display: none; }
            /* .nav-item.logout tiene margin-bottom:45px para separarlo en la barra vertical
               de escritorio; hay que igualar el selector para poder pisarlo acá. */
            .nav-item, .nav-item.logout { margin-bottom: 0; }
            .nav-item::after { display: none; }
            .calendar-wrapper { width: 94%; }
        }

    </style>
</head>
<body>
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-F4G8QC48"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<nav class="sidebar">
        <div class="sidebar-logo"></div>
        
        <a href="social.php" class="nav-item" data-tooltip="Red Social">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
        </a>

        <a href="app.php" class="nav-item" data-tooltip="Trade Metrics">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z" /></svg>
        </a>

        <a href="diario.php" class="nav-item active" data-tooltip="Diario">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>
        </a>

        <button class="nav-item logout" id="navLogout" data-tooltip="Cerrar Sesión">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
            </svg>
        </button>
    </nav>

    <div id="overlay" class="overlay"></div>

    <div id="settingsModal" class="settings-modal">
        </div>

    <div class="max-w-7xl mx-auto px-6 py-8 calendar-wrapper">
        <header class="flex flex-col md:flex-row justify-between items-center mb-6 border-b border-white/10 pb-4">
            <div class="mb-4 md:mb-0">
                <h1 class="text-2xl md:text-3xl font-bold text-white">Diario de Emociones</h1>
                <p class="text-[#9aa4b2] text-sm mt-1">Registra tu psicología mes a mes.</p>
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs md:text-sm bg-[#161a22] p-3 rounded-lg border border-white/5">
                <div class="flex items-center"><div class="w-3 h-3 bg-[#0ea600]/30 border border-[#0ea600] rounded-sm mr-2"></div> Ganador</div>
                <div class="flex items-center"><div class="w-3 h-3 bg-[#ff6b6b]/30 border border-[#ff6b6b] rounded-sm mr-2"></div> Perdedor</div>
                <div class="flex items-center"><i class="fa-solid fa-bed text-[#9aa4b2] mr-2"></i> Sin Operar</div>
                <div class="flex items-center"><i class="fa-solid fa-circle-exclamation text-[#ff6b6b] mr-2"></i> Falta Diario</div>
            </div>
        </header>

        <div class="flex justify-between items-center mb-6 bg-[#161a22] p-4 rounded-xl border border-white/5 shadow-lg">
            <button onclick="changeMonth(-1)" class="text-[#9aa4b2] hover:text-[#0ea600] transition-colors p-2">
                <i class="fa-solid fa-chevron-left text-xl"></i>
            </button>
            
            <div class="text-center">
                <h2 id="current-month-display" class="text-xl font-bold text-white uppercase tracking-wider"></h2>
            </div>

            <button onclick="changeMonth(1)" class="text-[#9aa4b2] hover:text-[#0ea600] transition-colors p-2">
                <i class="fa-solid fa-chevron-right text-xl"></i>
            </button>
        </div>

        <div id="calendar-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            <div class="col-span-full text-center text-[#9aa4b2] py-10">
                <i class="fa-solid fa-circle-notch fa-spin text-2xl"></i><br>Cargando datos...
            </div>
        </div>
    </div>

    <div id="entry-modal" class="fixed inset-0 bg-black/70 hidden z-50 flex items-center justify-center p-4 opacity-0 transition-opacity duration-300 backdrop-blur-sm">
        <div class="modal-dark rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col transform scale-95 transition-transform duration-300" id="modal-panel">
            <div class="px-6 py-4 modal-header flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-bold text-white" id="modal-date-title">Registrar Emociones</h2>
                    <p class="text-sm text-[#9aa4b2]" id="modal-stats-subtitle">Trades: 0 | PnL: $0.00</p>
                </div>
                <button onclick="closeModal()" class="text-[#9aa4b2] hover:text-[#ff6b6b] transition-colors"><i class="fa-solid fa-xmark text-2xl"></i></button>
            </div>
            <div class="p-6 overflow-y-auto flex-1 space-y-5">
                <form id="journal-form">
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[#0ea600]">1. ¿Por qué entré en la operación?</label>
                        <textarea id="q_why" rows="2" class="w-full px-3 py-2 rounded-md transition focus:ring-1" placeholder="FOMO, aburrimiento, setup claro..."></textarea>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[#0ea600]">2. ¿Fue mi setup real o inventado?</label>
                        <select id="q_setup" class="w-full px-3 py-2 rounded-md">
                            <option value="">Seleccionar...</option><option value="real">Setup Real (Plan)</option><option value="inventado">Inventado / Forzado</option><option value="seguimiento">Seguimiento de tendencia</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[#0ea600]">3. ¿Cómo me sentí durante la operación?</label>
                        <textarea id="q_feeling" rows="2" class="w-full px-3 py-2 rounded-md transition focus:ring-1" placeholder="Ansioso, confiado, con miedo..."></textarea>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[#0ea600]">4. ¿Qué haría distinto mañana?</label>
                        <textarea id="q_different" rows="2" class="w-full px-3 py-2 rounded-md transition focus:ring-1" placeholder="Esperar confirmación, salir antes..."></textarea>
                    </div>
                    <div class="mt-4 pt-4 border-t border-white/5">
                        <label class="block text-sm font-semibold text-[#9aa4b2] mb-2">Comentarios Adicionales</label>
                        <textarea id="q_comments" rows="3" class="w-full px-3 py-2 rounded-md transition focus:ring-1" placeholder="Notas extra..."></textarea>
                    </div>
                    <input type="hidden" id="current_date_iso">
                </form>
            </div>
            <div class="px-6 py-4 modal-footer flex justify-end space-x-3">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-transparent border border-white/10 text-[#9aa4b2] rounded-lg hover:bg-white/5 transition">Cancelar</button>
                <button type="button" onclick="saveJournalEntry()" id="btn-save" class="px-6 py-2 bg-[#0ea600] text-[#012322] font-bold rounded-lg hover:bg-[#10c900] hover:shadow-xl hover:shadow-[#0ea600]/40 transition flex items-center shadow-lg shadow-[#0ea600]/20">
                    <i class="fa-solid fa-save mr-2"></i> Guardar
                </button>
            </div>
        </div>
    </div>

    <script>
        // --- Gestión de Sesión y Navegación Básica ---
        const navLogoutBtn = document.getElementById('navLogout');
        if (navLogoutBtn) {
            navLogoutBtn.addEventListener('click', () => {
                if(confirm('¿Estás seguro que quieres cerrar sesión?')) {
                    window.location.href = 'api/logout.php';
                }
            });
        }
        
        // --- Variables de Estado para el Calendario ---
        const API_URL = 'api/api_diario.php';
        let tradesData = {};
        let journalData = {};
        
        // Iniciamos en el mes actual
        let viewDate = new Date(); 
        
        // --- Funciones Principales ---

        function changeMonth(offset) {
            viewDate.setMonth(viewDate.getMonth() + offset);
            loadData();
        }

        async function loadData() {
            const month = viewDate.getMonth() + 1;
            const year = viewDate.getFullYear();
            
            updateHeaderDisplay();

            const grid = document.getElementById('calendar-grid');
            grid.innerHTML = '<div class="col-span-full text-center text-[#9aa4b2] py-10"><i class="fa-solid fa-circle-notch fa-spin text-2xl"></i><br>Cargando...</div>';

            try {
                const response = await fetch(`${API_URL}?action=get_data&month=${month}&year=${year}`);
                if(!response.ok) throw new Error('Error de red');
                const data = await response.json();
                
                if(data.success) {
                    tradesData = data.trades;
                    journalData = data.journal;
                    renderCalendar();
                } else {
                    if(data.message === 'No has iniciado sesión') window.location.href = 'login.php';
                    else console.error(data.message);
                }
            } catch (error) {
                console.error("Error fetch:", error);
                grid.innerHTML = '<div class="col-span-full text-center text-[#ff6b6b] py-10">Error de conexión</div>';
            }
        }

        function updateHeaderDisplay() {
            const options = { month: 'long', year: 'numeric' };
            const dateStr = viewDate.toLocaleDateString('es-ES', options);
            document.getElementById('current-month-display').textContent = dateStr.charAt(0).toUpperCase() + dateStr.slice(1);
        }

        function renderCalendar() {
            const grid = document.getElementById('calendar-grid');
            grid.innerHTML = '';
            
            const year = viewDate.getFullYear();
            const month = viewDate.getMonth();

            const daysInMonth = new Date(year, month + 1, 0).getDate();

            for (let i = 1; i <= daysInMonth; i++) {
                const d = new Date(year, month, i);
                
                const yStr = year;
                const mStr = (month + 1).toString().padStart(2, '0');
                const dStr = i.toString().padStart(2, '0');
                const dateKey = `${yStr}-${mStr}-${dStr}`;
                
                const dayTrade = tradesData[dateKey];
                const dayJournal = journalData[dateKey];
                const hasTrades = !!dayTrade && dayTrade.count > 0;
                const hasJournal = !!dayJournal;
                const isProfit = hasTrades && dayTrade.pnl >= 0;
                
                const card = document.createElement('div');
                
                let bgStyle = 'background-color: var(--card);'; 
                let borderColor = 'border-white/5';
                let pnlColor = '';
                let statusIcon = '<i class="fa-solid fa-bed text-white/5 absolute bottom-2 right-2 text-4xl"></i>'; 

                if (hasTrades) {
                    if (isProfit) {
                        bgStyle = 'background-color: rgba(165, 191, 19, 0.2);';
                        borderColor = 'border-[#0ea600]/50'; 
                        pnlColor = 'text-[#0ea600]';
                        statusIcon = '<i class="fa-solid fa-arrow-trend-up text-[#0ea600] opacity-30 absolute bottom-2 right-2 text-5xl"></i>';
                    } else {
                        bgStyle = 'background-color: rgba(255, 107, 107, 0.2);';
                        borderColor = 'border-[#ff6b6b]/50';
                        pnlColor = 'text-[#ff6b6b]';
                        statusIcon = '<i class="fa-solid fa-arrow-trend-down text-[#ff6b6b] opacity-30 absolute bottom-2 right-2 text-5xl"></i>';
                    }
                }

                let alertIcon = (hasTrades && !hasJournal) ? `<div class="absolute -top-2 -right-2 bg-[#161a22] rounded-full p-1 z-10 shadow-md"><i class="fa-solid fa-circle-exclamation text-[#ff6b6b] text-lg animate-pulse"></i></div>` : '';
                let editIcon = hasJournal ? `<div class="absolute top-3 right-3 text-[#0ea600] opacity-70"><i class="fa-solid fa-pen text-xs"></i></div>` : '';

                card.className = `day-card relative rounded-xl border ${borderColor} cursor-pointer flex flex-col justify-between overflow-visible group`;
                card.style.cssText = bgStyle;
                
                card.onclick = () => openModal(dateKey, dayTrade, journalData[dateKey]);
                
                const dateOptions = { weekday: 'short', day: 'numeric', month: 'short' };
                const formattedDate = d.toLocaleDateString('es-ES', dateOptions);

                let contentHtml = `<div class="font-bold text-xs uppercase tracking-widest text-[#9aa4b2] mb-2">${formattedDate}</div>`;
                if (hasTrades) {
                    contentHtml += `<div class="z-10 relative"><div class="text-xs text-[#9aa4b2] mb-1">Trades: <span class="font-mono text-white">${dayTrade.count}</span></div><div class="text-2xl font-bold font-mono ${pnlColor}">${dayTrade.pnl >= 0 ? '+' : ''}${dayTrade.pnl.toFixed(2)}</div></div>`;
                } else {
                    contentHtml += `<div class="z-10 flex-1 flex items-center"><span class="text-xs text-[#9aa4b2]/50 italic">Sin actividad</span></div>`;
                }

                card.innerHTML = `${alertIcon}${editIcon}${contentHtml}${statusIcon}<div class="absolute inset-0 bg-white/0 group-hover:bg-white/5 rounded-xl transition-colors"></div>`;
                grid.appendChild(card);
            }
        }

        // --- Funciones del Modal ---
        const modal = document.getElementById('entry-modal');
        const modalPanel = document.getElementById('modal-panel');

        function openModal(dateIso, tradeData, journalEntry) {
            const parts = dateIso.split('-');
            const localDate = new Date(parts[0], parts[1] - 1, parts[2]);
            
            document.getElementById('modal-date-title').textContent = localDate.toLocaleDateString('es-ES', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            document.getElementById('current_date_iso').value = dateIso;

            const statsSubtitle = document.getElementById('modal-stats-subtitle');
            if (tradeData) {
                const colorClass = tradeData.pnl >= 0 ? 'text-[#0ea600]' : 'text-[#ff6b6b]';
                statsSubtitle.innerHTML = `Trades: <b class="text-white">${tradeData.count}</b> | PnL: <span class="${colorClass} font-mono font-bold">$${tradeData.pnl.toFixed(2)}</span>`;
            } else {
                statsSubtitle.innerHTML = `<span class="text-[#9aa4b2]"><i class="fa-solid fa-bed"></i> Día sin operaciones</span>`;
            }

            if (journalEntry) {
                document.getElementById('q_why').value = journalEntry.why || '';
                document.getElementById('q_setup').value = journalEntry.setup || '';
                document.getElementById('q_feeling').value = journalEntry.feeling || '';
                document.getElementById('q_different').value = journalEntry.different || '';
                document.getElementById('q_comments').value = journalEntry.comments || '';
            } else {
                document.getElementById('journal-form').reset();
            }

            modal.classList.remove('hidden');
            setTimeout(() => { modal.classList.remove('opacity-0'); modalPanel.classList.remove('scale-95'); modalPanel.classList.add('scale-100'); }, 10);
        }

        function closeModal() {
            modal.classList.add('opacity-0'); modalPanel.classList.remove('scale-100'); modalPanel.classList.add('scale-95');
            setTimeout(() => { modal.classList.add('hidden'); }, 300);
        }

        async function saveJournalEntry() {
            const btnSave = document.getElementById('btn-save');
            const originalContent = btnSave.innerHTML;
            btnSave.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Guardando...';
            btnSave.disabled = true;

            const dateIso = document.getElementById('current_date_iso').value;
            const entry = {
                date: dateIso,
                why: document.getElementById('q_why').value,
                setup: document.getElementById('q_setup').value,
                feeling: document.getElementById('q_feeling').value,
                different: document.getElementById('q_different').value,
                comments: document.getElementById('q_comments').value
            };

            try {
                const response = await fetch(`${API_URL}?action=save_entry`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(entry)
                });
                const result = await response.json();
                if (result.success) {
                    journalData[dateIso] = entry;
                    renderCalendar();
                    closeModal();
                } else {
                    alert("Error: " + (result.message || 'No se pudo guardar'));
                }
            } catch (error) {
                console.error(error);
                alert("Error de conexión");
            } finally {
                btnSave.innerHTML = originalContent;
                btnSave.disabled = false;
            }
        }

        // Cargar datos al iniciar
        loadData();
    </script>
</body>
</html>