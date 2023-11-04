<div>
    <label :for="$name" class='label-text'>{{ $label ?? $name }}</label>
    <input :name="$name" :value="old($name)" {{ $attributes->merge([ 'type' => 'text', 'class' =>  'input input-bordered w-full']) }} /> 
    @error($name)
        <div class='text-sm text-error'>
            {{ $message }}
        </div>
    @enderror
</div>