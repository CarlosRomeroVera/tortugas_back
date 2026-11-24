<?php

namespace App\Filament\Resources\MaintenanceOrders\Actions;

use App\Actions\Maintenance\CloseMaintenance;
use App\Enums\AssetKind;
use App\Enums\MaintenanceStatus;
use App\Filament\Support\Domain;
use App\Models\MaintenanceOrder;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

class CloseMaintenanceAction
{
    public static function make(): Action
    {
        return Action::make('close')->label('Cerrar orden')->icon(Heroicon::OutlinedCheckCircle)->color('success')
            ->visible(fn (MaintenanceOrder $record) => in_array($record->status, [MaintenanceStatus::Scheduled, MaintenanceStatus::InProgress], true))
            ->modalDescription('Si termina antes de lo estimado, el resto del horario vuelve a estar disponible.')
            ->schema(fn (MaintenanceOrder $record) => [
                TextInput::make('reading')->numeric()->minValue(0)
                    ->label($record->asset->kind === AssetKind::Boat ? 'Horas de motor al cerrar' : 'Kilometraje al cerrar')
                    ->helperText('Reinicia el contador del siguiente servicio preventivo.')
                    ->default($record->asset->currentReading()),
                TextInput::make('cost')->label('Costo total')->numeric()->minValue(0)->prefix('$'),
            ])
            ->action(fn (MaintenanceOrder $record, array $data) => Domain::run(
                fn () => app(CloseMaintenance::class)->handle(
                    $record,
                    filled($data['reading'] ?? null) ? (float) $data['reading'] : null,
                    Domain::toCents($data['cost'] ?? null),
                ),
                success: "Orden {$record->code} cerrada",
            ));
    }
}
