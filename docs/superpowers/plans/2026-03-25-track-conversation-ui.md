# Track Conversation UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the single-turn `TrackCommand` with a multi-turn Nutgram `Conversation` that lets the `MediaTrackingAgent` ask clarifying questions and present a `[✓ Confirm] [✗ Cancel]` inline keyboard before executing (DB writes deferred to next milestone).

**Architecture:** `TrackConversation` (extends Nutgram `Conversation`) holds `Message[]` history as a serialized property and creates a fresh `MediaTrackingAgent` per turn, injecting history and a `RequestConfirmation` tool. After `prompt()`, if the tool was called, the response is sent with inline keyboard buttons; otherwise plain text and the conversation loops back for another turn.

**Tech Stack:** Laravel 11, Nutgram (Telegram bot), `laravel/ai` (Anthropic/Claude), Pest tests, PHPUnit assertions, FakeNutgram for testing.

---

## File Map

| Action | Path |
|--------|------|
| Create | `app/Ai/Tools/RequestConfirmation.php` |
| Modify | `app/Ai/Agents/MediaTrackingAgent.php` |
| Create | `app/Telegram/Conversations/TrackConversation.php` |
| Modify | `routes/telegram.php` |
| Delete | `app/Telegram/Commands/TrackCommand.php` |
| Modify | `tests/Feature/Ai/MediaTrackingAgentTest.php` |
| Create | `tests/Feature/Telegram/TrackConversationTest.php` |
| Delete | `tests/Feature/Telegram/TrackCommandTest.php` |
| Create | `tests/Unit/Ai/Tools/RequestConfirmationTest.php` |

---

## Task 1: `RequestConfirmation` tool

**Files:**
- Create: `app/Ai/Tools/RequestConfirmation.php`
- Create: `tests/Unit/Ai/Tools/RequestConfirmationTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Ai/Tools/RequestConfirmationTest.php`:

```php
<?php

use App\Ai\Tools\RequestConfirmation;
use Illuminate\Foundation\Testing\TestCase;
use Laravel\Ai\Tools\Request;

test('wasRequested() returns false before handle() is called', function () {
    /** @var TestCase $this */
    $tool = new RequestConfirmation();

    $this->assertFalse($tool->wasRequested());
});

test('wasRequested() returns true after handle() is called', function () {
    /** @var TestCase $this */
    $tool = new RequestConfirmation();
    $tool->handle(new Request([]));

    $this->assertTrue($tool->wasRequested());
});

test('handle() returns a non-empty acknowledgement string', function () {
    /** @var TestCase $this */
    $tool = new RequestConfirmation();
    $result = $tool->handle(new Request([]));

    $this->assertNotEmpty((string) $result);
});

test('schema() returns an empty array', function () {
    /** @var TestCase $this */
    $tool = new RequestConfirmation();
    $schema = $tool->schema(app(\Illuminate\Contracts\JsonSchema\JsonSchema::class));

    $this->assertSame([], $schema);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Unit/Ai/Tools/RequestConfirmationTest.php
```

Expected: FAIL — class not found.

- [ ] **Step 3: Implement `RequestConfirmation`**

Create `app/Ai/Tools/RequestConfirmation.php`:

```php
<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RequestConfirmation implements Tool
{
    private bool $requested = false;

    public function wasRequested(): bool
    {
        return $this->requested;
    }

    public function description(): Stringable|string
    {
        return 'Call this tool when you have identified the media item and resolved all ambiguity. '
            . 'It signals that you are ready to present a confirmation plan. '
            . 'Do not call it until you are confident about the title, year, creator, and intended action.';
    }

    public function handle(Request $request): Stringable|string
    {
        $this->requested = true;

        return 'Confirmation signalled. Write your confirmation message as your response text.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test tests/Unit/Ai/Tools/RequestConfirmationTest.php
```

Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Ai/Tools/RequestConfirmation.php tests/Unit/Ai/Tools/RequestConfirmationTest.php
git commit -m "feat: add RequestConfirmation signal tool for TrackConversation"
```

---

## Task 2: Update `MediaTrackingAgent` — history + tool injection

**Files:**
- Modify: `app/Ai/Agents/MediaTrackingAgent.php`
- Modify: `tests/Feature/Ai/MediaTrackingAgentTest.php`

- [ ] **Step 1: Update existing tests to cover the new constructor**

Add to `tests/Feature/Ai/MediaTrackingAgentTest.php`:

```php
use App\Ai\Tools\RequestConfirmation;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;

