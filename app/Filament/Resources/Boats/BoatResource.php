<?php

namespace App\Filament\Resources\Boats;

use App\Enums\AssetKind;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Boats\Pages\CreateBoat;
use App\Filament\Resources\Boats\Pages\EditBoat;
use App\Filament\Resources\Boats\Pages\ListBoats;
use App\Filament\Resources\Boats\Pages\ViewBoat;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class BoatResource extends AssetResource
{
    protected static ?string $slug = 'embarcaciones';

    protected static ?string $modelLabel = 'embarcación';

    protected static ?string $pluralModelLabel = 'embarcaciones';

    protected static ?string $navigationLabel = 'Embarcaciones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static ?int $navigationSort = 1;

    public static function kind(): AssetKind
    {
        return AssetKind::Boat;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBoats::route('/'),
            'create' => CreateBoat::route('/create'),
            'view' => ViewBoat::route('/{record}'),
            'edit' => EditBoat::route('/{record}/edit'),
        ];
    }
}
