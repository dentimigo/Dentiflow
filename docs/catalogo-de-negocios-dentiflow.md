# Catálogo de negocios · DentiFlow

DentiFlow es una aplicación web de gestión para consultorios dentales. Su usuario comprador potencial es el titular o administrador de la clínica. Sus usuarios diarios incluyen personal de odontología y asistencia.

## Capítulo 1. Estudio de mercado

### Introducción

El estudio explora si existen condiciones para ofrecer DentiFlow a consultorios privados pequeños en México. La hipótesis es que centralizar agenda, expedientes, tratamientos e inventario puede ahorrar trabajo de coordinación a clínicas que hoy combinan varias herramientas.

### Desarrollo

El segmento inicial propuesto son consultorios con un titular y personal de asistencia que comparten agenda. Frente a papel y hojas de cálculo, DentiFlow ofrece un flujo compartido con roles. Frente a un calendario general, vincula las citas con la operación dental. Frente a una suite clínica, la hipótesis comercial es competir por facilidad de adopción. La elección dependerá del costo de migrar datos, del tiempo de capacitación y de la frecuencia con la que el equipo necesita cambiar de herramienta.

Para dimensionar el mercado local, se puede contar en el DENUE el número de consultorios dentales privados de un municipio piloto y segmentarlos por tamaño. Para medir demanda, se propone entrevistar al menos a diez titulares, pedirles que completen una tarea de agenda en un piloto y registrar cuánto tiempo les toma con su herramienta actual y con DentiFlow. La disposición de pago se debe preguntar por separado. El conteo de establecimientos y las entrevistas quedan pendientes hasta definir el municipio y reclutar participantes. [INEGI, DENUE](https://www.inegi.org.mx/app/mapa/denue/default.aspx)

### Análisis ETL

1. **Extracción.** Se transcribieron al CSV del proyecto tres tasas publicadas en el comunicado de la ENDUTIH 2024. El universo son personas de seis años y más. [INEGI e IFT, ENDUTIH 2024, comunicado de resultados](https://www.inegi.org.mx/contenidos/saladeprensa/boletines/2025/endutih/ENDUTIH_24.pdf)
2. **Transformación.** El comando normaliza ámbitos y separadores decimales, valida que cada porcentaje esté entre 0 y 100 y rechaza ámbitos faltantes o duplicados. Después resta el porcentaje rural del urbano.
3. **Carga.** Desde la raíz de la app, `php artisan market:analyze` carga los resultados en una tabla de consola para reproducir el análisis. La entrada está en `database/market/endutih-2024.csv`.

| Ámbito | Uso de internet en 2024 |
| --- | ---: |
| Nacional | 83,1 % |
| Urbano | 86,9 % |
| Rural | 68,5 % |

La brecha urbano-rural fue de **18,4 puntos porcentuales**. La conectividad de la población orienta la selección del canal y sugiere comenzar el piloto en una localidad urbana. La muestra de esta encuesta mide uso de internet por personas. El tamaño del mercado de software dental y la intención de compra deben estimarse con el directorio y el trabajo de campo.

### Conclusión

El siguiente paso de validación comercial es un piloto urbano con tareas de agenda y seguimiento de citas. La decisión de ampliar el piloto debe considerar el ahorro de tiempo observado, la retención de los equipos y su disposición de pago.

## Capítulo 2. Tipos y características de negocios en TI

| Tipo | Qué lo caracteriza | Aplicación y reflexión para DentiFlow |
| --- | --- | --- |
| **E-Commerce** | Compra o venta con pedidos cursados por redes mediante mecanismos diseñados para recibirlos. | Una futura contratación en línea de la suscripción sería un canal B2B. La página actual permite solicitar acceso al sistema. Antes de vender un plan, conviene validar qué funciones sostienen la disposición de pago. |
| **E-Business** | Uso de tecnología digital para organizar procesos internos y relaciones de negocio. | La agenda, los expedientes y el inventario ya forman parte de la operación digital. Adoptarlos en conjunto aporta valor cuando la clínica define quién actualiza cada registro. |
| **E-Government** | Uso de medios digitales por instituciones públicas para prestar servicios y realizar trámites. | El portal de facturación del SAT ilustra la relación digital con una autoridad. La clínica puede realizar allí su trámite por separado. Una futura integración fiscal requeriría validar requisitos, responsabilidades y tratamiento de datos. |
| **EDI** | Intercambio automatizado de mensajes estructurados entre sistemas de organizaciones distintas. | En una fase futura, clínica y proveedor podrían intercambiar órdenes y confirmaciones con un estándar acordado. El beneficio de evitar recaptura debe compensar el costo de integrar catálogos, unidades y acuses con el proveedor. |

Referencias del capítulo. [OCDE, definición de comercio electrónico](https://www.oecd.org/en/publications/2025/10/the-2025-oecd-definition-of-e-commerce-and-guidelines-for-interpretation_3e7e5a61.html) · [Naciones Unidas, E-Government Survey 2024](https://publicadministration.desa.un.org/sites/default/files/list-of-files/2024/Presentation%201_UNEGOVSURVEY_%202024_Workshop.pdf) · [SAT, factura electrónica](https://wwwmat.sat.gob.mx/tramites/82914/genera-facturas-por-tu-actividad-como-persona-fisica) · [UNECE, UN/EDIFACT](https://unece.org/trade/uncefact/introducing-unedifact)

## Capítulo 3. Internet de las Cosas (IoT)

Los ejemplos muestran cómo las lecturas de un dispositivo conectado pueden disparar una decisión operativa.

| Sector | Ejemplo | Decisión basada en la lectura |
| --- | --- | --- |
| Salud dental | Un sensor conectado registra el estado de un ciclo del esterilizador y envía el evento a un tablero de mantenimiento. | El responsable revisa el equipo ante fallas notificadas. |
| Agricultura | Sensores de humedad envían mediciones por parcela. | Ajustar el riego según las lecturas y el cultivo. |
| Industria | Un acelerómetro conectado mide vibración en un motor. | Programar una inspección ante una tendencia anormal. |
| Logística | Un registrador conectado mide la temperatura de un envío refrigerado. | Revisar el envío si se registra una desviación. |
| Comercio | Una balanza conectada estima existencias en un estante. | Reponer producto según la existencia detectada. |
| Servicios urbanos | Sensores de nivel reportan el llenado de contenedores. | Priorizar rutas de recolección. |

El monitoreo del esterilizador se plantea como piloto futuro para DentiFlow. El evento mínimo incluiría identificador del equipo, fecha y estado. El piloto debería comprobar funcionamiento durante cortes de conexión y evaluar identificación, control de acceso y actualización de dispositivos. [NIST, IoT Device Cybersecurity Capability Core Baseline](https://www.nist.gov/publications/iot-device-cybersecurity-capability-core-baseline)

## Capítulo 4. Mercadotecnia digital

La audiencia inicial son titulares de consultorios y equipos que buscan coordinar citas y reducir recapturas. El mensaje es organizar agenda, expedientes e inventario en un solo lugar. La página de presentación `/conoce-dentiflow` aplica el mensaje, explica los módulos en lenguaje comercial y conecta **Solicitar acceso** con el proceso de registro y aprobación de la app.

| Etapa | Canal y acción | Medición |
| --- | --- | --- |
| Semana 1 · Descubrimiento | Página de presentación con título y descripción orientados a búsquedas de software dental, URL canónica y metadatos para compartir. Ya incorporada a la app. | Impresiones y clics orgánicos cuando se conecte una propiedad de búsqueda. |
| Semana 2 · Consideración | Difundir un video corto con una agenda ficticia y una publicación educativa sobre coordinación de citas. Usar enlaces de campaña diferenciados por canal una vez instrumentada su atribución. | Visitas por campaña y clics hacia el registro. |
| Semana 3 · Conversión | Usar el botón de solicitud de acceso y las preguntas frecuentes ya disponibles en la página. Invitar a los prospectos interesados a realizar una tarea de agenda durante el piloto. | Solicitudes recibidas ÷ visitas a la página. |
| Semana 4 · Activación | Guiar a las cuentas aprobadas para configurar horarios y registrar su primera cita de prueba. Comparar tareas completadas y recoger motivos de abandono. | Cuentas piloto con primera cita ÷ cuentas piloto aprobadas. |

La meta inicial propuesta es incorporar cinco consultorios a un piloto de cuatro semanas. La coordinación comercial puede revisar semanalmente las métricas y entrevistar a quienes abandonen el flujo. Los anuncios, herramientas de medición y envíos quedan como acciones de campaña pendientes. Se usarán datos ficticios en las demostraciones y mediciones agregadas sin expedientes ni datos de pacientes.
