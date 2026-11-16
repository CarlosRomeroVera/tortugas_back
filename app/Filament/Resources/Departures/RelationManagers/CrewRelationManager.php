<?php

namespace App\Filament\Resources\Departures\RelationManagers;

use App\Actions\Crew\AssignCrew;
use App\Enums\DepartureStatus;
use App\Enums\StaffRole;
use App\Filament\Support\Domain;
use App\Models\Departure;
use App\Models\Staff;
use Filament\Actions\Action;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Capitán, chofer y guías de la salida. Son quienes la ven y la operan desde la app. */
class CrewRelationManager extends RelationManager
{
    protected static string $relationship = 'crew';

    protected static ?string $title = 'Tripulación';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $editable = fn () => ! in_array($this->getOwnerRecord()->status, [DepartureStatus::Cancelled, DepartureStatus::Completed], true);

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('name')->label('Nombre'),
                TextColumn::make('crew_role')->label('Función')->badge()
                    ->state(fn (Staff $r) => StaffRole::tryFrom($r->pivot->role)),
                TextColumn::make('phone')->label('Teléfono')->placeholder('—'),
                TextColumn::make('app')->label('App')
                    ->state(fn (Staff $r) => $r->user ? 'Con acceso' : 'Sin usuario')
                    ->badge()->color(fn (Staff $r) => $r->user ? 'success' : 'gray'),
            ])
            ->headerActions([
                Action::make('assign')->label('Asignar')->icon(Heroicon::OutlinedUserPlus)
                    ->visible($editable)
                    ->schema([
                        Select::make('staff_id')->label('Persona')->required()->searchable()->live()
                            ->options(fn () => Staff::query()->where('is_active', true)
                                ->whereNotIn('id', $this->getOwnerRecord()->crew()->pluck('staff.id'))
                                ->orderBy('name')->get()
                                ->mapWithKeys(fn (Staff $s) => [$s->id => "{$s->name} · {$s->role->getLabel()}"]))
                            ->afterStateUpdated(fn (?string $state, Set $set) => $set('role', Staff::find($state)?->role)),
                        Select::make('role')->label('Función en esta salida')->options(StaffRole::class)->required(),
                    ])
                    ->action(function (array $data) {
                        /** @var Departure $departure */
                        $departure = $this->getOwnerRecord();

                        Domain::run(
                            fn () => app(AssignCrew::class)->handle(
                                $departure,
                                Staff::findOrFail($data['staff_id']),
                                $data['role'] instanceof StaffRole ? $data['role'] : StaffRole::from($data['role']),
                            ),
                            success: 'Tripulación asignada',
                        );
                    }),
            ])
            ->recordActions([
                DetachAction::make()->label('Quitar')->visible($editable),
            ]);
    }
}
