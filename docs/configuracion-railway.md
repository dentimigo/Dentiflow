# Configuracion de DentiFlow en Railway

URL publica:

`https://dentiflow-production-ab21.up.railway.app`

## Variables del servicio web

En una base nueva configura `DENTIFLOW_ADMIN_EMAIL` y `DENTIFLOW_ADMIN_PASSWORD` para el administrador operativo que gestiona **Usuarios**. Si también necesitas acceso a la administración general, configura `DENTIFLOW_SUPERADMIN_EMAIL` y `DENTIFLOW_SUPERADMIN_PASSWORD` con otro correo. Usa contraseñas únicas de al menos 12 caracteres y guarda estos valores solo en Railway.

El predespliegue crea las cuentas configuradas después de migrar. Las cuentas existentes conservan su contraseña. Una vez creadas, se pueden retirar las variables de aprovisionamiento.

Copiar en **Railway > servicio DentiFlow > Variables** los valores de
`.env.railway.example`. `APP_KEY` debe contener una clave generada una sola vez
con `php artisan key:generate --show` y no debe cambiarse en cada despliegue.

No crear la variable `SESSION_DOMAIN`. La cookie debe quedar limitada
automaticamente al dominio publico de Railway. Usar el texto `"null"` como
dominio puede impedir que algunos navegadores conserven la sesion.

Para que las sesiones sobrevivan reinicios y funcionen de forma consistente en
mas de una instancia, usar `SESSION_DRIVER=database`. La migracion incluida en
el proyecto ya crea la tabla `sessions`.

Las variables `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y
`DB_PASSWORD` deben referenciar el servicio MySQL de Railway, no `127.0.0.1`.

La aplicacion limita a 5 segundos el establecimiento de la conexion y no usa
conexiones PDO persistentes. Los valores de sesion MySQL recomendados son:

```dotenv
DB_CONNECT_TIMEOUT=5
MYSQL_SESSION_WAIT_TIMEOUT=600
MYSQL_SESSION_INTERACTIVE_TIMEOUT=600
```

En el servicio MySQL se pueden comprobar los limites efectivos con
`SHOW VARIABLES LIKE 'wait_timeout'` y
`SHOW VARIABLES LIKE 'interactive_timeout'`. Un valor de 600 segundos es
coherente con las conexiones no persistentes de esta aplicacion. No es
necesario conservar conexiones inactivas durante horas.

El repositorio no incluye Nginx, Apache, Octane, Procfile ni un comando de
inicio de Railway; por tanto, el limite total de la peticion depende del Start
Command configurado en el panel. Se recomienda limitar cada request a 30
segundos (por ejemplo, iniciar PHP con `-d max_execution_time=30`) y mantener
el timeout del proxy de Railway por encima de ese valor, para que PHP pueda
responder con 503 antes de que el proxy genere un 504.

Despues de cambiar las variables, desplegar nuevamente y ejecutar en el proceso
de despliegue:

```shell
php artisan migrate --force
php artisan optimize:clear
```

## Sesiones simultaneas

Cada navegador o dispositivo recibe una cookie de sesion independiente. Un
doctor, un asistente y un administrador pueden permanecer conectados al mismo
tiempo desde dispositivos distintos. Para probar dos cuentas en un solo equipo,
usar navegadores distintos o una ventana privada; dos pestanas normales del
mismo navegador comparten la misma sesion por diseno.

## Datos que no deben publicarse

`APP_KEY`, `DB_URL`, `DB_PASSWORD` y las claves de proveedores de correo son
secretos. Deben existir solo en Railway. Si alguno se comparte accidentalmente,
hay que rotarlo desde el proveedor correspondiente.
