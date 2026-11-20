# Instalación de DentiFlow en otra laptop

Esta guía explica cómo trasladar y ejecutar DentiFlow en una laptop con Windows usando XAMPP y Visual Studio Code. El proyecto utiliza Laravel 12, PHP, MySQL/MariaDB, Composer, Node.js, npm y Vite.

## Especificaciones técnicas necesarias

Antes de copiar o instalar el proyecto, la laptop debe cumplir con estas características.

### Hardware

| Componente | Mínimo para ejecutar el sistema | Recomendado para trabajar cómodamente |
| --- | --- | --- |
| Procesador | 2 núcleos, 64 bits | Intel Core i5, AMD Ryzen 5 o equivalente, 4 núcleos o más |
| Memoria RAM | 4 GB | 8 GB o más |
| Almacenamiento libre | 5 GB | 10 GB o más, preferentemente en SSD |
| Pantalla | 1366 × 768 | 1920 × 1080 |
| Conexión a internet | Necesaria para descargar las dependencias | Conexión estable de banda ancha |

Los datos de pacientes y los respaldos pueden aumentar el espacio utilizado. Se recomienda conservar espacio adicional y realizar respaldos periódicos fuera de la carpeta de XAMPP.

### Sistema operativo y arquitectura

- Windows 10 u 11 de 64 bits.
- Permisos para instalar programas y modificar variables de entorno.
- Fecha, hora y zona horaria de Windows configuradas correctamente.

La guía está escrita para Windows y XAMPP. El proyecto también puede funcionar en otros sistemas, pero las rutas y comandos cambiarían.

### Versiones de software

| Programa | Versión requerida o recomendada | Uso dentro de DentiFlow |
| --- | --- | --- |
| PHP | 8.2 o superior, de 64 bits | Ejecuta Laravel 12 |
| XAMPP | Una versión que incluya PHP 8.2 o superior | Proporciona Apache, PHP y MySQL/MariaDB |
| MySQL/MariaDB | La versión incluida en un XAMPP compatible | Guarda pacientes, usuarios, citas y demás información |
| Composer | 2.x | Instala las dependencias PHP de `composer.lock` |
| Node.js | 20.19 o superior, o 22.12 o superior | Requerido por Vite 7 |
| npm | La versión incluida con el Node.js compatible | Instala y compila los recursos web |
| Visual Studio Code | Versión estable reciente | Edición y terminal del proyecto |
| Git | Versión estable reciente, opcional | Descarga y actualización del código |

Para evitar problemas de compatibilidad, se recomienda Node.js 22 LTS. Las versiones exactas de las librerías del proyecto se encuentran fijadas en `composer.lock` y `package-lock.json`; esos archivos no deben eliminarse al trasladar el proyecto.

### Extensiones requeridas de PHP

En `C:\xampp\php\php.ini` deben estar habilitadas:

```text
curl
fileinfo
mbstring
mysqli
openssl
pdo_mysql
zip
```

También deben estar disponibles las extensiones básicas que ya incluye PHP, como `ctype`, `filter`, `hash`, `session`, `tokenizer` y `xml`. Se pueden comprobar desde la terminal con:

```powershell
C:\xampp\php\php.exe -m
```

Si una extensión aparece precedida por punto y coma en `php.ini`, por ejemplo `;extension=pdo_mysql`, se habilita quitando el punto y coma. Después se debe guardar el archivo, reiniciar Apache y abrir una terminal nueva.

### Puertos y servicios locales

| Servicio | Puerto predeterminado | Debe estar activo |
| --- | --- | --- |
| Apache de XAMPP | 80 y, para HTTPS, 443 | Sí, si se utilizará Apache o phpMyAdmin |
| MySQL/MariaDB de XAMPP | 3306 | Sí, durante todo el uso del sistema |
| Laravel con `artisan serve` | 8000 | Sí, mientras DentiFlow esté abierto |
| Vite en desarrollo | 5173 | Solo al ejecutar `npm run dev` |

El Firewall de Windows puede solicitar permiso para PHP, Apache o Node.js. Si DentiFlow solo se usará en esa laptop, basta con autorizar redes privadas. No se deben publicar estos puertos en internet sin configurar seguridad, HTTPS y un servidor apropiado.

### Archivos y accesos necesarios

- La cuenta de Windows debe poder escribir dentro de `C:\xampp\htdocs\Dentiflow-Front`.
- Laravel necesita permiso de escritura en `storage` y `bootstrap/cache`.
- MySQL debe permitir crear y modificar la base `dentiflow_front`.
- Durante la primera instalación se necesita internet para `composer install` y `npm install`.
- Después de instalar dependencias y ejecutar `npm run build`, el uso local normal no requiere internet, salvo las funciones externas que se configuren posteriormente, como correo SMTP.
- No se debe publicar, enviar por correo ni subir al repositorio el archivo `.env`, porque contiene la configuración privada del sistema.

