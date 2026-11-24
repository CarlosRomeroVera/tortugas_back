<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Todas las fechas se guardan como DATETIME en UTC (APP_TIMEZONE=UTC).
 * No se usa TIMESTAMP porque su rango termina en 2038.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique(); // SAL-20261115-07
            $table->foreignUuid('service_id')->constrained();
            $table->foreignUuid('asset_id')->constrained();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('departed_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->unsignedSmallInteger('capacity');
            $table->string('status', 20)->default('scheduled'); // DepartureStatus
            $table->foreignUuid('current_stage_id')->nullable()->constrained('service_stages')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'status']);
        });

        Schema::create('departure_crew', function (Blueprint $table) {
            $table->foreignUuid('departure_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('staff_id')->constrained('staff');
            $table->string('role', 20); // StaffRole en esta salida
            $table->primary(['departure_id', 'staff_id']);
        });

        Schema::create('maintenance_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique(); // OT-0142
            $table->foreignUuid('asset_id')->constrained();
            $table->string('type', 20);   // MaintenanceType
            $table->string('status', 20)->default('scheduled'); // MaintenanceStatus
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->decimal('reading_at_service', 10, 1)->nullable();
            $table->unsignedInteger('cost_cents')->nullable();
            $table->timestamps();
        });

        // Todo lo que ocupa un recurso: salida, mantenimiento o bloqueo manual.
        // MySQL no tiene restricciones de exclusión: el no traslape lo garantiza
        // App\Actions\Allocations\AllocateAsset con un bloqueo de fila.
        Schema::create('allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('asset_id')->constrained();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('kind', 20);   // AllocationKind
            $table->nullableUuidMorphs('allocatable'); // Departure o MaintenanceOrder
            $table->string('status', 20)->default('active'); // AllocationStatus
            $table->string('reason')->nullable(); // para bloqueos manuales
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['asset_id', 'status', 'starts_at', 'ends_at'], 'allocations_overlap_idx');
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique(); // R-8812
            $table->foreignUuid('departure_id')->constrained();
            $table->foreignUuid('customer_id')->constrained();
            $table->string('channel', 20);  // SalesChannel
            $table->string('agency_reference')->nullable();
            $table->unsignedSmallInteger('adults');
            $table->unsignedSmallInteger('children')->default(0);
            $table->unsignedSmallInteger('pax');
            $table->string('status', 20)->default('pending'); // BookingStatus
            $table->unsignedInteger('total_cents');
            $table->char('currency', 3);
            $table->string('cruise_ship')->nullable();
            $table->dateTime('all_aboard_at')->nullable();
            $table->foreignUuid('pickup_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['departure_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->constrained();
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3);
            $table->decimal('exchange_rate', 12, 6)->default(1); // pesos por 1 USD el día del pago
            $table->unsignedInteger('amount_mxn_cents');
            $table->string('method', 20);  // PaymentMethod
            $table->string('status', 20)->default('paid'); // PaymentStatus
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('allocations');
        Schema::dropIfExists('maintenance_orders');
        Schema::dropIfExists('departure_crew');
        Schema::dropIfExists('departures');
    }
};
