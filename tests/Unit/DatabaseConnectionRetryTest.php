<?php

namespace Tests\Unit;

use App\Support\DatabaseConnectionRetry;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Tests\TestCase;

class DatabaseConnectionRetryTest extends TestCase
{
    public function test_mysql_connection_is_non_persistent_and_has_explicit_timeouts(): void
    {
        $options = config('database.connections.mysql.options');

        $this->assertSame(5, $options[PDO::ATTR_TIMEOUT]);
        $this->assertFalse($options[PDO::ATTR_PERSISTENT]);
        $this->assertSame(
            'SET SESSION wait_timeout = 600, interactive_timeout = 600',
            $options[PDO::MYSQL_ATTR_INIT_COMMAND],
        );
    }

    public function test_it_reconnects_and_retries_once_when_mysql_has_gone_away(): void
    {
        DB::shouldReceive('disconnect')->once();
        DB::shouldReceive('reconnect')->once();

        $attempts = 0;
        $result = (new DatabaseConnectionRetry)->run(function () use (&$attempts) {
            $attempts++;

            if ($attempts === 1) {
                throw new PDOException('SQLSTATE[HY000] [2006] MySQL server has gone away');
            }

            return 'connected';
        });

        $this->assertSame('connected', $result);
        $this->assertSame(2, $attempts);
    }

    public function test_it_does_not_retry_an_unrelated_database_error(): void
    {
        DB::shouldReceive('disconnect')->never();
        DB::shouldReceive('reconnect')->never();

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('Duplicate entry');

        (new DatabaseConnectionRetry)->run(
            fn () => throw new PDOException('SQLSTATE[23000]: Duplicate entry')
        );
    }
}
