<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Unidades habilitadas para dar cada servicio (p. ej. El Cielo by Private Boat
 * solo en lanchas privadas, no en las de parasailing). Si un servicio no tiene
 * filas aquí, puede usar cualquier unidad activa de su tipo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_service', function (Blueprint $table) {
            $table->foreignUuid('service_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();
            $table->primary(['service_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_service');
    }
};