test('MediaTrackingAgent messages() returns empty array by default', function () {
    /** @var TestCase $this */
    $agent = new MediaTrackingAgent();

    $this->assertSame([], iterator_to_array($agent->messages()));
});

test('MediaTrackingAgent messages() returns injected history', function () {
    /** @var TestCase $this */
    $history = [
        new Message(MessageRole::User, 'Add The Hobbit'),
        new Message(MessageRole::Assistant, 'Got it!'),
    ];

    $agent = new MediaTrackingAgent(history: $history);

    $this->assertSame($history, iterator_to_array($agent->messages()));
});

test('MediaTrackingAgent tools() includes injected RequestConfirmation instance', function () {
    /** @var TestCase $this */
    $confirmationTool = new RequestConfirmation();
    $agent = new MediaTrackingAgent(confirmationTool: $confirmationTool);

    $tools = collect($agent->tools());
    $this->assertTrue($tools->contains($confirmationTool));
});

test('MediaTrackingAgent tools() includes a RequestConfirmation when none injected', function () {
    /** @var TestCase $this */
    $agent = new MediaTrackingAgent();

    $tools = collect($agent->tools());
    $this->assertTrue($tools->contains(fn ($tool) => $tool instanceof RequestConfirmation));
});
```

Also update the existing `'MediaTrackingAgent::make() with no args still works'` behaviour is covered by running the existing `make()` test — no change needed there.

- [ ] **Step 2: Run to verify new tests fail**

```bash
php artisan test tests/Feature/Ai/MediaTrackingAgentTest.php
```

Expected: new tests FAIL.

- [ ] **Step 3: Update `MediaTrackingAgent`**

Replace the content of `app/Ai/Agents/MediaTrackingAgent.php`:

```php
<?php

namespace App\Ai\Agents;

use App\Ai\Tools\RequestConfirmation;
use App\Ai\Tools\SearchMedia;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\WebSearch;
use Stringable;

#[Provider('anthropic')]
#[Model('claude-sonnet-4-6')]
class MediaTrackingAgent implements Agent, HasTools
{
    use Promptable;

    public function __construct(
        private array $history = [],
        private ?RequestConfirmation $confirmationTool = null,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are a media tracking assistant for David's personal media backlog.

        Your responses will be sent as Telegram messages using HTML parse mode. Keep replies short and conversational.

        You may use these HTML tags for light formatting:
        - <b>bold</b> for titles
        - <i>italic</i> for metadata like year or creator
        - <code>inline code</code> if needed

        Do not use headings, bullet lists, or any other HTML tags. Plain prose with occasional inline emphasis only.

        You will respond to a variety of queries from David.

        For instance he might ask:
            - If something is already in his backlog
            - To add something new to his backlog
            - To log that he watched / read / listened to something

        Before answering each prompt, think about how to best answer it: What tools will you need? You may not need to use all tools to answer each query.


        **Tracking Media**

        When David tells you about a piece of media he wants to track, identify the exact item with precision.

        Always use web search to confirm the publication year and primary creator before responding.

        Primary creator by media type:
        - Album → artist
        - Book → author
        - Movie → director
        - TV show → creator or showrunner
        - Video game → developer studio

        One creator only. Pick the single most relevant primary creator. For example, for a movie with multiple directors, pick the lead.

        Flag ambiguity. If search results reveal more than one plausible match — such as a remake, an adaptation, or multiple works with the same title — tell David and ask which one he means. For example: "I found two possibilities: 'Dune' (1965 novel by Frank Herbert) or 'Dune' (2021 film by Denis Villeneuve). Which did you mean?"

        Once you have identified the item with confidence, use the SearchMedia tool to look it up in David's library by title (and media type if known).

        Interpret the SearchMedia result as follows:
        - If no results are found: the item is not in the library. Confirm the item's identity (title, year, creator, type) and let David know it is not yet in his library.
        - If found and current_status is "backlog": it is in the library but not yet started.
        - If found and current_status is "started": David is currently working through it.
        - If found and current_status is "finished": David has already finished it.
        - If found and current_status is "abandoned": David previously abandoned it.

        Once you have identified the item and checked the library, confirm back concisely: title, year, primary creator, media type, and current library status.


        **Answering questions about David's Media Library**
        Some questions will not need information from the internet, but instead simply require you to use the SearchMedia tool to explore the database. You may need to run multiple queries.


        **Requesting Confirmation**

        When you are ready to take an action — such as adding a new item to the library or logging a media event — call the RequestConfirmation tool. This signals to the interface to present a Confirm/Cancel button to David.

        Only call RequestConfirmation when:
        - You have identified the exact media item (title, year, creator, type confirmed via web search)
        - You have checked the library via SearchMedia
        - All ambiguity is resolved (if there were multiple matches, David has clarified which one)
        - You know exactly what actions are needed (create media record, add event, etc.)

        Do not call RequestConfirmation for questions about the library — only for actions.

        In your response text (written at the same time as calling RequestConfirmation), describe the plan clearly and concisely. Example: "Add <b>The Hobbit</b> (1937) by J.R.R. Tolkien — Book to your library, and log a <i>started</i> event."

        PROMPT;
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return $this->history;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new WebSearch,
            new SearchMedia,
            $this->confirmationTool ?? new RequestConfirmation,
        ];
    }
}
```

- [ ] **Step 4: Run all agent tests**

```bash
php artisan test tests/Feature/Ai/MediaTrackingAgentTest.php
```

Expected: PASS (all tests including new ones).

- [ ] **Step 5: Commit**

```bash
git add app/Ai/Agents/MediaTrackingAgent.php tests/Feature/Ai/MediaTrackingAgentTest.php
git commit -m "feat: add history and RequestConfirmation injection to MediaTrackingAgent"
```

---

## Task 3: `TrackConversation` — plain text path

**Files:**
- Create: `app/Telegram/Conversations/TrackConversation.php`
- Create: `tests/Feature/Telegram/TrackConversationTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Telegram/TrackConversationTest.php`:

```php
<?php

