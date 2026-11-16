<?php

namespace App\Filament\Resources\Staff\Schemas;

use App\Enums\StaffRole;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StaffForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nombre')->required()->maxLength(150),
            Select::make('role')->label('Puesto')->options(StaffRole::class)->required(),
            TextInput::make('phone')->label('Teléfono')->tel()->maxLength(30),
            Select::make('user_id')->label('Usuario de la app')->relationship('user', 'email')->searchable()->preload()
                ->helperText('Necesario para que capitanes y choferes entren a la app.'),
            TextInput::make('license_number')->label('Licencia / libreta de mar')->maxLength(50),
            DatePicker::make('license_expires_on')->label('Vence'),
            Toggle::make('is_active')->label('Activo')->default(true),
        ]);
    }
}
