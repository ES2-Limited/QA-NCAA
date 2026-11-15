@props([
    'status' => null,
    'type' => 'audit_status', // 'audit_status' or 'ncr_status'
    'size' => 'sm',
])

@php
    $resolved = \App\Models\Lookup::resolveStatus($type, $status);
@endphp

<flux:badge :size="$size" :color="$resolved['colour']" {{ $attributes }}>
    {{ $resolved['label'] }}
</flux:badge>
