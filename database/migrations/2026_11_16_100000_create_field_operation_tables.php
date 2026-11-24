<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Operación en campo desde la app del capitán y el chofer.
 * Fechas en DATETIME UTC, igual que el resto del esquema.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Abordaje (capitán) o recogida (chofer) de cada reserva.
        Schema::table('bookings', function (Blueprint $table) {
            $table->dateTime('checked_in_at')->nullable()->after('status');
            $table->foreignId('checked_in_by')->nullable()->after('checked_in_at')->constrained('users')->nullOnDelete();
        });

        // Fallas que reporta la tripulación. Coordinación decide si abre una orden de mantenimiento.
        Schema::create('fault_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('asset_id')->constrained();
            $table->foreignUuid('departure_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('severity', 20); // FaultSeverity
            $table->string('status', 20)->default('open'); // FaultStatus
            $table->text('description');
            $table->dateTime('reported_at'); // hora del teléfono
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['asset_id', 'status']);
        });

        // Bitácora de lo que llega de la app. Solo crece. client_uuid hace la sincronización idempotente.
        Schema::create('field_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_uuid')->unique();
            $table->string('type', 20); // FieldEventType
            $table->string('outcome', 20); // applied | ignored
            $table->string('message')->nullable();
            $table->foreignUuid('departure_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('service_stage_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('fault_report_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('reading', 10, 1)->nullable(); // horómetro u odómetro
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->dateTime('occurred_at'); // hora del teléfono
            $table->dateTime('received_at'); // hora del servidor

            $table->index(['departure_id', 'type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_events');
        Schema::dropIfExists('fault_reports');
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checked_in_by');
            $table->dropColumn('checked_in_at');
        });
    }
};
