<?php
require_once __DIR__ . '/api/session_bootstrap.php';
require_once __DIR__ . '/api/csrf.php';
start_secure_session();
$csrfToken = csrf_token();
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
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Registro - Trade Metrics</title>
    <!-- ¡NUEVO! Icono de la pestaña -->
    <link rel="icon" type="image/png" href="logo3.png">
    
    <style>
      :root{--bg:#0f1724; --card:#161a22; --muted:#9aa4b2; --accent:#a5bf13; --danger:#ff6b6b;}
      
      /* ¡MODIFICADO! Añadido display:flex para centrar */
      html,body{
        height:100%;
        margin:0;
        background:linear-gradient(180deg,#192731 0%, #192731 100%);
        color:#e6eef6; 
        font-family: Inter, sans-serif;
        display: block; 
        align-items: center; 
        justify-content: center;
      }
      
      .wrap{max-width:400px;margin:50px auto;padding:20px; width: 100%; box-sizing: border-box;}
      .card{background:var(--card);padding:20px;border-radius:10px;box-shadow:0 6px 18px rgba(2,6,23,0.6)}
      label{display:block;font-size:13px;color:var(--muted);margin-bottom:6px}
      input{width:100%;padding:8px 10px;border-radius:8px;border:1px solid rgba(255,255,255,0.04);background:var(--glass);color:inherit;font-size:14px; box-sizing: border-box;}
      .btn{display:inline-block;padding:8px 10px;border-radius:8px;background:#a5bf13;color:#012322;border:none;cursor:pointer;font-weight:600;width:100%;}
      #message { margin-top: 15px; font-size: 14px; text-align: center; }
      .success { color: var(--accent); }
      .error { color: var(--danger); }
    </style>
</head>
<body>
    <!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-F4G8QC48"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
    <div class="wrap">
    
        <!-- ¡NUEVO! Logo añadido encima del card -->
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="logo5.png" alt="Trade Metrics Logo" width="100">
        </div>

        <div class="card">
            <h2 style="text-align:center; margin-top:0;">Crear Cuenta</h2>
            <form id="registerForm">
                <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div style="margin-bottom: 10px;">
                    <label>Email</label>
                    <input id="email" type="email" required>
                </div>
                <div style="margin-bottom: 10px;">
                    <label>Contraseña</label>
                    <input id="password" type="password" minlength="8" required>
                </div>
                <div style="margin-bottom: 15px;">
                    <label>Confirmar Contraseña</label>
                    <input id="password_confirm" type="password" minlength="8" required>
                </div>
                <button type="submit" class="btn">Registrarse</button>
            </form>
            <div id="message"></div>
            <p style="font-size: 13px; text-align:center; margin-top:15px; color:var(--muted);">
                ¿Ya tienes cuenta? <a href="login.php" style="color:var(--accent)">Inicia Sesión</a>
            </p>
        </div>
    </div>

    <script>
        document.getElementById('registerForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;
            const messageEl = document.getElementById('message');

            if (password !== passwordConfirm) {
                messageEl.textContent = 'Las contraseñas no coinciden.';
                messageEl.className = 'error';
                return;
            }
            if (password.length < 8) {
                messageEl.textContent = 'La contraseña debe tener al menos 8 caracteres.';
                messageEl.className = 'error';
                return;
            }

            try {
                const response = await fetch('register_process.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        email: email,
                        password: password,
                        csrf_token: document.getElementById('csrf_token').value
                    })
                });

                const result = await response.json();

                if (result.success) {
                    messageEl.textContent = '¡Registro exitoso! Redirigiendo a login...';
                    messageEl.className = 'success';
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                } else {
                    messageEl.textContent = result.message;
                    messageEl.className = 'error';
                }
            } catch (err) {
                messageEl.textContent = 'Error de conexión.';
                messageEl.className = 'error';
            }
        });
    </script>
</body>
</html>