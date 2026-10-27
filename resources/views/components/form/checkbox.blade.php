@props([
    'name',
    'label' => null,
    'checked' => false,
])

<fieldset class="fieldset">
    <label class="fieldset-label">
        <input
            type="checkbox"
            name="{{ $name }}"
            @checked(session()->hasOldInput() ? old($name) : $checked)
            {{ $attributes->merge(["class" => "checkbox"]) }}
        />
        {{ $label ?? Str::headline($name) }}
    </label>
    @error($name)
        <div class="text-error text-sm">{{ $message }}</div>
    @enderror
</fieldset>
