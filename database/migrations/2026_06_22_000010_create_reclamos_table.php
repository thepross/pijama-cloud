<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reclamos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_cliente')->constrained('usuarios')->onDelete('cascade');
            $table->foreignId('id_pedido')->nullable()->constrained('pedidos')->onDelete('set null');
            $table->string('tipo_reclamo');
            $table->text('descripcion');
            $table->date('fecha_reclamo');
            $table->date('fecha_respuesta')->nullable();
            $table->text('respuesta')->nullable();
            $table->string('estado_reclamo')->default('pendiente');
            $table->string('state')->default('activo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reclamos');
    }
};
