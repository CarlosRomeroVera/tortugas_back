<?php

namespace App\Filament\Resources\Assets\Actions;

use App\Actions\Maintenance\ScheduleMaintenance;
use App\Enums\MaintenanceType;
use App\Filament\Support\Domain;
use App\Models\Asset;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

class ScheduleMaintenanceAction
{
    public static function make(): Action
    {
        return Action::make('maintenance')
            ->label('Programar mantenimiento')
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->color('warning')
            ->modalWidth('lg')
            ->schema([
                Select::make('type')->label('Tipo')->options(MaintenanceType::class)->default(MaintenanceType::Preventive)->required(),
                TextInput::make('title')->label('Trabajo')->required()->maxLength(150),
                DateTimePicker::make('starts_at')->label('Desde')->seconds(false)->required(),
                DateTimePicker::make('ends_at')->label('Hasta (estimado)')->seconds(false)->required()->after('starts_at'),
                Textarea::make('description')->label('Detalle'),
            ])
            ->action(fn (Asset $record, array $data) => Domain::run(
                fn () => app(ScheduleMaintenance::class)->handle(
                    $record,
                    MaintenanceType::from($data['type'] instanceof MaintenanceType ? $data['type']->value : $data['type']),
                    $data['title'],
                    CarbonImmutable::parse($data['starts_at'])->utc(),
                    CarbonImmutable::parse($data['ends_at'])->utc(),
                    $data['description'] ?? null,
                ),
                success: 'Mantenimiento programado',
            ));
    }
}
