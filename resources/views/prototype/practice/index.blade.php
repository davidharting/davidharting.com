{{--
    PROTOTYPE, throw me away. Four variants of in-app vocab practice,
    switchable via ?variant=A|B|C|D, on a throwaway /prototype/practice route.
    Answers "How should in-app practice look and feel?" (issue #376).
    Grades are kept in the browser only: nothing is saved.
    Add ?empty=1 to see the "nothing due" screen.
--}}
@php
    $variants = [
        'A' => 'Flip card',
        'B' => 'Swipe stack',
        'C' => 'Type it first',
        'D' => 'Definition first',
    ];
    $variant = array_key_exists(request('variant'), $variants) ? request('variant') : 'A';
    $fullScreen = in_array($variant, ['A', 'B']);
@endphp

<x-layout.app :hideNav="$fullScreen">
    <script>
        window.practiceSession = (cards) => ({
            cards,
            index: 0,
            revealed: false,
            results: [],
            current() {
                return this.cards[this.index] ?? null;
            },
            done() {
                return this.index >= this.cards.length;
            },
            gotCount() {
                return this.results.filter((r) => r.gotIt).length;
            },
            missedWords() {
                return this.results.filter((r) => !r.gotIt).map((r) => r.word);
            },
            reveal() {
                this.revealed = true;
            },
            grade(gotIt) {
                this.results.push({ word: this.current().word, gotIt });
                this.index++;
                this.revealed = false;
                this.afterGrade?.();
            },
            restart() {
                this.index = 0;
                this.revealed = false;
                this.results = [];
            },
        });
    </script>

    <div class="pb-20 font-sans">
        @include('prototype.practice.variant-'.strtolower($variant), ['cards' => $cards])
    </div>

    <x-prototype-switcher :variants="$variants" :current="$variant" />

    @livewireScripts
</x-layout.app>
