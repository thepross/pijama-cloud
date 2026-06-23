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
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_qr')->unique()->nullable();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('color')->nullable();
            $table->string('talla')->nullable();
            $table->string('genero')->nullable();
            $table->string('marca')->nullable();
            $table->string('material')->nullable();
            $table->decimal('precio_compra', 10, 2);
            $table->decimal('precio_venta', 10, 2);
            $table->integer('stock');
            $table->integer('stock_minimo')->default(0);
            $table->string('categoria');
            $table->string('foto')->nullable();
            $table->string('state')->default('activo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
