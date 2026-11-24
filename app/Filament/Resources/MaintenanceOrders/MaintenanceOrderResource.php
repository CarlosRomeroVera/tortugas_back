<?php

namespace App\Filament\Resources\MaintenanceOrders;

use App\Filament\Resources\MaintenanceOrders\Pages\CreateMaintenanceOrder;
use App\Filament\Resources\MaintenanceOrders\Pages\EditMaintenanceOrder;
use App\Filament\Resources\MaintenanceOrders\Pages\ListMaintenanceOrders;
use App\Filament\Resources\MaintenanceOrders\Pages\ViewMaintenanceOrder;
use App\Filament\Resources\MaintenanceOrders\Schemas\MaintenanceOrderForm;
use App\Filament\Resources\MaintenanceOrders\Schemas\MaintenanceOrderInfolist;
use App\Filament\Resources\MaintenanceOrders\Tables\MaintenanceOrdersTable;
use App\Models\MaintenanceOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MaintenanceOrderResource extends Resource
{
    protected static ?string $model = MaintenanceOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Flota';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Mantenimiento';

    protected static ?string $modelLabel = 'orden de mantenimiento';

    protected static ?string $pluralModelLabel = 'órdenes de mantenimiento';

    protected static ?string $slug = 'mantenimiento';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return MaintenanceOrderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MaintenanceOrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaintenanceOrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceOrders::route('/'),
            'create' => CreateMaintenanceOrder::route('/create'),
            'view' => ViewMaintenanceOrder::route('/{record}'),
            'edit' => EditMaintenanceOrder::route('/{record}/edit'),
        ];
    }
}
