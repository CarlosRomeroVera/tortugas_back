<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nombre')->required()->maxLength(150),
            TextInput::make('email')->label('Correo')->email()->maxLength(150),
            TextInput::make('phone')->label('Teléfono / WhatsApp')->tel()->maxLength(30),
            TextInput::make('country')->label('País (2 letras)')->length(2),
            Textarea::make('notes')->label('Notas')->columnSpanFull(),
        ]);
    }
}