use App\Ai\Agents\MediaTrackingAgent;
use App\Ai\Tools\RequestConfirmation;
use Illuminate\Foundation\Testing\TestCase;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\User\User;
use SergiX44\Nutgram\Testing\FakeNutgram;

function davidUser(): User
{
    return User::make(
        id: config('nutgram.owner_user_id'),
        is_bot: false,
        first_name: 'David',
    );
}

test('/track sends agent response as plain text when confirmation not requested', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(['Which Dune did you mean?']);

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());

    $bot->hearText('/track mark dune as finished')
        ->reply()
        ->assertReplyText('Which Dune did you mean?')
        ->assertActiveConversation();
});

test('/track keeps conversation active for follow-up after plain text response', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(['Which Dune did you mean?', 'Got it.']);

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());

    $bot->hearText('/track mark dune as finished')->reply();
    $bot->hearText('the novel')
        ->reply()
        ->assertReplyText('Got it.')
        ->assertActiveConversation();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Telegram/TrackConversationTest.php
```

Expected: FAIL — class not found.

- [ ] **Step 3: Create `TrackConversation` with plain text path only**

Create `app/Telegram/Conversations/TrackConversation.php`:

```php
<?php

namespace App\Telegram\Conversations;

use App\Ai\Agents\MediaTrackingAgent;
use App\Ai\Tools\RequestConfirmation;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class TrackConversation extends Conversation
{
    /** @var Message[] */
    protected array $messageHistory = [];

    public function start(Nutgram $bot, string $text): void
    {
        $this->runAgentTurn($bot, $text);
    }

    public function converse(Nutgram $bot): void
    {
        $this->runAgentTurn($bot, $bot->message()->text ?? '');
    }

    public function awaitConfirmation(Nutgram $bot): void
    {
        // Implemented in Task 5
    }

    private function runAgentTurn(Nutgram $bot, string $userText): void
    {
        $confirmationTool = app(RequestConfirmation::class);
        $agent = new MediaTrackingAgent($this->messageHistory, $confirmationTool);
        $response = $agent->prompt($userText);

        $this->messageHistory[] = new Message(MessageRole::User, $userText);
        $this->messageHistory[] = new Message(MessageRole::Assistant, $response->text);

        $bot->sendMessage($response->text, parse_mode: ParseMode::HTML);
        $this->next('converse');
    }
}
```

Also add this to `routes/telegram.php` temporarily (will be fully replaced in Task 8 — add alongside existing routes for now so tests can run):

```php
$bot->onCommand('track {text}', function (Nutgram $bot, string $text) {
    App\Telegram\Conversations\TrackConversation::begin($bot, data: [trim($text)]);
})->middleware(App\Telegram\Middleware\OnlyDavidMiddleware::class);
```

Add it BEFORE the existing `registerCommand(TrackCommand::class)` line so it takes precedence.

- [ ] **Step 4: Run tests**

```bash
php artisan test tests/Feature/Telegram/TrackConversationTest.php
```

Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Telegram/Conversations/TrackConversation.php tests/Feature/Telegram/TrackConversationTest.php routes/telegram.php
git commit -m "feat: scaffold TrackConversation with plain-text agent response path"
```

