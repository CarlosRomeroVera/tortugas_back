<?php

namespace App\Filament\Resources\Departures;

use App\Filament\Resources\Departures\Pages\CreateDeparture;
use App\Filament\Resources\Departures\Pages\ListDepartures;
use App\Filament\Resources\Departures\Pages\ViewDeparture;
use App\Filament\Resources\Departures\RelationManagers\BookingsRelationManager;
use App\Filament\Resources\Departures\Schemas\DepartureForm;
use App\Filament\Resources\Departures\Schemas\DepartureInfolist;
use App\Filament\Resources\Departures\Tables\DeparturesTable;
use App\Models\Departure;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DepartureResource extends Resource
{
    protected static ?string $model = Departure::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Salidas';

    protected static ?string $modelLabel = 'salida';

    protected static ?string $pluralModelLabel = 'salidas';

    protected static ?string $slug = 'salidas';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return DepartureForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DepartureInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeparturesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [BookingsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDepartures::route('/'),
            'create' => CreateDeparture::route('/create'),
            'view' => ViewDeparture::route('/{record}'),
        ];
    }
}
