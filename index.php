<!doctype html>
<html lang="es">
<head>
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-F4G8QC48');</script>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Trade Metrics - Dashboard y Métricas Avanzadas para MT5</title>
    <link rel="icon" type="image/png" href="logo3.png">
    
    <style>
      :root{
        --bg:#0f1724; 
        --accent:#a5bf13; 
        --text:#e6eef6;
        --muted:#9aa4b2;
        --font-main: 'Inter', sans-serif;
      }
      
      body, html { margin: 0; padding: 0; background-color: var(--bg); font-family: var(--font-main); color: var(--text); overflow-x: hidden; }

      /* --- FONDO APP --- */
      .app-background-container {
        position: fixed;
        top: 0; left: 0; width: 100vw; height: 100vh; z-index: 1;
        overflow: hidden;
      }
      .app-screenshot { width: 100%; height: auto; display: block; will-change: transform; }
      
      .blur-overlay {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        z-index: 2;
        backdrop-filter: blur(8px);
        background: rgba(0, 0, 0, 0.4);
        pointer-events: none;
      }

      /* --- HERO FIJO --- */
      .hero-content {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100vh;
        display: flex; flex-direction: column; justify-content: center; align-items: center;
        text-align: center; z-index: 10; padding: 20px; box-sizing: border-box;
        pointer-events: none;
      }
      .hero-content > * { pointer-events: auto; }

      header {
        position: fixed;
        top: 0; width: 100%; display: flex; justify-content: space-between; align-items: center;
        padding: 25px 40px; box-sizing: border-box; z-index: 100;
      }
      .logo-link { font-weight: 800; font-size: 24px; text-decoration: none; color: #fff; }

      h1 { font-size: 4rem; line-height: 1.1; margin: 0 0 20px; font-weight: 800; max-width: 900px; }
      h1 strong { color: var(--accent); }
      .description { font-size: 1.2rem; color: var(--muted); margin-bottom: 40px; max-width: 650px; }

      .btn { padding: 18px 38px; border-radius: 12px; font-weight: 700; text-decoration: none; display: inline-block; transition: 0.3s; border: none; }
      .btn-primary { background: var(--accent); color: #000; }
      .btn-secondary { border: 1px solid rgba(255,255,255,0.3); color: #fff; margin-left: 15px; background: rgba(255,255,255,0.1); }

      /* --- MODIFICADO: Aumentado a 250vh para mucho más scroll --- */
      .scroll-logic-spacer { height: 250vh; } 

      /* --- SECCIÓN SLIDER --- */
      .features-slider-section {
        position: relative; 
        background: var(--bg); 
        padding: 60px 20px 80px; 
        text-align: center; 
        z-index: 20;
        border-top: 1px solid rgba(255,255,255,0.05);
      }

      .slider-container {
        max-width: 1100px; 
        margin: 0 auto; 
        position: relative;
      }

      .img-frame {
        width: 100%;
        height: 521px; 
        background: #161a22; 
        border-radius: 15px;
        box-shadow: 0 40px 100px rgba(0,0,0,0.8);
        border: 1px solid rgba(255,255,255,0.1);
        overflow: hidden;
      }

      .main-feature-img {
        width: 100%;
        height: 100%;
        object-fit: cover; 
        object-position: top center;
        display: block;
        transition: opacity 0.4s ease;
      }

      .arrow {
        position: absolute; 
        top: 50%; 
        transform: translateY(-50%);
        background: var(--accent); 
        color: #000;
        width: 50px; 
        height: 50px; 
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; 
        z-index: 30; 
        font-size: 20px;
        transition: 0.3s; 
        border: none;
        box-shadow: 0 10px 20px rgba(0,0,0,0.5);
      }
      .arrow:hover { transform: translateY(-50%) scale(1.1); background: #fff; }
      .arrow-left { left: -25px; }
      .arrow-right { right: -25px; }

      .fade-text { transition: opacity 0.4s ease; margin-bottom: 40px; }

      @media (max-width: 768px) {
        h1 { font-size: 2.2rem; }
        .img-frame { height: 350px; }
        .btn-secondary { margin-left: 0; margin-top: 10px; width: 100%; text-align: center;}
        .btn-primary { width: 100%; }
        header { padding: 20px; flex-wrap: wrap; gap: 10px; }
        .logo-link { font-size: 20px; }
        .description { font-size: 1rem; }
        #featureTitle { font-size: 1.8rem; }
        #featureDesc { font-size: 1rem; }
        /* Las flechas quedaban a medias afuera del contenedor y se recortaban con overflow-x:hidden */
        .arrow { width: 38px; height: 38px; font-size: 16px; }
        .arrow-left { left: 5px; }
        .arrow-right { right: 5px; }
      }

      @media (max-width: 420px) {
        h1 { font-size: 1.7rem; }
        .img-frame { height: 220px; }
      }
    </style>
</head>
<body>

    <header>
        <a href="index.php" class="logo-link">Trade Metrics</a>
        <a href="login.php" class="btn btn-secondary" style="padding: 10px 22px; font-size: 0.9rem;">Iniciar Sesión</a>
    </header>

    <div class="app-background-container">
        <img src="trademetrics.png" id="bgImage" class="app-screenshot" alt="App Preview">
    </div>
    <div class="blur-overlay"></div>

    <div class="hero-content">
        <img src="logo5.png" alt="Logo" width="80" style="margin-bottom: 25px;">
        <h1>Deja de Adivinar. <br><strong>Domina tu Trading con Datos.</strong></h1>
        <p class="description">La herramienta definitiva para traders de MetaTrader 5 que buscan precisión y rentabilidad real.</p>
        <div class="cta">
            <a href="register.php" class="btn btn-primary">Empieza Tu Análisis</a>
            <a href="login.php" class="btn btn-secondary">Ya tengo cuenta</a>
        </div>
    </div>

    <div class="scroll-logic-spacer"></div>

    <section class="features-slider-section" id="funcionalidades">
        <div class="fade-text" id="textContainer">
            <h2 id="featureTitle" style="font-size: 3rem; margin: 0 0 10px; font-weight: 800;">Diario de Trading Automático</h2>
            <p id="featureDesc" style="color: var(--muted); max-width: 750px; margin: 0 auto; font-size: 1.2rem;">
                Tus operaciones se registran y consolidan solas. Control total de tus ganancias y pérdidas diarias.
            </p>
        </div>

        <div class="slider-container">
            <button class="arrow arrow-left" onclick="changeFeature(-1)">&#10094;</button>
            <div class="img-frame">
                <img src="diario.png" id="featureImg" class="main-feature-img" alt="Funcionalidad">
            </div>
            <button class="arrow arrow-right" onclick="changeFeature(1)">&#10095;</button>
        </div>
        
        <footer style="margin-top: 80px; padding-bottom: 40px; color: var(--muted); opacity: 0.5; font-size: 0.9rem;">
            <p>&copy; <?php echo date('Y'); ?> Trade Metrics. Todos los derechos reservados.</p>
        </footer>
    </section>

    <script>
        const bgImage = document.getElementById('bgImage');
        window.addEventListener('scroll', () => {
            const scrollPercent = window.scrollY / (document.documentElement.scrollHeight - window.innerHeight);
            // Movemos la imagen un 50% hacia arriba para ver más de la parte inferior
            bgImage.style.transform = `translateY(-${scrollPercent * 50}%)`;
        });

        const features = [
            {
                title: "Diario de Trading Automático",
                desc: "Tus operaciones se registran y consolidan solas. Control total de tus ganancias y pérdidas diarias.",
                img: "diario.png"
            },
            {
                title: "Dashboard y Estadísticas de Operaciones",
                desc: "Analiza tu ratio de acierto, drawdown y curva de crecimiento con gráficos de alta precisión.",
                img: "trademetrics.png" 
            },
            {
                title: "Comunidad",
                desc: "Conéctate con otros traders, comparte tus resultados y crece en un ecosistema profesional.",
                img: "redes.png" 
            }
        ];

        let currentIndex = 0;

        function changeFeature(direction) {
            const featureImg = document.getElementById('featureImg');
            const textContainer = document.getElementById('textContainer');
            
            featureImg.style.opacity = 0;
            textContainer.style.opacity = 0;

            setTimeout(() => {
                currentIndex = (currentIndex + direction + features.length) % features.length;
                
                featureImg.src = features[currentIndex].img;
                document.getElementById('featureTitle').innerText = features[currentIndex].title;
                document.getElementById('featureDesc').innerText = features[currentIndex].desc;

                featureImg.style.opacity = 1;
                textContainer.style.opacity = 1;
            }, 400);
        }
    </script>
</body>
</html>