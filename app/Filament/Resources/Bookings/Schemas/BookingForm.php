<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Enums\SalesChannel;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/** Edición de una reserva existente: solo datos que no afectan cupo ni precio. */
class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components(self::detailFields());
    }

    /** Campos compartidos con el asistente de creación. */
    public static function detailFields(): array
    {
        return [
            Section::make('Venta')->columns(2)->columnSpanFull()->components([
                Select::make('channel')->label('Canal de venta')->options(SalesChannel::class)->required()->live()->default(SalesChannel::Counter),
                TextInput::make('agency_reference')->label('Voucher de agencia')->maxLength(100)
                    ->visible(fn (Get $get) => in_array($get('channel'), [SalesChannel::Agency, SalesChannel::Agency->value], true)),
            ]),
            Section::make('Crucero y recogida')->columns(3)->columnSpanFull()->components([
                TextInput::make('cruise_ship')->label('Crucero')->maxLength(100)->placeholder('Icon of the Seas'),
                DateTimePicker::make('all_aboard_at')->label('All aboard')->seconds(false)
                    ->helperText('Se avisará si el tour termina muy cerca de esta hora.'),
                Select::make('pickup_location_id')->label('Recoger en')->relationship('pickupLocation', 'name')->searchable()->preload()
                    ->placeholder('Llega por su cuenta'),
            ]),
            Textarea::make('notes')->label('Notas')->columnSpanFull(),
        ];
    }
}
