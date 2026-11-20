<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class AdminSystemController extends Controller
{
    public function index()
    {
        $databaseOk = true;

        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $databaseOk = false;
        }

        $databasePath = config('database.default') === 'sqlite'
            ? config('database.connections.sqlite.database')
            : null;
        $storagePath = storage_path();
        $freeBytes = @disk_free_space($storagePath);

        return view('admin.system', [
            'services' => [
                ['Base de datos', $databaseOk ? 'Disponible' : 'Sin conexión', $databaseOk],
                ['Almacenamiento', is_writable($storagePath) ? 'Escritura disponible' : 'Solo lectura', is_writable($storagePath)],
                ['Correo', strtoupper(config('mail.default')), config('mail.default') !== 'log'],
                ['Caché', strtoupper(config('cache.default')), true],
                ['Cola de tareas', strtoupper(config('queue.default')), true],
            ],
            'metrics' => [
                'sessions' => Schema::hasTable('sessions') ? DB::table('sessions')->count() : null,
                'failedJobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
                'audits' => AuditLog::count(),
                'freeSpace' => $freeBytes !== false ? $this->formatBytes($freeBytes) : 'No disponible',
            ],
            'runtime' => [
                'environment' => app()->environment(),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'databaseDriver' => strtoupper(config('database.default')),
                'databaseSize' => $databasePath && is_file($databasePath) ? $this->formatBytes(filesize($databasePath)) : 'Gestionada externamente',
                'sessionDriver' => strtoupper(config('session.driver')),
            ],
            'cache' => $this->cacheMetrics(),
            'backupAvailable' => $this->backupAvailable(),
            'backupDriver' => strtoupper(config('database.default')),
        ]);
    }

    public function clearCache(Request $request)
    {
        $before = $this->cacheMetrics();
        Artisan::call('optimize:clear');
        clearstatcache();
        $after = $this->cacheMetrics();
        $freedBytes = max(0, $before['bytes'] - $after['bytes']);

        AuditLog::record('admin.system.cache_cleared', 'system', [
            'before_bytes' => $before['bytes'],
            'after_bytes' => $after['bytes'],
            'freed_bytes' => $freedBytes,
        ], $request, true);

        return back()->with(
            'success',
            'La caché se limpió correctamente. Se liberaron '.$this->formatBytes($freedBytes).'. Quedan '.$after['formatted'].'.'
        );
    }

    public function downloadBackup(Request $request)
    {
        $driver = config('database.default');

        if ($driver === 'sqlite') {
            $databasePath = config('database.connections.sqlite.database');
            abort_unless($databasePath && is_file($databasePath), 404);

            AuditLog::record('admin.system.backup_downloaded', 'database', [
                'driver' => 'sqlite',
            ], $request, true);

            return response()->download(
                $databasePath,
                'dentiflow-respaldo-'.now()->format('Y-m-d-His').'.sqlite',
                ['Content-Type' => 'application/octet-stream']
            );
        }

        abort_unless(in_array($driver, ['mysql', 'mariadb'], true), 404);

        $connection = config("database.connections.{$driver}");
        $binary = $this->mysqlDumpBinary();

        if (! $binary) {
            return back()->withErrors([
                'backup' => 'No se encontró mysqldump. Configura MYSQLDUMP_PATH con la ruta de la herramienta de respaldo.',
            ]);
        }

        $database = (string) ($connection['database'] ?? '');
        if ($database === '') {
            return back()->withErrors(['backup' => 'La base de datos no tiene un nombre configurado.']);
        }

        $backupDirectory = storage_path('app/backups');
        File::ensureDirectoryExists($backupDirectory);
        $safeDatabase = Str::slug($database, '_') ?: 'database';
        $filename = 'dentiflow-'.$safeDatabase.'-'.now()->format('Y-m-d-His').'.sql';
        $backupPath = $backupDirectory.DIRECTORY_SEPARATOR.$filename;

        $command = [
            $binary,
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--default-character-set=utf8mb4',
            '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? '3306'),
            '--user='.(string) ($connection['username'] ?? 'root'),
            '--result-file='.$backupPath,
            $database,
        ];

        $password = (string) ($connection['password'] ?? '');
        $environment = $this->mysqlDumpEnvironment($password);
        $process = new Process($command, base_path(), $environment);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($backupPath) || filesize($backupPath) === 0) {
            File::delete($backupPath);

            report(new \RuntimeException('MySQL backup failed: '.$process->getErrorOutput()));

            return back()->withErrors([
                'backup' => 'No fue posible generar el respaldo de MySQL. Revisa las credenciales y permisos de la base de datos.',
            ]);
        }

        AuditLog::record('admin.system.backup_downloaded', 'database', [
            'driver' => $driver,
            'database' => $database,
            'size' => filesize($backupPath),
        ], $request, true);

        return response()->download($backupPath, $filename, [
            'Content-Type' => 'application/sql',
        ])->deleteFileAfterSend(true);
    }

    private function backupAvailable(): bool
    {
        $driver = config('database.default');

        if ($driver === 'sqlite') {
            $path = config('database.connections.sqlite.database');

            return (bool) ($path && is_file($path));
        }

        return in_array($driver, ['mysql', 'mariadb'], true) && $this->mysqlDumpBinary() !== null;
    }

    private function mysqlDumpBinary(): ?string
    {
        $driver = config('database.default');
        $configured = config("database.connections.{$driver}.dump_binary_path");

        if (is_string($configured) && $configured !== '') {
            return is_file($configured) ? $configured : null;
        }

        $xamppBinary = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
        if (PHP_OS_FAMILY === 'Windows' && is_file($xamppBinary)) {
            return $xamppBinary;
        }

        return PHP_OS_FAMILY === 'Windows' ? null : 'mysqldump';
    }

    private function mysqlDumpEnvironment(string $password): ?array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return $password !== '' ? ['MYSQL_PWD' => $password] : null;
        }

        // Apache en XAMPP puede iniciar PHP sin las variables que Winsock necesita.
        // mysqldump entonces falla con el error 10106 aunque MySQL esté disponible.
        $systemRoot = (string) ($_SERVER['SystemRoot'] ?? getenv('SystemRoot') ?: 'C:\\Windows');
        $environment = [
            'SystemRoot' => $systemRoot,
            'WINDIR' => (string) ($_SERVER['WINDIR'] ?? getenv('WINDIR') ?: $systemRoot),
            'COMSPEC' => (string) ($_SERVER['COMSPEC'] ?? getenv('COMSPEC') ?: $systemRoot.'\\System32\\cmd.exe'),
            'PATH' => (string) ($_SERVER['PATH'] ?? getenv('PATH') ?: $systemRoot.'\\System32'),
            'TEMP' => (string) ($_SERVER['TEMP'] ?? getenv('TEMP') ?: sys_get_temp_dir()),
            'TMP' => (string) ($_SERVER['TMP'] ?? getenv('TMP') ?: sys_get_temp_dir()),
        ];

        if ($password !== '') {
            $environment['MYSQL_PWD'] = $password;
        }

        return $environment;
    }

    private function cacheMetrics(): array
    {
        $bytes = 0;
        $files = 0;
        $entries = 0;

        foreach ([storage_path('framework/cache'), storage_path('framework/views')] as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                if ($file->getFilename() === '.gitignore') {
                    continue;
                }

                $bytes += $file->getSize();
                $files++;
            }
        }

        $bootstrapCache = base_path('bootstrap/cache');
        if (is_dir($bootstrapCache)) {
            foreach (File::files($bootstrapCache) as $file) {
                if ($file->getFilename() === '.gitignore') {
                    continue;
                }

                $bytes += $file->getSize();
                $files++;
            }
        }

        if (config('cache.default') === 'database' && Schema::hasTable('cache')) {
            $entries = DB::table('cache')->count();
            $bytes += (int) DB::table('cache')
                ->selectRaw('COALESCE(SUM(LENGTH(value)), 0) as bytes')
                ->value('bytes');
        }

        return [
            'bytes' => $bytes,
            'formatted' => $this->formatBytes($bytes),
            'files' => $files,
            'entries' => $entries,
        ];
    }

    private function formatBytes(int|float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return number_format($bytes, $index === 0 ? 0 : 2).' '.$units[$index];
    }
}