---

## Task 4: `TrackConversation` — confirmation path

**Files:**
- Modify: `app/Telegram/Conversations/TrackConversation.php`
- Modify: `tests/Feature/Telegram/TrackConversationTest.php`

- [ ] **Step 1: Add failing test**

Add to `tests/Feature/Telegram/TrackConversationTest.php`:

```php
test('/track sends message with inline keyboard when agent calls RequestConfirmation', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(['Add "The Hobbit" (1937) by J.R.R. Tolkien — Book to your library.']);
    app()->bind(RequestConfirmation::class, fn () => tap(new RequestConfirmation(), fn ($t) => $t->handle(new \Laravel\Ai\Tools\Request([]))));

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());

    $bot->hearText('/track Add The Hobbit to my backlog')
        ->reply()
        ->assertReplyText('Add "The Hobbit" (1937) by J.R.R. Tolkien — Book to your library.')
        ->assertReplyMessage([
            'reply_markup' => [
                'inline_keyboard' => [[
                    ['text' => '✓ Confirm', 'callback_data' => 'confirm'],
                    ['text' => '✗ Cancel', 'callback_data' => 'cancel'],
                ]],
            ],
        ])
        ->assertActiveConversation();
});
```

- [ ] **Step 2: Run to verify it fails**

```bash
php artisan test tests/Feature/Telegram/TrackConversationTest.php --filter="sends message with inline keyboard"
```

Expected: FAIL — no reply_markup in response.

- [ ] **Step 3: Add confirmation branch to `runAgentTurn()`**

Replace `runAgentTurn()` in `TrackConversation`:

```php
private function runAgentTurn(Nutgram $bot, string $userText): void
{
    $confirmationTool = app(RequestConfirmation::class);
    $agent = new MediaTrackingAgent($this->messageHistory, $confirmationTool);
    $response = $agent->prompt($userText);

    $this->messageHistory[] = new Message(MessageRole::User, $userText);
    $this->messageHistory[] = new Message(MessageRole::Assistant, $response->text);

    if ($confirmationTool->wasRequested()) {
        $bot->sendMessage(
            $response->text,
            reply_markup: InlineKeyboardMarkup::make()->addRow(
                InlineKeyboardButton::make('✓ Confirm', callback_data: 'confirm'),
                InlineKeyboardButton::make('✗ Cancel', callback_data: 'cancel'),
            ),
            parse_mode: ParseMode::HTML,
        );
        $this->next('awaitConfirmation');
    } else {
        $bot->sendMessage($response->text, parse_mode: ParseMode::HTML);
        $this->next('converse');
    }
}
```

- [ ] **Step 4: Run all conversation tests**

```bash
php artisan test tests/Feature/Telegram/TrackConversationTest.php
```

Expected: PASS (all tests so far).

- [ ] **Step 5: Commit**

```bash
git add app/Telegram/Conversations/TrackConversation.php tests/Feature/Telegram/TrackConversationTest.php
git commit -m "feat: send inline keyboard when agent calls RequestConfirmation"
```

---

## Task 5: `TrackConversation` — `awaitConfirmation` step

**Files:**
- Modify: `app/Telegram/Conversations/TrackConversation.php`
- Modify: `tests/Feature/Telegram/TrackConversationTest.php`

- [ ] **Step 1: Add failing tests**

Add to `tests/Feature/Telegram/TrackConversationTest.php`:

