<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Embarcaciones y vehículos: un mismo tipo de recurso.
        Schema::create('assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 20);                 // AssetKind
            $table->string('name');
            $table->string('registration')->nullable(); // matrícula o placas
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('capacity');
            $table->string('status', 20)->default('active'); // AssetStatus
            $table->decimal('engine_hours', 10, 1)->nullable();   // embarcaciones
            $table->unsignedInteger('odometer_km')->nullable();   // vehículos
            $table->unsignedInteger('service_interval')->nullable(); // horas o km entre servicios
            $table->decimal('last_service_reading', 10, 1)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['kind', 'name']);
            $table->index(['kind', 'status']);
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('type', 20); // LocationType
            $table->string('address')->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('pickup_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category', 20);     // ServiceCategory
            $table->string('asset_kind', 20);   // AssetKind que usa
            $table->boolean('is_shared')->default(true); // compartida (cupo) o privada (recurso completo)
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('buffer_minutes')->default(0); // limpieza / regreso entre salidas
            $table->unsignedSmallInteger('default_capacity')->nullable();
            $table->unsignedInteger('price_adult_cents');
            $table->unsignedInteger('price_child_cents')->nullable();
            $table->char('currency', 3)->default('MXN');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('service_stages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('name');
            $table->boolean('requires_reading')->default(false); // pide horómetro / km
            $table->timestamps();

            $table->index(['service_id', 'position']); // sin unique: Filament reordena fila por fila
        });

        // Horarios fijos de las salidas compartidas.
        Schema::create('service_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->nullable()->constrained();
            $table->unsignedTinyInteger('weekday'); // 0 = domingo … 6 = sábado
            $table->time('departs_at');             // hora local de Cozumel
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->index(['service_id', 'weekday']);
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('role', 20); // StaffRole
            $table->string('phone')->nullable();
            $table->string('license_number')->nullable();
            $table->date('license_expires_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('country', 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('service_schedules');
        Schema::dropIfExists('service_stages');
        Schema::dropIfExists('services');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('assets');
    }
};
