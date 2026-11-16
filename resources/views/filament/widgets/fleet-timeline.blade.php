@php
    // Colores con variables de Filament: se adaptan a modo claro y oscuro.
    $tones = [
        'live' => ['bg' => 'var(--primary-100)', 'fg' => 'var(--primary-700)', 'bar' => 'var(--primary-500)', 'label' => 'En curso'],
        'booked' => ['bg' => 'var(--info-100)', 'fg' => 'var(--info-700)', 'bar' => 'var(--info-500)', 'label' => 'Programada'],
        'done' => ['bg' => 'var(--gray-100)', 'fg' => 'var(--gray-600)', 'bar' => 'var(--gray-400)', 'label' => 'Finalizada'],
        'maintenance' => ['bg' => 'var(--warning-100)', 'fg' => 'var(--warning-700)', 'bar' => 'var(--warning-500)', 'label' => 'Mantenimiento'],
        'block' => ['bg' => 'var(--danger-100)', 'fg' => 'var(--danger-700)', 'bar' => 'var(--danger-500)', 'label' => 'Bloqueo'],
    ];
@endphp

<x-filament-widgets::widget>
    <x-filament::section wire:poll.30s>
        <x-slot name="heading">Agenda de la flota</x-slot>
        <x-slot name="description">{{ ucfirst($dateLabel) }}</x-slot>
        <x-slot name="afterHeader">
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
                <x-filament::tabs>
                    <x-filament::tabs.item :active="$kind === 'boat'" wire:click="$set('kind', 'boat')">Embarcaciones</x-filament::tabs.item>
                    <x-filament::tabs.item :active="$kind === 'vehicle'" wire:click="$set('kind', 'vehicle')">Vehículos</x-filament::tabs.item>
                </x-filament::tabs>
                <x-filament::button size="sm" color="gray" icon="heroicon-m-chevron-left" wire:click="shiftDay(-1)" label-sr-only>Día anterior</x-filament::button>
                @unless ($isToday)
                    <x-filament::button size="sm" color="gray" wire:click="today">Hoy</x-filament::button>
                @endunless
                <x-filament::button size="sm" color="gray" icon="heroicon-m-chevron-right" wire:click="shiftDay(1)" label-sr-only>Día siguiente</x-filament::button>
            </div>
        </x-slot>

        <div style="overflow-x:auto">
            <div style="min-width:760px;position:relative">
                {{-- Escala de horas --}}
                <div style="display:grid;grid-template-columns:150px 1fr">
                    <div></div>
                    <div style="position:relative;height:1.5rem">
                        @foreach ($ticks as $tick)
                            <span style="position:absolute;left:{{ $tick['left'] }}%;font-size:.7rem;color:var(--gray-500);transform:translateX(-50%)">{{ $tick['label'] }}</span>
                        @endforeach
                    </div>
                </div>

                @forelse ($rows as $row)
                    <div style="display:grid;grid-template-columns:150px 1fr;border-top:1px solid var(--gray-200);min-height:3rem">
                        <div style="padding:.4rem .6rem;display:flex;flex-direction:column;justify-content:center">
                            <span style="font-weight:600;font-size:.85rem">{{ $row['asset']->name }}</span>
                            <span style="font-size:.7rem;color:var(--gray-500)">{{ $row['asset']->capacity }} pax · {{ $row['asset']->status->getLabel() }}</span>
                        </div>
                        <div style="position:relative;background-image:repeating-linear-gradient(90deg,transparent 0,transparent calc({{ 100 / max(1, count($ticks) - 1) }}% - 1px),var(--gray-200) calc({{ 100 / max(1, count($ticks) - 1) }}% - 1px),var(--gray-200) {{ 100 / max(1, count($ticks) - 1) }}%)">
                            @foreach ($row['blocks'] as $b)
                                @php($t = $tones[$b['tone']])
                                <{{ $b['url'] ? 'a' : 'div' }} @if ($b['url']) href="{{ $b['url'] }}" @endif
                                    title="{{ $b['title'] }} · {{ $b['range'] }} · {{ $b['subtitle'] }}"
                                    style="position:absolute;top:.35rem;bottom:.35rem;left:{{ $b['left'] }}%;width:{{ $b['width'] }}%;background:{{ $t['bg'] }};color:{{ $t['fg'] }};border-left:3px solid {{ $t['bar'] }};border-radius:.375rem;padding:.15rem .4rem;font-size:.7rem;line-height:1.2;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;text-decoration:none">
                                    <strong style="display:block;overflow:hidden;text-overflow:ellipsis">{{ $b['title'] }}</strong>
                                    <span style="opacity:.85">{{ $b['subtitle'] }}</span>
                                </{{ $b['url'] ? 'a' : 'div' }}>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p style="padding:1rem;color:var(--gray-500)">No hay unidades registradas.</p>
                @endforelse

                @if (! is_null($nowLeft))
                    <div style="position:absolute;top:0;bottom:0;left:calc(150px + (100% - 150px) * {{ $nowLeft / 100 }});width:2px;background:var(--danger-500);pointer-events:none">
                        <span style="position:absolute;top:0;left:4px;background:var(--danger-500);color:white;font-size:.65rem;font-weight:700;padding:1px 4px;border-radius:3px">{{ $nowLabel }}</span>
                    </div>
                @endif
            </div>
        </div>

        <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:.75rem;font-size:.75rem;color:var(--gray-500)">
            @foreach ($tones as $tone)
                <span style="display:inline-flex;align-items:center;gap:.35rem"><i style="width:.65rem;height:.65rem;border-radius:2px;background:{{ $tone['bar'] }};display:inline-block"></i>{{ $tone['label'] }}</span>
            @endforeach
            <span>Espacio vacío = libre</span>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