```php
test('tapping Confirm ends the conversation with acknowledgement', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(['Add "The Hobbit" (1937) by J.R.R. Tolkien — Book to your library.']);
    app()->bind(RequestConfirmation::class, fn () => tap(new RequestConfirmation(), fn ($t) => $t->handle(new \Laravel\Ai\Tools\Request([]))));

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());

    $bot->hearText('/track Add The Hobbit')->reply();

    $bot->hearCallbackQueryData('confirm')
        ->reply()
        ->assertReplyText('✓ Done. (DB writes coming in next milestone)')
        ->assertNoConversation();
});

test('tapping Cancel ends the conversation with cancellation message', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(['Add "The Hobbit" (1937) by J.R.R. Tolkien — Book to your library.']);
    app()->bind(RequestConfirmation::class, fn () => tap(new RequestConfirmation(), fn ($t) => $t->handle(new \Laravel\Ai\Tools\Request([]))));

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());

    $bot->hearText('/track Add The Hobbit')->reply();

    $bot->hearCallbackQueryData('cancel')
        ->reply()
        ->assertReplyText('Cancelled. Nothing was changed.')
        ->assertNoConversation();
});

test('stray text while awaiting confirmation sends a reminder and stays active', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(['Add "The Hobbit" (1937) by J.R.R. Tolkien — Book to your library.']);
    app()->bind(RequestConfirmation::class, fn () => tap(new RequestConfirmation(), fn ($t) => $t->handle(new \Laravel\Ai\Tools\Request([]))));

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());

    $bot->hearText('/track Add The Hobbit')->reply();

    $bot->hearText('actually wait')
        ->reply()
        ->assertReplyText('Please tap Confirm or Cancel.')
        ->assertActiveConversation();
});
```

- [ ] **Step 2: Run to verify they fail**

```bash
php artisan test tests/Feature/Telegram/TrackConversationTest.php --filter="tapping|stray text"
```

Expected: FAIL.

- [ ] **Step 3: Implement `awaitConfirmation()`**

Replace the stub `awaitConfirmation()` in `TrackConversation`:

```php
public function awaitConfirmation(Nutgram $bot): void
{
    if (! $bot->isCallbackQuery()) {
        $bot->sendMessage('Please tap Confirm or Cancel.');
        $this->next('awaitConfirmation');

        return;
    }

    $bot->answerCallbackQuery();

    if ($bot->callbackQuery()?->data === 'confirm') {
        $bot->sendMessage('✓ Done. (DB writes coming in next milestone)');
    } else {
        $bot->sendMessage('Cancelled. Nothing was changed.');
    }

    $this->end();
}
```

- [ ] **Step 4: Run all conversation tests**

```bash
php artisan test tests/Feature/Telegram/TrackConversationTest.php
```

Expected: PASS (all tests).

- [ ] **Step 5: Commit**

```bash
git add app/Telegram/Conversations/TrackConversation.php tests/Feature/Telegram/TrackConversationTest.php
git commit -m "feat: implement awaitConfirmation step with confirm/cancel/guard handling"
```

---

## Task 6: `TrackConversation` — error handling

**Files:**
- Modify: `app/Telegram/Conversations/TrackConversation.php`
- Modify: `tests/Feature/Telegram/TrackConversationTest.php`

- [ ] **Step 1: Add failing test**

Add to `tests/Feature/Telegram/TrackConversationTest.php`:

```php
test('/track ends conversation with error message when AI provider fails', function () {
    /** @var TestCase $this */
    MediaTrackingAgent::fake(fn () => throw \Laravel\Ai\Exceptions\InsufficientCreditsException::forProvider('anthropic'));

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(davidUser());

    $bot->hearText('/track Add The Hobbit')
        ->reply()
        ->assertReplyText('Error: AI provider [anthropic] has insufficient credits or quota.')
        ->assertNoConversation();
});
```

- [ ] **Step 2: Run to verify it fails**

```bash
php artisan test tests/Feature/Telegram/TrackConversationTest.php --filter="AI provider fails"
```

Expected: FAIL — exception bubbles up uncaught.

- [ ] **Step 3: Add try/catch to `runAgentTurn()`**

Wrap the body of `runAgentTurn()` in a try/catch:

