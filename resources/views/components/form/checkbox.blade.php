<fieldset class="fieldset">
    <label class="fieldset-label">
        <input type="checkbox" :name="$name" :checked="$checked" {{ $attributes->merge(["class" => "checkbox"]) }} />
        {{ $label ?? $name }}
    </label>
    @error($name)
        <div class="text-error text-sm">{{ $message }}</div>
    @enderror
</fieldset>
