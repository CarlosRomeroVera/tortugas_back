<?php

namespace App\Filament\Resources\Assets\Actions;

use App\Actions\Allocations\AllocateAsset;
use App\Enums\AllocationKind;
use App\Filament\Support\Domain;
use App\Models\Asset;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/** Bloqueo manual de agenda (evento privado, boda, reserva telefónica sin confirmar…). */
class BlockAssetAction
{
    public static function make(): Action
    {
        return Action::make('block')
            ->label('Bloquear horario')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->modalWidth('lg')
            ->schema([
                DateTimePicker::make('starts_at')->label('Desde')->seconds(false)->required()->default(now()->ceilHour()),
                DateTimePicker::make('ends_at')->label('Hasta')->seconds(false)->required()->after('starts_at')->default(now()->ceilHour()->addHours(2)),
                TextInput::make('reason')->label('Motivo')->required()->maxLength(150),
            ])
            ->action(fn (Asset $record, array $data) => Domain::run(
                fn () => app(AllocateAsset::class)->handle(
                    $record,
                    CarbonImmutable::parse($data['starts_at'])->utc(),
                    CarbonImmutable::parse($data['ends_at'])->utc(),
                    AllocationKind::Block,
                    reason: $data['reason'],
                ),
                success: "{$record->name} bloqueada",
            ));
    }
}