```php
private function runAgentTurn(Nutgram $bot, string $userText): void
{
    try {
        $confirmationTool = app(RequestConfirmation::class);
        $agent = new MediaTrackingAgent($this->messageHistory, $confirmationTool);
        $response = $agent->prompt($userText);

        $this->messageHistory[] = new Message(MessageRole::User, $userText);
        $this->messageHistory[] = new Message(MessageRole::Assistant, $response->text);

        if ($confirmationTool->wasRequested()) {
            $bot->sendMessage(
                $response->text,
                reply_markup: InlineKeyboardMarkup::make()->addRow(
                    InlineKeyboardButton::make('✓ Confirm', callback_data: 'confirm'),
                    InlineKeyboardButton::make('✗ Cancel', callback_data: 'cancel'),
                ),
                parse_mode: ParseMode::HTML,
            );
            $this->next('awaitConfirmation');
        } else {
            $bot->sendMessage($response->text, parse_mode: ParseMode::HTML);
            $this->next('converse');
        }
    } catch (AiException $e) {
        Log::error('MediaTrackingAgent failed in TrackConversation', ['exception' => $e]);
        $bot->sendMessage("Error: {$e->getMessage()}");
        $this->end();
    }
}
```

- [ ] **Step 4: Run all conversation tests**

```bash
php artisan test tests/Feature/Telegram/TrackConversationTest.php
```

Expected: PASS (all tests).

- [ ] **Step 5: Commit**

```bash
git add app/Telegram/Conversations/TrackConversation.php tests/Feature/Telegram/TrackConversationTest.php
git commit -m "feat: catch AiException in TrackConversation and end conversation with error message"
```

---

## Task 7: Wire up routes, delete `TrackCommand`

**Files:**
- Modify: `routes/telegram.php`
- Delete: `app/Telegram/Commands/TrackCommand.php`
- Delete: `tests/Feature/Telegram/TrackCommandTest.php`

- [ ] **Step 1: Replace the TrackCommand entries in `routes/telegram.php`**

Remove:
```php
$bot->registerCommand(App\Telegram\Commands\TrackCommand::class)
    ->middleware(OnlyDavidMiddleware::class);
$bot->onCommand('track', function (Nutgram $bot) {
    $bot->sendMessage(App\Telegram\Commands\TrackCommand::usageMessage());
})->middleware(OnlyDavidMiddleware::class);
```

And the temporary `onCommand('track {text}', ...)` added in Task 3.

Replace with:

```php
$bot->onCommand('track {text}', function (Nutgram $bot, string $text) {
    App\Telegram\Conversations\TrackConversation::begin($bot, data: [trim($text)]);
})->middleware(OnlyDavidMiddleware::class);

$bot->onCommand('track', function (Nutgram $bot) {
    $bot->sendMessage("Usage: /track <description>\nExample: /track Add The Hobbit to my backlog");
})->middleware(OnlyDavidMiddleware::class);
```

- [ ] **Step 2: Delete `TrackCommand` and its tests**

```bash
rm app/Telegram/Commands/TrackCommand.php
rm tests/Feature/Telegram/TrackCommandTest.php
```

- [ ] **Step 3: Add unauthorized user test to `TrackConversationTest`**

Add to `tests/Feature/Telegram/TrackConversationTest.php`:

```php
test('unauthorized user is rejected from /track', function () {
    /** @var TestCase $this */

    /** @var FakeNutgram $bot */
    $bot = app(Nutgram::class);
    $bot->setCommonUser(User::make(
        id: 99999,
        is_bot: false,
        first_name: 'Stranger',
    ));

    $bot->hearText('/track Add something')
        ->reply()
        ->assertReplyText('Sorry, you are not authorized to use this bot.');
});
```

- [ ] **Step 4: Run the full test suite**

```bash
php artisan test --compact --bail
```

Expected: PASS (no failures).

- [ ] **Step 5: Commit**

```bash
git add routes/telegram.php tests/Feature/Telegram/TrackConversationTest.php
git rm app/Telegram/Commands/TrackCommand.php tests/Feature/Telegram/TrackCommandTest.php
git commit -m "feat: replace TrackCommand with TrackConversation; wire up routes"
```

---

## Self-review notes

- Task 3 adds a temporary `onCommand` to routes as a scaffold — Task 7 cleans this up. The plan calls this out explicitly.
- `app()->bind(RequestConfirmation::class, ...)` in tests binds for the duration of that test only (Laravel resets bindings between tests). No teardown needed.
- `assertAnswerCallbackQuery` is not available as a named helper, but `assertCalled('answerCallbackQuery')` could be added to button tests if desired — omitted here to keep tests focused on observable behaviour (reply text + conversation state).
- The `davidUser()` helper function is defined at the top of `TrackConversationTest.php`. Pest function definitions at file scope are scoped to that file.
