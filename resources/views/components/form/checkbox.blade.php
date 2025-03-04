<fieldset class='fieldset'>
    <label class='fieldset-label'>
        <input
            type="checkbox"
            :name="$name"
            :checked="$checked"
            {{ $attributes->merge(["class" => "checkbox"]) }}
        />
        {{ $label ?? $name }}
    @error($name)
        <div class="text-sm text-error">
            {{ $message }}
        </div>
    @enderror
</fieldset>
