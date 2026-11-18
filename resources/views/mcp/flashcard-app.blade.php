{{--
    One flashcard, rendered in the chat by MCP Apps hosts. It follows the
    in-app practice page: the word on the front, "Say the definition, then
    tap", a tap flips the card to the definition, then ✕ Missed it or
    ✓ Got it (or ← / →). It fetches and grades through the admin MCP tools
    itself, so nothing here is server-rendered but the shell.
--}}
<x-mcp::app :title="$title">
    <x-slot:head>
        <style>
            :root {
                /* light-dark() needs this; the host overrides it with its own theme. */
                color-scheme: light dark;
                --color-background-primary: light-dark(#ffffff, #1f1f1f);
                --color-background-secondary: light-dark(#f4f4f5, #2a2a2a);
                --color-text-primary: light-dark(#18181b, #fafafa);
                --color-text-secondary: light-dark(#71717a, #a1a1aa);
                --color-border-primary: light-dark(#e4e4e7, #3f3f46);
                --font-sans: system-ui, -apple-system, sans-serif;
                --font-serif: ui-serif, Georgia, 'Times New Roman', serif;
                --border-radius-lg: 16px;
                --got-it: light-dark(#15803d, #4ade80);
                --missed-it: light-dark(#b91c1c, #f87171);
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                padding: 12px;
                font-family: var(--font-sans);
                color: var(--color-text-primary);
                background: transparent;
            }

            [hidden] {
                display: none !important;
            }

            .sr-only {
                position: absolute;
                width: 1px;
                height: 1px;
                overflow: hidden;
                clip: rect(0 0 0 0);
                white-space: nowrap;
            }

            .status {
                display: flex;
                justify-content: flex-end;
                gap: 8px;
                min-height: 24px;
                margin-bottom: 8px;
                font-size: 13px;
            }

            .badge {
                padding: 2px 10px;
                border-radius: 999px;
                background: var(--color-background-secondary);
                color: var(--color-text-secondary);
            }

            .badge.ahead {
                background: light-dark(#fef3c7, #451a03);
                color: light-dark(#92400e, #fcd34d);
            }

            /*
             * The card flips on its vertical axis. Both faces share one grid
             * cell, so the card is as tall as the longer face and a long
             * definition grows it rather than spilling out.
             */
            .scene {
                perspective: 1200px;
            }

            .card {
                display: grid;
                cursor: pointer;
                transform-style: preserve-3d;
                transition: transform 450ms cubic-bezier(0.2, 0.7, 0.3, 1);
                -webkit-tap-highlight-color: transparent;
                user-select: none;
            }

            .card:focus-visible {
                outline: none;
            }

            .card:focus-visible .face {
                outline: 2px solid var(--color-text-secondary);
                outline-offset: 3px;
            }

            .card.flipped {
                transform: rotateY(180deg);
                cursor: default;
            }

            .face {
                grid-area: 1 / 1;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 20px;
                min-height: 240px;
                padding: 24px;
                text-align: center;
                border: 1px solid var(--color-border-primary);
                border-radius: var(--border-radius-lg);
                background: var(--color-background-primary);
                box-shadow: 0 6px 20px rgb(0 0 0 / 0.08);
                -webkit-backface-visibility: hidden;
                backface-visibility: hidden;
                overflow-wrap: anywhere;
            }

            .face.back {
                transform: rotateY(180deg);
            }

            .word {
                margin: 0;
                font-family: var(--font-serif);
                font-size: clamp(32px, 9vw, 48px);
                font-weight: 400;
            }

            .back .word {
                font-size: 22px;
                color: var(--color-text-secondary);
            }

            .definition {
                margin: 0;
                font-family: var(--font-serif);
                font-size: 22px;
                line-height: 1.35;
            }

            .hint {
                margin: 0;
                font-size: 14px;
                color: var(--color-text-secondary);
            }

            @media (prefers-reduced-motion: reduce) {
                .card {
                    transition: none;
                }
            }

            /* Always the same height, so the card never jumps. */
            .actions {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 32px;
                min-height: 80px;
            }

            .grade {
                width: 56px;
                height: 56px;
                border-radius: 999px;
                font-size: 24px;
                cursor: pointer;
            }

            .grade.missed {
                border: 2px solid var(--missed-it);
                background: transparent;
                color: var(--missed-it);
            }

            .grade.got {
                border: 2px solid var(--got-it);
                background: var(--got-it);
                color: var(--color-background-primary);
            }

            .button {
                min-height: 44px;
                padding: 10px 20px;
                border: 1px solid var(--color-border-primary);
                border-radius: 999px;
                background: var(--color-background-secondary);
                color: var(--color-text-primary);
                font: inherit;
                cursor: pointer;
            }

            .button.primary {
                border-color: var(--color-text-primary);
                background: var(--color-text-primary);
                color: var(--color-background-primary);
            }

            button:disabled {
                opacity: 0.5;
                cursor: progress;
            }

            .message {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 12px;
                padding: 24px;
                text-align: center;
            }

            .message h2 {
                margin: 0;
                font-family: var(--font-serif);
                font-size: 28px;
                font-weight: 400;
            }

            .message p {
                margin: 0;
                color: var(--color-text-secondary);
            }

            .emoji {
                font-size: 40px;
            }
        </style>

        <script type="module">
            const GOT_IT = @js(\App\Enum\Recall::GotIt->value);
            const MISSED_IT = @js(\App\Enum\Recall::MissedIt->value);

            // Keep in step with fromNow() in resources/js/word-practice.js:
            // this frame can't load the Vite bundle, so it has its own copy.
            const RELATIVE_UNITS = [
                ['day', 86400],
                ['hour', 3600],
                ['minute', 60],
                ['second', 1],
            ];

            /** "in 10 minutes", "tomorrow", for an ISO 8601 timestamp. */
            function fromNow(timestamp) {
                const seconds = (new Date(timestamp) - Date.now()) / 1000;
                const [unit, size] = RELATIVE_UNITS.find(([, size]) => Math.abs(seconds) >= size) ?? RELATIVE_UNITS.at(-1);
                return new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' }).format(Math.round(seconds / size), unit);
            }

            const $ = (id) => document.getElementById(id);

            /** Show one view (loading, practice, done, empty, error), hide the rest, and focus its main control. */
            function show(view, focus = null) {
                for (const id of ['loading', 'practice', 'done', 'empty', 'error']) {
                    $(id).hidden = id !== view;
                }

                focus?.focus();
            }

            /** Hide the face the user can't see from assistive tech, so the answer isn't read out early. */
            function faceUp(flipped) {
                $('card').classList.toggle('flipped', flipped);
                $('front').inert = flipped;
                $('back').inert = !flipped;
                $('front').setAttribute('aria-hidden', String(flipped));
                $('back').setAttribute('aria-hidden', String(!flipped));
            }

            createMcpApp(async (app) => {
                app.autoResize();

                let flashcard = null;
                let definition = null;
                // Bumped on every load, so a slow reply for an earlier card
                // can't land on the card now showing.
                let loadNumber = 0;

                /** Call a tool and return its structured result, or throw its error text. */
                async function call(name, args = {}) {
                    const result = await app.callServerTool(name, args);

                    if (result.isError) {
                        throw new Error(result.content?.[0]?.text || 'Something went wrong.');
                    }

                    if (!result.structuredContent) {
                        throw new Error(`${name} returned no structured result.`);
                    }

                    return result.structuredContent;
                }

                function fail(heading, error) {
                    $('error-heading').textContent = heading;
                    $('error-text').textContent = error.message;
                    show('error', $('retry'));
                }

                /**
                 * Load and show the next card. Focus moves only when David
                 * asked for it, so the first load doesn't pull focus away
                 * from the chat's message box.
                 */
                async function loadNext({ includeNotDue = false, moveFocus = true } = {}) {
                    const thisLoad = ++loadNumber;
                    flashcard = null;
                    definition = null;
                    show('loading');

                    try {
                        const next = await call('next-flashcard', { include_not_due: includeNotDue });

                        if (thisLoad !== loadNumber) {
                            return;
                        }

                        flashcard = next.flashcard;

                        if (flashcard === null) {
                            const hasWords = next.next_due_at !== null;
                            $('empty-emoji').textContent = hasWords ? '🌿' : '📖';
                            $('empty-heading').textContent = hasWords ? 'All caught up' : 'No words yet';
                            $('empty-text').textContent = hasWords
                                ? `The next word is due ${fromNow(next.next_due_at)}. Anything more is practising ahead.`
                                : 'Add a word and it will show up here to practise.';
                            $('practise-ahead').hidden = !hasWords;
                            show('empty', moveFocus && hasWords ? $('practise-ahead') : null);
                            return;
                        }

                        $('front-word').textContent = flashcard.word;
                        $('back-word').textContent = flashcard.word;
                        $('definition').textContent = '';
                        $('ahead').hidden = flashcard.is_due;
                        $('due-count').textContent = `${next.due_count} due`;
                        $('card').setAttribute('aria-label', `${flashcard.word}. Tap to show the definition.`);
                        $('card').setAttribute('aria-busy', 'true');
                        $('show').disabled = true;
                        $('show').hidden = false;
                        $('grades').hidden = true;
                        faceUp(false);
                        show('practice', moveFocus ? $('card') : null);

                        // Fetched now, so the flip is instant. It stays in this
                        // frame rather than going back to the model.
                        const revealed = await call('reveal-flashcard', { flashcard_id: flashcard.flashcard_id });

                        if (thisLoad !== loadNumber) {
                            return;
                        }

                        definition = revealed.short_definition;
                        $('definition').textContent = definition;
                        $('card').setAttribute('aria-busy', 'false');
                        $('show').disabled = false;
                    } catch (error) {
                        if (thisLoad === loadNumber) {
                            fail("Couldn't load the card", error);
                        }
                    }
                }

                function flip() {
                    if (flashcard === null || definition === null || $('card').classList.contains('flipped')) {
                        return;
                    }

                    faceUp(true);
                    $('card').removeAttribute('aria-label');
                    $('show').hidden = true;
                    $('grades').hidden = false;
                    $('announcement').textContent = `${flashcard.word}: ${definition}`;
                    $('got').focus();
                }

                let grading = false;

                async function grade(result) {
                    if (grading || flashcard === null || !$('card').classList.contains('flipped')) {
                        return;
                    }

                    grading = true;
                    $('missed').disabled = true;
                    $('got').disabled = true;

                    try {
                        const graded = await call('grade-flashcard', {
                            flashcard_id: flashcard.flashcard_id,
                            result,
                        });

                        const gotIt = graded.result === GOT_IT;
                        $('done-emoji').textContent = gotIt ? '👏' : '🔁';
                        $('done-heading').textContent = gotIt ? 'Got it' : 'Missed it';
                        $('done-text').textContent = `You'll see ${graded.word} again ${fromNow(graded.next_due_at)}.`;
                        flashcard = null;
                        show('done', $('next'));

                        // Lets the model know without starting a turn. Best
                        // effort: the grade is saved either way.
                        app.updateModelContext({
                            content: [{ type: 'text', text: `David graded "${graded.word}" on the flashcard: ${graded.result}.` }],
                            structuredContent: { word: graded.word, result: graded.result, next_due_at: graded.next_due_at },
                        }).catch(() => {});
                    } catch (error) {
                        fail("Couldn't save your grade", error);
                    } finally {
                        grading = false;
                        $('missed').disabled = false;
                        $('got').disabled = false;
                    }
                }

                $('card').addEventListener('click', flip);
                $('card').addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        flip();
                    }
                });
                // The in-app page's keys: → for Got it, ← for Missed it, once flipped.
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'ArrowRight') {
                        grade(GOT_IT);
                    } else if (event.key === 'ArrowLeft') {
                        grade(MISSED_IT);
                    }
                });
                $('show').addEventListener('click', flip);
                $('missed').addEventListener('click', () => grade(MISSED_IT));
                $('got').addEventListener('click', () => grade(GOT_IT));
                $('next').addEventListener('click', () => loadNext());
                $('practise-ahead').addEventListener('click', () => loadNext({ includeNotDue: true }));
                $('retry').addEventListener('click', () => loadNext());

                await loadNext({ moveFocus: false });
            });
        </script>
    </x-slot:head>

    <main>
        <p id="announcement" class="sr-only" aria-live="polite"></p>

        <div id="loading" class="message">
            <p>Finding your next word…</p>
        </div>

        <div id="practice" hidden>
            <div class="status">
                <span id="ahead" class="badge ahead" hidden>Practising ahead</span>
                <span id="due-count" class="badge"></span>
            </div>

            <div class="scene">
                {{-- A div, not a button: buttons flatten the 3D flip in some browsers. --}}
                <div id="card" class="card" role="button" tabindex="0">
                    <div id="front" class="face front">
                        <span id="front-word" class="word"></span>
                        <span class="hint">Say the definition, then tap</span>
                    </div>
                    <div id="back" class="face back">
                        <span id="back-word" class="word"></span>
                        <span id="definition" class="definition"></span>
                    </div>
                </div>
            </div>

            <div class="actions">
                <button id="show" type="button" class="button primary">Show definition</button>
                <div id="grades" class="actions" hidden>
                    <button id="missed" type="button" class="grade missed" aria-label="Missed it">✕</button>
                    <button id="got" type="button" class="grade got" aria-label="Got it">✓</button>
                </div>
            </div>
        </div>

        <div id="done" class="message" hidden>
            <div id="done-emoji" class="emoji" aria-hidden="true"></div>
            <h2 id="done-heading"></h2>
            <p id="done-text"></p>
            <button id="next" type="button" class="button primary">Next word</button>
        </div>

        <div id="empty" class="message" hidden>
            <div id="empty-emoji" class="emoji" aria-hidden="true"></div>
            <h2 id="empty-heading"></h2>
            <p id="empty-text"></p>
            <button id="practise-ahead" type="button" class="button">Practise ahead</button>
        </div>

        <div id="error" class="message" hidden>
            <h2 id="error-heading"></h2>
            <p id="error-text"></p>
            <button id="retry" type="button" class="button">Try again</button>
        </div>
    </main>
</x-mcp::app>
