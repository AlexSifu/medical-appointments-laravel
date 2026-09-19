@props(['code', 'name' => null])
<x-badge :variant="\App\Models\ReservationStatus::variant($code)" :icon="\App\Models\ReservationStatus::icon($code)" {{ $attributes }}>
    {{ $name ?? \App\Models\ReservationStatus::label($code) }}
</x-badge>
