@props([
    "name",
    "label",
    "model" => null,
])

<div>
    <label for="{{ $name }}" class="label-text">
        {{ $label ?? Str::headline($name) }}
    </label>
    <input
        name="{{ $name }}"
        value="{{ old($name, $model) }}"
        {{ $attributes->merge(["type" => "text", "class" => "input input-bordered w-full"]) }}
    />
    @error($name)
        <div class="text-sm text-error">
            {{ $message }}
        </div>
    @enderror
</div>
