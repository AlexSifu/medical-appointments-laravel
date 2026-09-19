@props(['name', 'label', 'type' => 'text', 'value' => null, 'help' => null, 'required' => false, 'id' => null])
@php
    $id = $id ?? 'f-'.str_replace(['[', ']', '.'], '-', $name);
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $error = $errors->first($key);
    $describedBy = trim(($help ? $id.'-help ' : '').($error ? $id.'-error' : ''));
    $current = $type === 'password' ? null : old($key, $value);
@endphp
<div class="mb-3">
    <label for="{{ $id }}" class="form-label">
        {{ $label }}@if ($required)<span class="text-danger" aria-hidden="true"> *</span>@endif
    </label>
    <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}"
        @if ($current !== null) value="{{ $current }}" @endif
        @if ($required) required aria-required="true" @endif
        @if ($error) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->class(['form-control', 'is-invalid' => $error]) }}>
    @if ($help)
        <div id="{{ $id }}-help" class="form-text">{{ $help }}</div>
    @endif
    @if ($error)
        <div id="{{ $id }}-error" class="invalid-feedback d-block">{{ $error }}</div>
    @endif
</div>
