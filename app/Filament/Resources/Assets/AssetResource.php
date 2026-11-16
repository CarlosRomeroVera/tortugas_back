<?php

namespace App\Filament\Resources\Assets;

use App\Enums\AssetKind;
use App\Filament\Resources\Assets\RelationManagers\AllocationsRelationManager;
use App\Filament\Resources\Assets\RelationManagers\MaintenanceOrdersRelationManager;
use App\Filament\Resources\Assets\Schemas\AssetForm;
use App\Filament\Resources\Assets\Schemas\AssetInfolist;
use App\Filament\Resources\Assets\Tables\AssetsTable;
use App\Models\Asset;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Base de Embarcaciones y Vehículos: mismo modelo (Asset), filtrado por tipo.
 * Las subclases solo definen $kind, etiquetas y páginas.
 */
abstract class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static string|UnitEnum|null $navigationGroup = 'Flota';

    protected static ?string $recordTitleAttribute = 'name';

    abstract public static function kind(): AssetKind;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('kind', static::kind());
    }

    public static function form(Schema $schema): Schema
    {
        return AssetForm::configure($schema, static::kind());
    }

    public static function infolist(Schema $schema): Schema
    {
        return AssetInfolist::configure($schema, static::kind());
    }

    public static function table(Table $table): Table
    {
        return AssetsTable::configure($table, static::kind());
    }

    public static function getRelations(): array
    {
        return [
            AllocationsRelationManager::class,
            MaintenanceOrdersRelationManager::class,
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
