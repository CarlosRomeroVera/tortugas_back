<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Enums\FaultSeverity;
use App\Enums\FaultStatus;
use App\Models\FaultReport;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Fallas reportadas por la tripulación desde la app. */
class FaultReportsRelationManager extends RelationManager
{
    protected static string $relationship = 'faultReports';

    protected static ?string $title = 'Fallas reportadas';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['reporter', 'departure']))
            ->defaultSort('reported_at', 'desc')
            ->columns([
                TextColumn::make('reported_at')->label('Reportada')->dateTime('d M · H:i'),
                TextColumn::make('severity')->label('Gravedad')->badge(),
                TextColumn::make('description')->label('Descripción')->wrap()->limit(120),
                TextColumn::make('reporter.name')->label('Reportó')->placeholder('—'),
                TextColumn::make('departure.code')->label('Salida')->placeholder('—'),
                TextColumn::make('status')->label('Estado')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(FaultStatus::class)->default(FaultStatus::Open->value),
                SelectFilter::make('severity')->label('Gravedad')->options(FaultSeverity::class),
            ])
            ->recordActions([
                Action::make('resolve')->label('Marcar atendida')->color('success')->requiresConfirmation()
                    ->modalDescription('Si hace falta reparar la unidad, programa antes una orden de mantenimiento.')
                    ->visible(fn (FaultReport $r) => $r->status === FaultStatus::Open)
                    ->action(fn (FaultReport $r) => $r->update([
                        'status' => FaultStatus::Resolved,
                        'resolved_at' => now(),
                        'resolved_by' => auth()->id(),
                    ])),
            ]);
    }
}
