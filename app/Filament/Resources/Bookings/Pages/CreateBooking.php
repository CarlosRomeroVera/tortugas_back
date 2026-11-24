<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Actions\Bookings\BookingData;
use App\Actions\Bookings\CreateBooking as CreateBookingAction;
use App\Enums\SalesChannel;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Schemas\BookingForm;
use App\Filament\Support\Domain;
use App\Models\Departure;
use App\Models\Service;
use App\Services\Availability;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Asistente: servicio y horario → cliente → detalles.
 * Los horarios salen de Availability; la reserva la crea CreateBooking,
 * que vuelve a comprobar el cupo con bloqueo de fila.
 */
class CreateBooking extends CreateRecord
{
    use HasWizard;

    protected static string $resource = BookingResource::class;

    /** Evita duplicados si el usuario hace doble clic en "Crear". */
    public string $idempotencyKey = '';

    public function mount(): void
    {
        $this->idempotencyKey = (string) Str::uuid();
        parent::mount();

        if ($departure = Departure::find(request()->query('departure'))) {
            $this->form->fill([
                'service_id' => $departure->service_id,
                'date' => $departure->starts_at->tz(LocalTime::zone())->toDateString(),
                'adults' => 1,
                'children' => 0,
                'slot' => 'd:'.$departure->id,
                'channel' => SalesChannel::Counter,
            ]);
        }
    }

    protected function getSteps(): array
    {
        return [
            Step::make('Servicio y horario')->columns(4)->schema([
                Select::make('service_id')->label('Servicio')->required()->live()->columnSpan(2)
                    ->options(Service::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->afterStateUpdated(fn (Set $set) => $set('slot', null)),
                DatePicker::make('date')->label('Fecha')->required()->live()->native(false)
                    ->default(fn () => CarbonImmutable::tomorrow(LocalTime::zone())->toDateString())
                    ->minDate(fn () => LocalTime::today())
                    ->afterStateUpdated(fn (Set $set) => $set('slot', null)),
                Grid::make(2)->columnSpan(1)->schema([
                    TextInput::make('adults')->label('Adultos')->integer()->minValue(1)->default(2)->required()->live(debounce: 400),
                    TextInput::make('children')->label('Niños')->integer()->minValue(0)->default(0)->live(debounce: 400),
                ]),
                Radio::make('slot')->label('Horarios disponibles')->required()->columnSpanFull()->columns(4)
                    ->options(fn (Get $get) => $this->slotOptions($get)['options'])
                    ->descriptions(fn (Get $get) => $this->slotOptions($get)['descriptions'])
                    ->disableOptionWhen(fn (string $value, Get $get) => in_array($value, $this->slotOptions($get)['disabled'], true))
                    ->helperText(fn (Get $get) => $get('service_id') && empty($this->slotOptions($get)['options'])
                        ? 'No hay horarios ese día. Prueba otra fecha o revisa la agenda de la flota.' : null)
                    ->validationMessages(['required' => 'Elige un horario.']),
                TextEntry::make('total')->label('Total')->columnSpanFull()
                    ->state(function (Get $get) {
                        $service = Service::find($get('service_id'));
                        if (! $service) {
                            return '—';
                        }
                        $cents = $service->priceFor((int) $get('adults'), (int) $get('children'));

                        return '$'.number_format($cents / 100, 2).' '.$service->currency->value;
                    }),
            ]),
            Step::make('Cliente')->columns(2)->schema([
                TextInput::make('customer_name')->label('Nombre')->required()->maxLength(150),
                TextInput::make('customer_email')->label('Correo')->email()->maxLength(150)
                    ->helperText('Si ya existe un cliente con este correo, se reutiliza.'),
                TextInput::make('customer_phone')->label('Teléfono / WhatsApp')->tel()->maxLength(30),
                TextInput::make('customer_country')->label('País (2 letras)')->length(2)->placeholder('MX, US, CA…'),
            ]),
            Step::make('Detalles')->columns(2)->schema(BookingForm::detailFields()),
        ];
    }

    /** @return array{options: array<string,string>, descriptions: array<string,string>, disabled: list<string>} */
    private function slotOptions(Get $get): array
    {
        $empty = ['options' => [], 'descriptions' => [], 'disabled' => []];
        $service = Service::find($get('service_id'));
        $pax = max(1, (int) $get('adults') + (int) $get('children'));
        if (! $service || ! $get('date')) {
            return $empty;
        }

        $slots = app(Availability::class)->for($service, $get('date'), $pax);
        $result = $empty;

        foreach ($slots as $slot) {
            if ($service->is_shared) {
                $key = 'd:'.$slot['departure_id'];
                $result['options'][$key] = "{$slot['local_time']} · {$slot['asset']}";
                $result['descriptions'][$key] = "{$slot['seats_left']} de {$slot['capacity']} lugares libres";
                if (! $slot['available']) {
                    $result['disabled'][] = $key;
                }
            } else {
                $key = 'p:'.$slot['starts_at'];
                $names = collect($slot['assets'])->pluck('name');
                $result['options'][$key] = $slot['local_time'];
                $result['descriptions'][$key] = $names->count().' libre'.($names->count() > 1 ? 's' : '').': '.$names->take(3)->join(', ');
            }
        }

        return $result;
    }

    protected function handleRecordCreation(array $data): Model
    {
        [$type, $value] = explode(':', $data['slot'], 2);

        return Domain::run(fn () => app(CreateBookingAction::class)->handle(new BookingData(
            customer: array_filter([
                'name' => $data['customer_name'],
                'email' => $data['customer_email'] ?? null,
                'phone' => $data['customer_phone'] ?? null,
                'country' => isset($data['customer_country']) ? strtoupper($data['customer_country']) : null,
            ]),
            adults: (int) $data['adults'],
            children: (int) ($data['children'] ?? 0),
            channel: $data['channel'] instanceof SalesChannel ? $data['channel'] : SalesChannel::from($data['channel']),
            departureId: $type === 'd' ? $value : null,
            serviceId: $type === 'p' ? $data['service_id'] : null,
            startsAt: $type === 'p' ? CarbonImmutable::parse($value)->utc() : null,
            agencyReference: $data['agency_reference'] ?? null,
            cruiseShip: $data['cruise_ship'] ?? null,
            allAboardAt: filled($data['all_aboard_at'] ?? null) ? CarbonImmutable::parse($data['all_aboard_at'])->utc() : null,
            pickupLocationId: $data['pickup_location_id'] ?? null,
            notes: $data['notes'] ?? null,
            idempotencyKey: 'panel-'.$this->idempotencyKey,
        )));
    }

    protected function getRedirectUrl(): string
    {
        return BookingResource::getUrl('view', ['record' => $this->record]);
    }
}
