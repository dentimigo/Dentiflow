<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('treatments') || ! $this->hasIndex('treatments', 'treatments_tratamiento_unique')) {
            return;
        }

        Schema::table('treatments', function ($table) {
            $table->dropUnique('treatments_tratamiento_unique');
        });
    }

    public function down(): void
    {
        // No se restaura el indice: el catalogo y los tratamientos realizados pueden compartir nombre.
    }

    private function hasIndex(string $table, string $index): bool
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return $connection->table('information_schema.statistics')
                ->where('table_schema', $connection->getDatabaseName())
                ->where('table_name', $table)
                ->where('index_name', $index)
                ->exists();
        }

        if ($driver === 'sqlite') {
            return collect($connection->select("PRAGMA index_list('{$table}')"))
                ->contains(fn ($row) => ($row->name ?? null) === $index);
        }

        return false;
    }
};
