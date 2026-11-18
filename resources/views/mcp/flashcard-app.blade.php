{{--
    One flashcard, rendered in the chat by MCP Apps hosts. It follows the
    in-app practice page: the word on the front, "Say the definition, then
    tap", a tap flips the card to the definition, then ✕ Missed it or
    ✓ Got it. It fetches and grades through the admin MCP tools itself, so
    nothing here is server-rendered but the shell.
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

            /* The card flips on its vertical axis; front and back share one box. */
            .scene {
                perspective: 1200px;
            }

            .card {
                position: relative;
                width: 100%;
                min-height: 240px;
                cursor: pointer;
                transform-style: preserve-3d;
                transition: transform 450ms cubic-bezier(0.2, 0.7, 0.3, 1);
            }

            .card.flipped {
                transform: rotateY(180deg);
                cursor: default;
            }

            .face {
                position: absolute;
                inset: 0;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 20px;
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
                padding: 10px 18px;
                border: 1px solid var(--color-border-primary);
                border-radius: 999px;
                background: var(--color-background-secondary);
                color: var(--color-text-primary);
                font: inherit;
                cursor: pointer;
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
            const RELATIVE_UNITS = [
                ['day', 86400],
                ['hour', 3600],
                ['minute', 60],
                ['second', 1],
            ];

            /** "in 10 minutes", "tomorrow", for an ISO 8601 timestamp, as on the practice page. */
            function fromNow(timestamp) {
                const seconds = (new Date(timestamp) - Date.now()) / 1000;
                const [unit, size] =
                    RELATIVE_UNITS.find(([, size]) => Math.abs(seconds) >= size) ?? RELATIVE_UNITS.at(-1);
                return new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' }).format(
                    Math.round(seconds / size),
                    unit,
                );
            }

            const $ = (id) => document.getElementById(id);

            /** Show one of the views (card, done, empty, error) and hide the rest. */
            function show(view) {
                for (const id of ['practice', 'done', 'empty', 'error']) {
                    $(id).hidden = id !== view;
                }
            }

            createMcpApp(async (app) => {
                app.autoResize();

                let flashcard = null;
                let definition = null;

                /** Call a tool and return its structured result, or throw its error text. */
                async function call(name, args = {}) {
                    const result = await app.callServerTool(name, args);
                    const text = result.content?.[0]?.text ?? '';

                    if (result.isError) {
                        throw new Error(text || 'Something went wrong.');
                    }

                    return result.structuredContent ?? JSON.parse(text);
                }

                function fail(error) {
                    $('error-text').textContent = error.message;
                    show('error');
                }

                async function loadNext(includeNotDue = false) {
                    try {
                        const next = await call('next-flashcard', { include_not_due: includeNotDue });
                        flashcard = next.flashcard;
                        definition = null;

                        if (flashcard === null) {
                            $('next-due').textContent = next.next_due_at
                                ? `The next word is due ${fromNow(next.next_due_at)}. Anything more is practising ahead.`
                                : '';
                            $('practise-ahead').hidden = next.next_due_at === null;
                            $('empty-heading').textContent = next.next_due_at ? 'All caught up' : 'No words yet';
                            show('empty');
                            return;
                        }

                        $('front-word').textContent = flashcard.word;
                        $('back-word').textContent = flashcard.word;
                        $('definition').textContent = '';
                        $('ahead').hidden = flashcard.is_due;
                        $('due-count').textContent = `${next.due_count} due`;
                        $('card').classList.remove('flipped');
                        $('card').setAttribute('aria-disabled', 'false');
                        $('card').setAttribute('aria-label', `${flashcard.word}. Tap to show the definition.`);
                        $('show').hidden = false;
                        $('grades').hidden = true;
                        show('practice');

                        // Fetched now, so the flip is instant. It stays in this
                        // frame; the model never sees it.
                        const revealed = await call('reveal-flashcard', { flashcard_id: flashcard.flashcard_id });
                        definition = revealed.short_definition;
                        $('definition').textContent = definition;
                    } catch (error) {
                        fail(error);
                    }
                }

                function flip() {
                    if (flashcard === null || definition === null || $('card').classList.contains('flipped')) {
                        return;
                    }

                    $('card').classList.add('flipped');
                    $('card').setAttribute('aria-disabled', 'true');
                    $('show').hidden = true;
                    $('grades').hidden = false;
                    $('got').focus();
                }

                async function grade(result) {
                    for (const button of document.querySelectorAll('.grade')) {
                        button.disabled = true;
                    }

                    try {
                        const graded = await call('grade-flashcard', {
                            flashcard_id: flashcard.flashcard_id,
                            result,
                        });

                        const gotIt = graded.result === 'got_it';
                        $('done-emoji').textContent = gotIt ? '👏' : '🔁';
                        $('done-heading').textContent = gotIt ? 'Got it' : 'Missed it';
                        $('done-text').textContent = `You'll see ${graded.word} again ${fromNow(graded.next_due_at)}.`;
                        show('done');

                        // Lets the model know without starting a turn.
                        await app.updateModelContext({
                            flashcard: { word: graded.word, result: graded.result, next_due_at: graded.next_due_at },
                        });
                    } catch (error) {
                        fail(error);
                    } finally {
                        for (const button of document.querySelectorAll('.grade')) {
                            button.disabled = false;
                        }
                    }
                }

                $('card').addEventListener('click', flip);
                $('card').addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        flip();
                    }
                });
                $('show').addEventListener('click', flip);
                $('missed').addEventListener('click', () => grade('missed_it'));
                $('got').addEventListener('click', () => grade('got_it'));
                $('next').addEventListener('click', () => loadNext());
                $('practise-ahead').addEventListener('click', () => loadNext(true));
                $('retry').addEventListener('click', () => loadNext());

                await loadNext();
            });
        </script>
    </x-slot:head>

    <main>
        <div id="practice" hidden>
            <div class="status">
                <span id="ahead" class="badge ahead" hidden>Practising ahead</span>
                <span id="due-count" class="badge"></span>
            </div>

            <div class="scene">
                {{-- A div, not a button: buttons flatten the 3D flip in some browsers. --}}
                <div id="card" class="card" role="button" tabindex="0">
                    <span class="face front">
                        <span id="front-word" class="word"></span>
                        <span class="hint">Say the definition, then tap</span>
                    </span>
                    <span class="face back" aria-live="polite">
                        <span id="back-word" class="word"></span>
                        <span id="definition" class="definition"></span>
                    </span>
                </div>
            </div>

            <div class="actions">
                <button id="show" type="button" class="button">Show definition</button>
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
            <button id="next" type="button" class="button">Next word</button>
        </div>

        <div id="empty" class="message" hidden>
            <div class="emoji" aria-hidden="true">🌿</div>
            <h2 id="empty-heading">All caught up</h2>
            <p id="next-due"></p>
            <button id="practise-ahead" type="button" class="button">Practise ahead</button>
        </div>

        <div id="error" class="message" hidden>
            <h2>Couldn't load the card</h2>
            <p id="error-text"></p>
            <button id="retry" type="button" class="button">Try again</button>
        </div>
    </main>
</x-mcp::app>
