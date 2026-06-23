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
        Schema::create('envios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_pedido')->constrained('pedidos')->onDelete('cascade');
            $table->foreignId('id_distribuidor')->nullable()->constrained('usuarios')->onDelete('set null');
            $table->string('direccion_entrega');
            $table->date('fecha_salida')->nullable();
            $table->date('fecha_entrega')->nullable();
            $table->string('estado_envio')->default('pendiente');
            $table->text('observacion')->nullable();
            $table->string('ruta')->nullable();
            $table->string('state')->default('activo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('envios');
    }
};
