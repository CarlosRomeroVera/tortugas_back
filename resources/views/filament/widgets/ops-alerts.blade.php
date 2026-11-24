<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Alertas</x-slot>
        <x-slot name="afterHeader">
            <x-filament::badge :color="$alerts->isEmpty() ? 'success' : 'danger'">{{ $alerts->count() }} abiertas</x-filament::badge>
        </x-slot>

        @forelse ($alerts as $alert)
            <div style="display:flex;gap:.75rem;padding:.6rem 0;{{ $loop->last ? '' : 'border-bottom:1px solid var(--gray-200);' }}">
                <span style="width:3px;border-radius:3px;flex:none;background:var(--{{ $alert['level'] }}-500)"></span>
                <div>
                    <div style="font-weight:600;font-size:.875rem">{{ $alert['title'] }}</div>
                    <div style="font-size:.8rem;color:var(--gray-500)">{{ $alert['body'] }}</div>
                </div>
            </div>
        @empty
            <p style="font-size:.875rem;color:var(--gray-500)">Sin pendientes.</p>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
