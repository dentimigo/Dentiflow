<!doctype html>
<html lang="es-MX">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DentiFlow | Software de gestión para clínicas dentales</title>
    <meta name="description" content="Organiza la agenda, los expedientes y el inventario de tu consultorio dental con DentiFlow. Conoce sus funciones y solicita acceso para tu equipo.">
    <link rel="canonical" href="{{ route('marketing.dentiflow') }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="DentiFlow | Gestión para clínicas dentales">
    <meta property="og:description" content="Agenda, expedientes e inventario para tu equipo dental en un solo lugar.">
    <meta property="og:url" content="{{ route('marketing.dentiflow') }}">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font: 1rem/1.6 system-ui, sans-serif; color: #2c1739; background: #f9f5fc; }
        .wrap { width: min(68rem, calc(100% - 2rem)); margin-inline: auto; }
        header { background: #fff; border-bottom: 1px solid #e5d8eb; }
        nav { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .75rem 1.5rem; padding-block: 1rem; }
        nav div { display: flex; flex-wrap: wrap; gap: .75rem 1.5rem; }
        a { color: #672383; text-underline-offset: .22em; }
        a:focus-visible, summary:focus-visible { outline: 3px solid #8530a9; outline-offset: 4px; }
        .brand { font-size: 1.4rem; font-weight: 800; text-decoration: none; }
        .hero { padding: clamp(3rem, 9vw, 7rem) 0; background: #efe3f6; }
        h1, h2, h3 { line-height: 1.15; }
        h1 { max-width: 17ch; margin: 0; font-size: clamp(2.35rem, 7vw, 5rem); letter-spacing: -.045em; }
        h2 { margin-top: 0; font-size: clamp(1.65rem, 4vw, 2.5rem); }
        p { max-width: 64ch; }
        .hero p { font-size: 1.15rem; margin: 1.3rem 0; }
        .button { display: inline-block; padding: .7rem 1.15rem; border-radius: .65rem; background: #672383; color: #fff; font-weight: 700; text-decoration: none; }
        .button:hover { background: #512569; }
        .section { padding-block: clamp(2.5rem, 6vw, 4rem); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 15rem), 1fr)); gap: 1rem; }
        article, details { background: #fff; border: 1px solid #e5d8eb; border-radius: .85rem; padding: 1.2rem; }
        article h3 { margin: 0; }
        article p, details p { margin-bottom: 0; }
        details + details { margin-top: .65rem; }
        summary { cursor: pointer; font-weight: 700; }
        footer { border-top: 1px solid #e5d8eb; padding-block: 1.5rem; }
    </style>
</head>
<body>
    <header>
        <nav class="wrap" aria-label="Navegación principal">
            <a class="brand" href="{{ route('marketing.dentiflow') }}">DentiFlow</a>
            <div>
                <a href="#funciones">Funciones</a>
                <a href="#preguntas">Preguntas frecuentes</a>
                <a href="{{ route('login') }}">Iniciar sesión</a>
            </div>
        </nav>
    </header>
    <main>
        <section class="hero">
            <div class="wrap">
                <h1>Gestiona tu consultorio dental en un solo lugar</h1>
                <p>Coordina las citas de tu equipo, consulta los expedientes de tus pacientes y revisa las existencias de tu consultorio desde DentiFlow.</p>
                <a class="button" href="{{ route('register') }}">Solicitar acceso</a>
            </div>
        </section>
        <section class="wrap section" id="funciones">
            <h2>Herramientas para tu equipo dental</h2>
            <div class="grid">
                <article>
                    <h3>Agenda y citas</h3>
                    <p>Consulta la disponibilidad del equipo en el calendario y da seguimiento al estado de cada cita.</p>
                </article>
                <article>
                    <h3>Expedientes y tratamientos</h3>
                    <p>Organiza el historial clínico y consulta el catálogo de tratamientos y sus costos.</p>
                </article>
                <article>
                    <h3>Inventario y reportes</h3>
                    <p>Revisa las existencias de insumos y consulta los reportes de operación según tu rol.</p>
                </article>
            </div>
        </section>
        <section class="wrap section" id="preguntas">
            <h2>Preguntas frecuentes</h2>
            <details>
                <summary>¿Quién puede solicitar acceso?</summary>
                <p>El personal de odontología y asistencia puede seleccionar su perfil y crear una cuenta. Un administrador debe aprobarla para habilitar el acceso.</p>
            </details>
            <details>
                <summary>¿Cómo se organiza el acceso del equipo?</summary>
                <p>DentiFlow usa roles para controlar qué secciones y acciones tiene disponibles cada integrante del equipo.</p>
            </details>
        </section>
    </main>
    <footer>
        <div class="wrap">DentiFlow</div>
    </footer>
</body>
</html>
