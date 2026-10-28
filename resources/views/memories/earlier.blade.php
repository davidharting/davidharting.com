<x-layout.memories>
    <div class="mx-auto max-w-xl font-sans">
        <a class="link text-sm" href="{{ route("memories.index") }}">← All months</a>

        <x-type.page-title class="mt-8 text-4xl">Choose a month to write about</x-type.page-title>

        <form
            class="mt-6"
            onsubmit="
                event.preventDefault();
                window.location.assign(this.elements.month.value);
            "
        >
            <fieldset class="fieldset">
                <label for="month" class="fieldset-label">Which month?</label>
                <select id="month" name="month" class="select w-full text-base">
                    @foreach ($months as $month)
                        <option value="{{ route('memories.write', $month->toYearMonth()) }}">
                            {{ $month->label() }}
                        </option>
                    @endforeach
                </select>
            </fieldset>

            <button type="submit" class="btn btn-primary btn-block mt-4">Write</button>
        </form>
    </div>
</x-layout.memories>
