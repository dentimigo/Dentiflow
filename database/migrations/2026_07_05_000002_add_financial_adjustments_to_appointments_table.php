<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->decimal('descuento', 10, 2)->default(0)->after('color');
            $table->string('procedimiento_extra')->nullable()->after('descuento');
            $table->decimal('costo_extra', 10, 2)->default(0)->after('procedimiento_extra');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['descuento', 'procedimiento_extra', 'costo_extra']);
        });
    }
};
