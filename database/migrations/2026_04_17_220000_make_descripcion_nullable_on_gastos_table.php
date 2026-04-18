<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->text('descripcion')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->text('descripcion')->nullable(false)->change();
        });
    }
};