## 1. Programas necesarios

Instalar en la nueva laptop:

- XAMPP con PHP 8.2 o superior y MySQL/MariaDB.
- Composer.
- Node.js 20.19 o superior, o 22.12 o superior, que incluye npm.
- Visual Studio Code.
- Git, recomendado si el proyecto se copiará desde un repositorio.

Comprobar las instalaciones desde PowerShell o la terminal de VS Code:

```powershell
C:\xampp\php\php.exe -v
composer --version
node --version
npm --version
git --version
```

PHP debe mostrar una versión 8.2 o posterior. Si `php` no se reconoce, se puede usar `C:\xampp\php\php.exe` en todos los comandos de esta guía o agregar `C:\xampp\php` a la variable `Path` de Windows.

En `C:\xampp\php\php.ini` deben estar habilitadas, como mínimo, las extensiones habituales de Laravel y MySQL: `pdo_mysql`, `mysqli`, `openssl`, `mbstring`, `fileinfo`, `curl` y `zip`. Después de modificar `php.ini`, reiniciar Apache.

## 2. Copiar el proyecto

Colocar el proyecto en:

```text
C:\xampp\htdocs\Dentiflow-Front
```

Hay dos formas recomendadas:

### Opción A: clonar con Git

```powershell
cd C:\xampp\htdocs
git clone DIRECCION_DEL_REPOSITORIO Dentiflow-Front
cd Dentiflow-Front
```

### Opción B: copiar una carpeta o archivo ZIP

Copiar o descomprimir el proyecto dentro de `C:\xampp\htdocs`. No es necesario copiar las carpetas `vendor` y `node_modules`, porque se vuelven a generar. Sí se deben conservar los archivos del proyecto, incluyendo `.env.example`, `composer.json`, `composer.lock`, `package.json` y `package-lock.json`.

Abrir la carpeta completa `C:\xampp\htdocs\Dentiflow-Front` en Visual Studio Code, no solamente un archivo individual.

## 3. Encender XAMPP y crear la base de datos

1. Abrir el Panel de Control de XAMPP.
2. Iniciar **Apache** y **MySQL**.
3. Abrir `http://localhost/phpmyadmin`.
4. Crear una base de datos llamada `dentiflow_front` con cotejamiento `utf8mb4_unicode_ci`.

También puede crearse desde la consola de MySQL:

