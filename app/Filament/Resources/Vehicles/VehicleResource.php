<?php

namespace App\Filament\Resources\Vehicles;

use App\Enums\AssetKind;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Resources\Vehicles\Pages\ListVehicles;
use App\Filament\Resources\Vehicles\Pages\ViewVehicle;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class VehicleResource extends AssetResource
{
    protected static ?string $slug = 'vehiculos';

    protected static ?string $modelLabel = 'vehículo';

    protected static ?string $pluralModelLabel = 'vehículos';

    protected static ?string $navigationLabel = 'Vehículos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 2;

    public static function kind(): AssetKind
    {
        return AssetKind::Vehicle;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicles::route('/'),
            'create' => CreateVehicle::route('/create'),
            'view' => ViewVehicle::route('/{record}'),
            'edit' => EditVehicle::route('/{record}/edit'),
        ];
    }
}
