# Conectar la base de datos de DentiFlow con XAMPP

Esta guía explica cómo conectar DentiFlow con MySQL/MariaDB de XAMPP en Windows. La conexión se configura en el archivo `.env` del proyecto Laravel.

## 1. Iniciar XAMPP

1. Abrir **XAMPP Control Panel**.
2. Presionar **Start** en **Apache** y **MySQL**.
3. Comprobar que ambos módulos aparezcan en verde.
4. Abrir `http://localhost/phpmyadmin`.

MySQL debe permanecer encendido mientras se utiliza DentiFlow.

## 2. Crear la base de datos

En phpMyAdmin:

1. Seleccionar **Nueva** en el menú izquierdo.
2. Escribir `dentiflow_front`.
3. Seleccionar el cotejamiento `utf8mb4_unicode_ci`.
4. Presionar **Crear**.

También puede utilizarse la pestaña **SQL**:

```sql
CREATE DATABASE dentiflow_front
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

## 3. Configurar `.env`

Abrir una terminal de Visual Studio Code en `C:\xampp\htdocs\Dentiflow-Front`. Si `.env` no existe:

```powershell
Copy-Item .env.example .env
```

Abrir `.env` y comprobar esta sección:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dentiflow_front
DB_USERNAME=root
DB_PASSWORD=
```

| Variable | Función | Valor habitual en XAMPP |
| --- | --- | --- |
| `DB_CONNECTION` | Motor utilizado | `mysql` |
| `DB_HOST` | Dirección de MySQL | `127.0.0.1` |
| `DB_PORT` | Puerto de MySQL | `3306` |
| `DB_DATABASE` | Nombre de la base | `dentiflow_front` |
| `DB_USERNAME` | Usuario de MySQL | `root` |
| `DB_PASSWORD` | Contraseña de MySQL | Vacía normalmente |

`DB_PASSWORD=` significa que la contraseña está vacía. Si se configuró una contraseña para `root`, debe escribirse después del signo igual. No agregar espacios alrededor del signo igual ni compartir públicamente el archivo `.env`.

## 4. Limpiar la configuración

Después de modificar `.env`:

```powershell
php artisan config:clear
php artisan cache:clear
```

Si `php` no se reconoce:

```powershell
C:\xampp\php\php.exe artisan config:clear
C:\xampp\php\php.exe artisan cache:clear
```

## 5. Crear las tablas en una instalación nueva

Ejecutar:

```powershell
php artisan migrate --seed
```

Las cuentas de demostración se crean con contraseñas aleatorias. Para crear tus propias cuentas administrativas, configura las variables DENTIFLOW_ADMIN_EMAIL y DENTIFLOW_ADMIN_PASSWORD (y las de superadministración si las necesitas) en el .env local y ejecuta `php artisan dentiflow:provision-admins`. Elige correos distintos de las cuentas de demostración.

## 6. Importar una base existente

Si existe un respaldo `.sql` con pacientes, citas y usuarios:

1. Crear o seleccionar `dentiflow_front` en phpMyAdmin.
2. Abrir la pestaña **Importar**.
3. Seleccionar el archivo `.sql`.
4. Mantener el formato SQL y presionar **Importar**.
5. Esperar el mensaje de importación correcta.
6. Ejecutar:

```powershell
php artisan config:clear
php artisan migrate
```

`migrate` aplica únicamente los cambios que falten. No usar `migrate --seed` si los usuarios ya vienen en el respaldo. Guardar una copia de cualquier información existente antes de importar.

## 7. Verificar la conexión

```powershell
php artisan migrate:status
```

Si aparece la lista de migraciones, Laravel se conectó correctamente con MySQL. Para una comprobación adicional:

```powershell
php artisan tinker
```

Dentro de Tinker:

```php
DB::connection()->getPdo();
DB::table('users')->count();
```

El primer comando debe devolver una conexión PDO y el segundo la cantidad de usuarios. Escribir `exit` para salir.

Finalmente:

```powershell
php artisan serve
```

Abrir `http://127.0.0.1:8000`.

## 8. Usar un usuario exclusivo de MySQL

Para un sistema con información real es preferible no utilizar `root`. Desde la pestaña SQL de phpMyAdmin, cambiando la contraseña del ejemplo:

```sql
CREATE USER 'dentiflow_user'@'localhost'
IDENTIFIED BY 'CambiarPorUnaContrasenaSegura';

GRANT ALL PRIVILEGES ON dentiflow_front.*
TO 'dentiflow_user'@'localhost';

FLUSH PRIVILEGES;
```

Después actualizar `.env`:

```dotenv
DB_USERNAME=dentiflow_user
DB_PASSWORD=CambiarPorUnaContrasenaSegura
```

Y ejecutar `php artisan config:clear`.

## 9. Errores frecuentes

### `No connection could be made` o error 2002

MySQL está apagado o utiliza otro puerto. Iniciarlo en XAMPP, revisar el puerto y limpiar la configuración.

### `Unknown database 'dentiflow_front'`

La base no existe. Crearla en phpMyAdmin o corregir `DB_DATABASE`.

### `Access denied for user`

El usuario o la contraseña son incorrectos. Revisar `DB_USERNAME` y `DB_PASSWORD`. Si `root` no tiene contraseña, dejar exactamente `DB_PASSWORD=`.

### `could not find driver`

1. Abrir `C:\xampp\php\php.ini`.
2. Buscar `;extension=pdo_mysql`.
3. Quitar el punto y coma: `extension=pdo_mysql`.
4. Comprobar también `extension=mysqli`.
5. Guardar, reiniciar Apache y abrir una terminal nueva.

Comprobar las extensiones con:

```powershell
C:\xampp\php\php.exe -m
```

### MySQL utiliza el puerto 3307 u otro puerto

En XAMPP abrir **Config > my.ini** y buscar `port`. Si muestra `3307`, configurar:

```dotenv
DB_PORT=3307
```

Después ejecutar `php artisan config:clear`.

### Los cambios de `.env` no se aplican

```powershell
php artisan optimize:clear
```

### Las tablas ya existen

Revisar primero `php artisan migrate:status`. No usar `migrate:fresh`, ya que elimina todas las tablas y todos los pacientes, citas, usuarios y demás registros.

## 10. Resumen rápido

1. Iniciar Apache y MySQL en XAMPP.
2. Crear `dentiflow_front` en phpMyAdmin.
3. Configurar las variables `DB_*` en `.env`.
4. Ejecutar:

```powershell
cd C:\xampp\htdocs\Dentiflow-Front
php artisan config:clear
php artisan migrate --seed
php artisan migrate:status
php artisan serve
```

Con MySQL encendido y las variables `DB_*` correctas, DentiFlow estará conectado a la base de datos de XAMPP.
