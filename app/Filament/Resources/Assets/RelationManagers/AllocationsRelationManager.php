<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Actions\Allocations\ReleaseAllocation;
use App\Enums\AllocationKind;
use App\Enums\AllocationStatus;
use App\Models\Allocation;
use App\Models\Departure;
use App\Models\MaintenanceOrder;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AllocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'allocations';

    protected static ?string $title = 'Agenda';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('allocatable'))
            ->defaultSort('starts_at')
            ->columns([
                TextColumn::make('starts_at')->label('Inicio')->dateTime('D d M · H:i')->sortable(),
                TextColumn::make('ends_at')->label('Fin')->dateTime('H:i'),
                TextColumn::make('kind')->label('Tipo')->badge(),
                TextColumn::make('detail')->label('Detalle')->state(fn (Allocation $r) => match (true) {
                    $r->allocatable instanceof Departure => $r->allocatable->code,
                    $r->allocatable instanceof MaintenanceOrder => $r->allocatable->code.' · '.$r->allocatable->title,
                    default => $r->reason,
                }),
                TextColumn::make('status')->label('Estado')->badge(),
            ])
            ->filters([
                TernaryFilter::make('upcoming')->label('Solo próximas')->default(true)
                    ->queries(true: fn (Builder $query) => $query->where('ends_at', '>', now()), false: fn (Builder $query) => $query->where('ends_at', '<=', now())),
                SelectFilter::make('kind')->label('Tipo')->options(AllocationKind::class),
                SelectFilter::make('status')->label('Estado')->options(AllocationStatus::class)->default(AllocationStatus::Active->value),
            ])
            ->recordActions([
                Action::make('release')->label('Liberar')->color('danger')->requiresConfirmation()
                    ->modalDescription('El horario queda disponible para venta.')
                    ->visible(fn (Allocation $r) => $r->kind === AllocationKind::Block && $r->status === AllocationStatus::Active)
                    ->action(fn (Allocation $r) => app(ReleaseAllocation::class)->handle($r)),
            ]);
    }
}
