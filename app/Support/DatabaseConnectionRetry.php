<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB;
use PDOException;

class DatabaseConnectionRetry
{
    /** Run a database operation and retry it once on a lost MySQL connection. */
    public function run(Closure $operation): mixed
    {
        try {
            return $operation();
        } catch (PDOException $exception) {
            if (! $this->wasConnectionLost($exception)) {
                throw $exception;
            }

            DB::disconnect();
            DB::reconnect();

            return $operation();
        }
    }

    public function wasConnectionLost(PDOException $exception): bool
    {
        $message = strtolower($exception->getMessage());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return in_array($driverCode, [2002, 2003, 2006, 2013, 2055], true)
            || str_contains($message, 'mysql server has gone away')
            || str_contains($message, 'server has gone away')
            || str_contains($message, 'lost connection to mysql server')
            || str_contains($message, 'error while sending query packet')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'no connection could be made')
            || str_contains($message, 'connection timed out');
    }
}
