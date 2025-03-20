@props([
    "name",
    "label",
    "model" => null,
])

<div>
    <div>
        <label for="{{ $name }}" class="fieldset-label">
            {{ $label ?? Str::headline($name) }}
        </label>
        <input
            name="{{ $name }}"
            value="{{ old($name, $model) }}"
            {{ $attributes->merge(["type" => "text", "class" => "input"]) }}
        />
    </div>
    @error($name)
        <div class="text-sm text-error">
            {{ $message }}
        </div>
    @enderror
</div>