```sql
CREATE DATABASE dentiflow_front
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

## 4. Configurar el archivo `.env`

En la terminal de VS Code, dentro del proyecto:

```powershell
Copy-Item .env.example .env
```

Verificar estas variables en `.env`:

```dotenv
APP_NAME=DentiFlow
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dentiflow_front
DB_USERNAME=root
DB_PASSWORD=
```

La instalación normal de XAMPP usa el usuario `root` sin contraseña. Si MySQL tiene otra contraseña o puerto, se deben colocar aquí los valores reales. No copiar el valor `APP_KEY` de una instalación nueva; el comando de la siguiente sección lo generará.

Para habilitar el respaldo desde el módulo administrativo, se puede agregar:

```dotenv
MYSQLDUMP_PATH="C:\xampp\mysql\bin\mysqldump.exe"
```

## 5. Instalar dependencias y preparar Laravel

Ejecutar desde la raíz del proyecto:

```powershell
composer install
npm install
php artisan key:generate
php artisan config:clear
```

Si `php` no está agregado a `Path`, sustituir cada uso de `php` por `C:\xampp\php\php.exe`, por ejemplo:

```powershell
C:\xampp\php\php.exe artisan key:generate
```

No se recomienda usar `composer update` para instalar el proyecto en otra laptop, porque podría cambiar las versiones registradas en `composer.lock`.

## 6. Preparar o trasladar la información

Elegir solamente uno de los siguientes escenarios.

### Escenario A: instalación nueva, sin datos anteriores

Ejecutar:

```powershell
php artisan migrate --seed
```

Las cuentas de demostración se crean con contraseñas aleatorias. Para crear tus propias cuentas administrativas, configura las variables DENTIFLOW_ADMIN_EMAIL y DENTIFLOW_ADMIN_PASSWORD (y las de superadministración si las necesitas) en el .env local y ejecuta `php artisan dentiflow:provision-admins`. Elige correos distintos de las cuentas de demostración.

### Escenario B: traslado con pacientes, citas y demás datos

En la laptop anterior:

1. Abrir phpMyAdmin.
2. Seleccionar la base `dentiflow_front`.
3. Elegir **Exportar**, método rápido, formato SQL.
4. Guardar el archivo, por ejemplo `dentiflow_front.sql`.

En la laptop nueva:

1. Crear la base vacía `dentiflow_front`.
2. Seleccionarla en phpMyAdmin.
3. Elegir **Importar** y seleccionar `dentiflow_front.sql`.
4. Ejecutar después:

```powershell
php artisan migrate
```

En este escenario no es necesario ejecutar `--seed`, pues los usuarios y datos ya vienen en el respaldo. Conservar el `.env` antiguo solo si se necesita mantener la misma `APP_KEY`, especialmente si existen datos cifrados por Laravel; de lo contrario, configurar el `.env` nuevo y generar su llave. Nunca compartir públicamente el archivo `.env`.

Si el sistema llegara a guardar archivos subidos en `storage/app`, copiar también esa carpeta desde la laptop anterior, sin reemplazar los archivos `.gitignore` de forma accidental.

## 7. Compilar la interfaz

Para usar el sistema normalmente:

```powershell
npm run build
```

Esto genera los archivos finales de CSS y JavaScript en `public/build` y evita tener que mantener Vite abierto.

Durante el desarrollo se puede usar:

```powershell
npm run dev
```

En ese caso, esa terminal debe permanecer abierta.

## 8. Iniciar DentiFlow

La forma más sencilla es:

```powershell
php artisan serve
```

Mantener la terminal abierta y entrar a:

```text
http://127.0.0.1:8000
```

Apache y MySQL deben continuar encendidos en XAMPP. Aunque Laravel se abra en el puerto 8000, MySQL sigue siendo proporcionado por XAMPP.

Como el proyecto configura `QUEUE_CONNECTION=database`, para procesar trabajos pendientes se recomienda abrir otra terminal y ejecutar:

```powershell
php artisan queue:work
```

Para desarrollo también existe el comando integrado:

```powershell
composer run dev
```

Este inicia Laravel, Vite y el procesador de la cola al mismo tiempo. Se detiene con `Ctrl+C`.

## 9. Verificar que todo funciona

Ejecutar:

```powershell
php artisan migrate:status
php artisan test
npm run build
```

Después comprobar manualmente:

- Inicio de sesión con cada rol.
- Registro y consulta de pacientes.
- Creación y edición de citas.
- Dentistas, tratamientos e inventario.
- Reportes, configuración y respaldo administrativo.
- Cierre de sesión y recuperación de contraseña.

El correo está configurado inicialmente con `MAIL_MAILER=log`; por ello, los mensajes de recuperación no se envían a una bandeja real y quedan registrados en `storage/logs/laravel.log`. Para enviar correos reales se necesita configurar un proveedor SMTP en `.env`.

## 10. Errores frecuentes

### `php`, `composer`, `node` o `npm` no se reconoce

Cerrar y abrir nuevamente VS Code después de instalar el programa. Agregar sus carpetas a `Path` o usar la ruta completa de PHP de XAMPP.

### `could not find driver`

Habilitar `extension=pdo_mysql` en `C:\xampp\php\php.ini` y reiniciar Apache/terminales.

### `Access denied for user 'root'`

El usuario, contraseña o puerto de `.env` no coincide con MySQL. Corregirlos y ejecutar:

```powershell
php artisan config:clear
```

### `Unknown database 'dentiflow_front'`

Crear la base en phpMyAdmin y comprobar `DB_DATABASE` en `.env`.

### Puerto 3306 ocupado o MySQL no inicia

Revisar el registro de XAMPP. Si MySQL usa otro puerto, actualizar `DB_PORT` en `.env` y limpiar la configuración de Laravel.

### Puerto 8000 ocupado

Iniciar Laravel en otro puerto:

```powershell
php artisan serve --port=8001
```

Cambiar también `APP_URL=http://127.0.0.1:8001` y ejecutar `php artisan config:clear`.

### Error 500 o `No application encryption key has been specified`

Ejecutar:

```powershell
php artisan key:generate
php artisan optimize:clear
```

### La página aparece sin estilos

Ejecutar `npm install` y `npm run build`. Comprobar que exista `public/build/manifest.json`.

### Cambios en `.env` no se reflejan

```powershell
php artisan optimize:clear
```

### Permisos o caché dañada

Comprobar que Windows permita escribir en `storage` y `bootstrap/cache`, y limpiar las cachés:

```powershell
php artisan optimize:clear
```

## 11. Lista rápida para entregar la laptop

- XAMPP, Composer, Node.js y VS Code instalados.
- Proyecto en `C:\xampp\htdocs\Dentiflow-Front`.
- Apache y MySQL pueden iniciar sin errores.
- Base `dentiflow_front` creada o importada.
- `.env` configurado y `APP_KEY` presente.
- `composer install` y `npm install` completados.
- Migraciones ejecutadas.
- `npm run build` completado.
- Inicio de sesión y módulos principales comprobados.
- Contraseñas iniciales cambiadas antes de usar datos reales.
- Respaldo SQL guardado en un lugar seguro.
