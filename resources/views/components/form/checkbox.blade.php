<div class="form-control">
    <label class="label cursor-pointer">
        <span class="label-text">{{ $label ?? $name }}</span>
        <input
            type="checkbox"
            :name="$name"
            :checked="$checked"
            {{ $attributes->merge(["class" => "checkbox"]) }}
        />
    </label>
    @error($name)
        <div class="text-sm text-error">
            {{ $message }}
        </div>
    @enderror
</div>
