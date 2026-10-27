<x-layout.memories>
    <div class="mx-auto max-w-xl font-sans">
        <a class="link text-sm" href="{{ route("memories.index") }}">← All months</a>

        <x-type.page-title class="mt-8 text-5xl">An earlier month</x-type.page-title>

        <form method="POST" action="{{ route("memories.earlier.open") }}" class="mt-6">
            @csrf

            <fieldset class="fieldset">
                <label for="month" class="fieldset-label">Which month?</label>
                <input
                    id="month"
                    name="month"
                    type="month"
                    @class(['input w-full text-base', 'input-error' => $errors->has('month')])
                    placeholder="YYYY-MM"
                    pattern="\d{4}-\d{2}"
                    min="{{ $earliest->toYearMonth() }}"
                    max="{{ $latest->toYearMonth() }}"
                    value="{{ old("month", $latest->toYearMonth()) }}"
                    required
                    @error('month') aria-invalid="true" aria-describedby="month-error" @enderror
                />
                @error('month')
                    <p id="month-error" class="text-error text-sm">{{ $message }}</p>
                @enderror
            </fieldset>

            <button type="submit" class="btn btn-primary btn-block mt-4">Write</button>
        </form>
    </div>
</x-layout.memories>
