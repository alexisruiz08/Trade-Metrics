<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require 'api/db_connect.php';
$user_id = $_SESSION['user_id'];

// Verificar si ya está activo
$stmt = $conn->prepare("SELECT subscription_status FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if ($user['subscription_status'] === 'active') {
    header('Location: app.php');
    exit;
}
?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Pago Requerido - Trade Metrics</title>
    <link rel="icon" type="image/png" href="logo3.png">
    
    <style>
      :root{--bg:#0f1724; --card:#161a22; --muted:#9aa4b2; --accent:#a5bf13; --danger:#ff6b6b; --paypal-blue:#0070ba;}
      html,body{height:100%;margin:0;background:linear-gradient(180deg,#192731 0%, #192731 100%);color:#e6eef6;font-family:Inter,sans-serif;}
      .wrap{max-width:450px;margin:50px auto;padding:20px;}
      .card{background:var(--card);padding:30px;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.6);text-align:center;}
      .price{font-size:32px;font-weight:bold;color:#fff;margin:15px 0;}
      .old-price { text-decoration: line-through; color: var(--muted); font-size: 24px; margin-right: 10px; }
      .discount { color: var(--accent); font-weight: bold; font-size: 24px; }
      .current-price { font-size: 36px; color: #ffffff; font-weight: bold; margin: 10px 0; }
      .btn{display:block;width:100%;padding:12px 24px;margin:15px auto;border-radius:8px;font-weight:600;cursor:pointer;text-decoration:none;font-size:1rem;max-width:280px;}
      .btn-paypal{background:#0070ba;color:white;border:none;}
      .btn-secondary{background:transparent;color:var(--muted);border:1px solid var(--muted);margin-top:25px;max-width:280px;}
      .separator{margin:25px 0;color:var(--muted);}
      #paypal-button-container { max-width: 280px; margin: 0 auto; }
      .success-msg { color: var(--accent); font-weight: bold; margin-top: 20px; display: none; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h2>Pago Requerido</h2>
            <p style="color:var(--muted);">Acceso permanente al dashboard</p>
            
            <div class="price">
                <span class="old-price">USD 15</span>
                <span class="discount">15% OFF</span>
            </div>
            <div class="current-price">USD 12.99</div>

            <div class="separator">Pagar con PayPal o Tarjeta</div>

            <div id="paypal-button-container"></div>

            <div id="success-msg" class="success-msg">¡Pago exitoso! Redirigiendo al dashboard...</div>

            <p style="font-size:13px; margin-top:30px; color:var(--muted);">
                ¿Ya realizaste el pago?
            </p>
            <form action="check_payment.php" method="POST">
                <button type="submit" class="btn btn-secondary">Verificar estado del pago</button>
            </form>

            <div style="margin-top:20px;">
                <a href="api/logout.php" style="color:var(--danger); font-size:13px;">Cerrar Sesión</a>
            </div>
        </div>
    </div>

    <!-- PayPal SDK -->
    <script src="https://www.paypal.com/sdk/js?client-id=Ae6VkXu57qwn5jJhW1aZ97v9I-WFnJyIceI7jhz-DGIKhEUt5tsLJQN0XR4QS3WkilIU4TinFAI5Jd94&currency=USD"></script>

    <script>
      paypal.Buttons({
        style: {
          shape: 'rect',
          color: 'blue',
          layout: 'vertical',
          label: 'paypal'
        },
        createOrder: function(data, actions) {
          return fetch('api/create_paypal_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: <?= json_encode($user_id) ?> })
          })
          .then(res => res.json())
          .then(order => actions.order.create(order));
        },
        onApprove: function(data, actions) {
          return fetch('api/capture_paypal_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
              orderID: data.orderID,
              user_id: <?= json_encode($user_id) ?>
            })
          })
          .then(res => res.json())
          .then(result => {
            if (result.success) {
              document.getElementById('paypal-button-container').style.display = 'none';
              document.getElementById('success-msg').style.display = 'block';
              setTimeout(() => {
                window.location.href = 'app.php';
              }, 2000);
            } else {
              alert('Pago procesado pero error al activar. Contacta soporte.');
            }
          })
          .catch(err => {
            console.error(err);
            alert('Error al procesar el pago.');
          });
        },
        onError: function(err) {
          console.error('PayPal Error:', err);
          alert('Hubo un problema con PayPal.');
        }
      }).render('#paypal-button-container');
    </script>
</body>
</html>