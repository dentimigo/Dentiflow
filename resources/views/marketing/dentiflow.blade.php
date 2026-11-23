<!doctype html>
<html lang="es-MX">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Conoce DentiFlow | El día a día de tu consultorio dental</title>
    <meta name="description" content="Conoce las herramientas de DentiFlow para tu consultorio dental, compara los planes mensuales y descubre cómo activar y administrar tu suscripción.">
    <link rel="canonical" href="{{ route('marketing.dentiflow') }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Conoce DentiFlow | Un día en el consultorio">
    <meta property="og:description" content="Descubre cómo DentiFlow acompaña a tu equipo desde la primera cita hasta el cierre del día.">
    <meta property="og:url" content="{{ route('marketing.dentiflow') }}">
    <link rel="stylesheet" href="{{ asset('css/dentiflow-blog.css') }}">
    <script defer src="{{ asset('js/dentiflow-carousel.js') }}"></script>
</head>
<body id="inicio">
    @php
        $plans = \App\Models\ClinicWorkspace::TRIAL_PLANS;
        $stripeEnabled = config('stripe.enabled');
        $chapters = [
            [
                'id' => 'resumen', 'label' => 'Resumen', 'title' => 'Empezar con una vista del día',
                'body' => 'Al entrar a DentiFlow, el resumen nos muestra las citas de hoy y los accesos rápidos que corresponden a nuestro perfil. Desde ahí podemos abrir una cita, registrar un paciente o entrar al módulo que necesitamos.',
                'example' => 'En recepción consultamos las citas del día antes de registrar la primera visita.',
                'caption' => 'Accesos del panel de inicio',
                'rows' => [['Citas de hoy', null], ['Nuevo paciente', null], ['Agendar cita', null]],
            ],
            [
                'id' => 'agenda', 'label' => 'Agenda y citas', 'title' => 'Antes de abrir la puerta',
                'body' => 'En el calendario podemos revisar quién viene, a qué hora y con quién. El módulo de citas permite programar, confirmar, reprogramar o cancelar una visita, así como consultar su historial. También podemos bloquear horarios según nuestros permisos.',
                'example' => 'Una paciente pide cambiar su cita. Revisamos los huecos del calendario, elegimos otro horario y actualizamos la cita.',
                'caption' => 'Una mañana en la agenda',
                'rows' => [['09:00 · Revisión', 'Confirmada'], ['10:30 · Limpieza', 'Pendiente'], ['12:00 · Comida', 'Bloqueado']],
            ],
            [
                'id' => 'expedientes', 'label' => 'Pacientes y expedientes', 'title' => 'Retomar la consulta donde la dejamos',
                'body' => 'En Pacientes registramos a las personas que atiende el consultorio y buscamos su ficha. En Expedientes consultamos sus antecedentes y visitas anteriores. Al terminar la consulta, registramos lo ocurrido para tenerlo a mano en la siguiente visita.',
                'example' => 'Antes de atender una revisión, consultamos los antecedentes y la nota de la última visita.',
                'caption' => 'Lo que consultamos antes de atender',
                'rows' => [['Antecedentes médicos', null], ['Consultas anteriores', null], ['Nota de la visita', null]],
            ],
            [
                'id' => 'tratamientos', 'label' => 'Tratamientos', 'title' => 'Ponerle nombre y costo a cada tratamiento',
                'body' => 'En el catálogo podemos consultar los tratamientos registrados y sus costos. Cuando vinculamos un tratamiento a una cita, queda registrado qué procedimiento se programó para esa visita.',
                'example' => 'Al agendar una limpieza, seleccionamos el tratamiento del catálogo y consultamos su costo.',
                'caption' => 'Tres tratamientos de ejemplo',
                'rows' => [['Revisión dental', null], ['Limpieza dental', null], ['Resina', null]],
            ],
            [
                'id' => 'inventario', 'label' => 'Inventario', 'title' => 'Revisar qué hace falta reponer',
                'body' => 'Entre consultas podemos revisar las existencias y los niveles de reposición de los insumos. También podemos consultar proveedores y exportar los reportes de inventario a PDF o Excel.',
                'example' => 'Si quedan pocos guantes, revisamos el nivel de reposición y consultamos el proveedor antes de hacer el pedido.',
                'caption' => 'Un vistazo a los insumos',
                'rows' => [['Guantes', 'Por reponer'], ['Mascarillas', 'Disponible'], ['Vasos desechables', 'Disponible']],
            ],
            [
                'id' => 'reportes', 'label' => 'Reportes', 'title' => 'Cerrar el día con los datos a la mano',
                'body' => 'Al terminar la jornada podemos revisar el historial de citas y consultar reportes de la operación. DentiFlow permite filtrar los reportes y exportarlos cuando el equipo necesita conservar una copia.',
                'example' => 'Al cierre de la semana, elegimos el periodo que queremos revisar y descargamos el reporte.',
                'caption' => 'Una revisión semanal',
                'rows' => [['Periodo', 'Esta semana'], ['Formato disponible', 'PDF'], ['Formato disponible', 'Excel']],
            ],
            [
                'id' => 'equipo', 'label' => 'Equipo y acceso', 'title' => 'Organizarnos como equipo',
                'body' => 'Cada clínica tiene su propio espacio. La persona responsable puede invitar a su equipo, y los permisos de cada perfil determinan qué secciones puede consultar y qué acciones puede realizar.',
                'example' => 'Cuando se incorpora una asistente, le enviamos una invitación para que se una a la clínica con su propia cuenta.',
                'caption' => 'Perfiles del consultorio',
                'rows' => [['Responsable de la clínica', null], ['Doctor', null], ['Asistente', null]],
            ],
            [
                'id' => 'perfil', 'label' => 'Perfil y seguridad', 'title' => 'Ajustar nuestra cuenta y disponibilidad',
                'body' => 'En Mi perfil podemos revisar nuestros datos y acceder a las opciones de seguridad. El personal clínico que tiene habilitada la agenda semanal también puede indicar qué días y horas está disponible.',
                'example' => 'Si una doctora cambia su horario de los viernes, puede actualizarlo en su agenda semanal.',
                'caption' => 'Opciones del perfil clínico',
                'rows' => [['Datos profesionales', null], ['Agenda semanal', null], ['Seguridad y contraseña', null]],
            ],
        ];
    @endphp

    <header class="site-header">
        <nav class="wrap top-nav" aria-label="Navegación principal">
            <a class="brand" href="{{ route('marketing.dentiflow') }}">DentiFlow</a>
            <div class="nav-links">
                <a href="#funciones">El recorrido</a>
                <a href="#planes">Planes y pagos</a>
                <a href="#preguntas">Preguntas frecuentes</a>
                <a href="{{ route('login') }}">Iniciar sesión</a>
            </div>
        </nav>
    </header>

    <main>
        <section class="hero" aria-labelledby="titulo" data-photo-carousel>
            <div class="hero-photos" aria-hidden="true">
                {{-- Fotos de referencia de Pavel Danilyuk (6812480), Anna Shvets (3845736) y Arda Kaykısız (19976580), vía Pexels. --}}
                <img src="{{ asset('images/dentiflow-blog/portada-consultorio.jpg') }}" alt="" fetchpriority="high">
                <img src="{{ asset('images/dentiflow-blog/portada-atencion.jpg') }}" alt="" loading="lazy" hidden>
                <img src="{{ asset('images/dentiflow-blog/portada-equipo.jpg') }}" alt="" loading="lazy" hidden>
            </div>
            <div class="wrap hero-inner">
                <div>
                    <p class="eyebrow">El blog de DentiFlow</p>
                    <h1 id="titulo">Un día en el consultorio</h1>
                    <p class="intro">Desde la primera cita hasta el cierre, hay mucho que coordinar. Te contamos cómo puedes usar DentiFlow para llevar la agenda, dar seguimiento a cada paciente y organizar el trabajo del equipo.</p>
                    <a class="button" href="#funciones">Empezar el recorrido <span aria-hidden="true">↓</span></a>
                </div>
                <div class="hero-note">
                    <p class="eyebrow">Para tu consultorio</p>
                    <p>Compara los planes según el tamaño de tu equipo.</p>
                    <a href="#planes">Ver planes y pagos <span aria-hidden="true">↓</span></a>
                </div>
            </div>
        </section>

        <div class="wrap reading-layout" id="funciones">
            <nav class="contents" aria-label="En este artículo">
                <p class="eyebrow">En este artículo</p>
                <ul>
                    @foreach ($chapters as $chapter)
                        <li><a href="#{{ $chapter['id'] }}">{{ $chapter['label'] }}</a></li>
                    @endforeach
                    <li><a href="#planes">Planes y pagos</a></li>
                    <li><a href="#primeros-pasos">Primeros pasos</a></li>
                </ul>
            </nav>

            <div>
                @foreach ($chapters as $chapter)
                    @php
                        $image = 'images/dentiflow-blog/'.$chapter['id'].'.webp';
                    @endphp
                    <article class="chapter" id="{{ $chapter['id'] }}">
                        <p class="eyebrow">{{ $chapter['label'] }}</p>
                        <h2>{{ $chapter['title'] }}</h2>
                        <p>{{ $chapter['body'] }}</p>

                        <figure class="figure">
                            {{-- Agrega una captura de demostración en public/images/dentiflow-blog/{id}.webp. --}}
                            @if (is_file(public_path($image)))
                                <img src="{{ asset($image) }}" alt="Vista de {{ $chapter['label'] }} en DentiFlow" loading="lazy" decoding="async">
                            @else
                                <div class="preview">
                                    <p class="preview-label">Ejemplo · {{ $chapter['label'] }}</p>
                                    <ul>
                                        @foreach ($chapter['rows'] as [$item, $status])
                                            <li>
                                                <span>{{ $item }}</span>
                                                @if ($status)
                                                    <span class="status">{{ $status }}</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            <figcaption>{{ $chapter['caption'] }}</figcaption>
                        </figure>

                        <p class="example"><strong>En la práctica.</strong> {{ $chapter['example'] }}</p>
                    </article>
                @endforeach

                <section class="chapter" id="planes" aria-labelledby="titulo-planes">
                    <p class="eyebrow">Planes y pagos</p>
                    <h2 id="titulo-planes">Un plan para el tamaño de tu equipo</h2>
                    <p>Elige según cuántas personas usarán DentiFlow. El cupo de cada plan incluye al titular de la clínica.</p>
                    <div class="plan-grid">
                        @foreach ($plans as $plan)
                            <div class="plan-card">
                                <h3>{{ $plan['name'] }}</h3>
                                <p><strong>${{ number_format($plan['price']) }}</strong> MXN al mes</p>
                                <p>Hasta {{ $plan['seats'] }} personas</p>
                            </div>
                        @endforeach
                    </div>

                    <h3>Prueba de 72 horas</h3>
                    @if ($stripeEnabled)
                        <p>Elige un plan y registra una tarjeta de débito en Stripe. Al iniciar la prueba pagas $0. Si mantienes la suscripción, Stripe cobra el plan al terminar la prueba y después cada mes hasta que canceles. Puedes cancelar en el portal de Stripe antes de que termine la prueba para evitar el primer cobro.</p>
                    @else
                        <p>Elige un plan y confirma para empezar tu prueba gratuita de 72 horas sin tarjeta. Por ahora, el pago en línea está deshabilitado. Al terminar la prueba, la agenda requiere activación y los datos de la clínica siguen guardados.</p>
                    @endif

                    <h3>Administrar la suscripción</h3>
                    @if ($stripeEnabled)
                        <p>Desde <strong>Mi perfil → Suscripción</strong>, el titular puede consultar el plan, los lugares ocupados y el estado de la suscripción. El portal de Stripe permite actualizar la tarjeta, consultar facturas y cancelar.</p>
                        <p>Al cambiar de plan, el cupo se ajusta al confirmar y la nueva tarifa se aplica en el siguiente cobro, en la misma fecha programada. Para elegir un plan con menos lugares, el equipo actual debe caber en el nuevo cupo.</p>
                    @else
                        <p>Durante la prueba, el titular puede consultar el plan y el cupo desde <strong>Mi perfil → Suscripción</strong>. Puede cambiar a otro plan si todos los integrantes caben en él. El cambio conserva la fecha de vencimiento de la prueba.</p>
                    @endif
                </section>

                <section class="chapter" id="primeros-pasos" aria-labelledby="titulo-pasos">
                    <h2 id="titulo-pasos">Cómo empezar en DentiFlow</h2>
                    <ol class="steps">
                        <li><strong>Registra tu consultorio.</strong> Completa el formulario con tus datos y el nombre de tu clínica.</li>
                        <li><strong>Activa tu espacio.</strong> Selecciona el plan que se ajuste a tu equipo y sigue los pasos para iniciar la prueba.</li>
                        <li><strong>Invita a tu equipo.</strong> Desde Usuarios, el titular puede enviar invitaciones al personal de odontología y asistencia.</li>
                        <li><strong>Agenda la primera visita.</strong> Registra a un paciente, revisa la disponibilidad y crea su cita.</li>
                    </ol>
                </section>

                <section class="faq" id="preguntas">
                    <h2>Preguntas frecuentes</h2>
                    <details>
                        <summary>¿Cómo registro mi consultorio?</summary>
                        <p>Selecciona «Solicitar acceso» y completa el formulario con tus datos y los del consultorio. Después podrás revisar las opciones para activar tu espacio.</p>
                    </details>
                    <details>
                        <summary>¿Cómo me uno a un consultorio que ya usa DentiFlow?</summary>
                        <p>Pide a la persona responsable de tu clínica que te envíe una invitación. Usa el enlace de la invitación para crear tu acceso.</p>
                    </details>
                    <details>
                        <summary>¿Por qué mi equipo ve apartados diferentes?</summary>
                        <p>Las secciones disponibles dependen del rol. El personal clínico puede trabajar con citas y expedientes según sus permisos. Recepción usa los apartados habilitados para asistencia, y el titular puede administrar usuarios y suscripción.</p>
                    </details>
                </section>

                <section class="join" aria-labelledby="registro">
                    <h2 id="registro">Abre el espacio de tu consultorio</h2>
                    <a class="button" href="{{ route('register') }}">Solicitar acceso <span aria-hidden="true">↗</span></a>
                </section>
            </div>
        </div>
    </main>

    <footer class="wrap site-footer">
        <a class="brand" href="{{ route('marketing.dentiflow') }}">DentiFlow</a>
        <a href="#inicio">Volver arriba ↑</a>
    </footer>
</body>
</html>
