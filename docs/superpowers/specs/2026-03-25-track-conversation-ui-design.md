# Track Conversation UI — Design Spec

**Date:** 2026-03-25
**Milestone:** 3a — Confirmation UI (no DB writes yet)
**Status:** Approved

## Overview

Convert the `/track` command from a single-turn `TrackCommand` into a multi-turn Nutgram `Conversation`. The agent may engage in multiple planning turns (e.g., asking clarifying questions) before presenting a confirmation dialog with `[✓ Confirm]` and `[✗ Cancel]` inline keyboard buttons. DB writes are deferred to a later milestone.

---

## Components

### 1. `TrackConversation` (new)

Replaces `TrackCommand`. Extends `Conversation`. Has three steps:

- **`start`** — entry point, triggered by `/track <text>`
- **`planningTurn`** — handles free-text follow-ups; loops back to itself until confirmation is ready
- **`awaitConfirmation`** — handles the inline keyboard button tap

Both `start` and `planningTurn` run the same core logic (extracted into a private `runAgentTurn(string $userText)` method) and branch based on whether the `RequestConfirmation` tool was called. `start()` receives the text via the command parameter (same as `TrackCommand` today). `planningTurn()` reads it from `$bot->message()->text`.

Registered in `routes/telegram.php` in place of `TrackCommand`, with `OnlyDavidMiddleware`.

### 2. `RequestConfirmation` tool (new)

A Laravel AI tool the agent calls when it has enough information to present a plan. It does not send anything — it only sets an internal flag and is inspected by `TrackConversation` after `prompt()` returns.

Public interface:
- `wasRequested(): bool` — whether the agent called this tool during the last `prompt()` call
- `handle(Request $request): string` — records the call, returns a brief acknowledgement string to the agent

Schema: no required parameters. The agent calls it when ready; the content of the confirmation message comes from the agent's text response.

### 3. `MediaTrackingAgent` (updated)

Gains a constructor accepting:
- `array $history = []` — plain `['role' => ..., 'content' => ...]` arrays
- `?RequestConfirmation $confirmationTool = null` — injected tool instance

`messages()` maps history through `Message::tryFrom()`. `tools()` includes the injected `RequestConfirmation` instance (falling back to `new RequestConfirmation()` if null). `WebSearch` and `SearchMedia` are unchanged.

The agent instructions gain a new section explaining when to call `RequestConfirmation`: once it has identified the media item (via web search + SearchMedia), resolved any ambiguity, and is ready to present a plan.

### 4. `TrackCommand` (deleted)

Replaced entirely by `TrackConversation`.

---

## Conversation State

`TrackConversation` declares one serialized property:

```php
protected array $messageHistory = [];
```

Nutgram's `__serialize()` captures all instance properties (via `get_object_vars($this)`) and stores the full object in Laravel cache between turns. No manual `setData`/`getData` needed.

After each `prompt()` call, the Conversation appends two entries to `$messageHistory`:

```php
$this->messageHistory[] = ['role' => 'user',      'content' => $userText];
$this->messageHistory[] = ['role' => 'assistant',  'content' => $response->text];
```

Plain arrays are used (not `Message` objects) so the history serializes cleanly. They are converted to `Message` objects only when passed to the agent.

---

## State Machine

```
/track <text>
    └─ start()
         └─ runAgentTurn($text)
              ├─ confirmation requested  →  send text + buttons, next('awaitConfirmation')
              └─ not requested           →  send plain text,     next('planningTurn')

user sends free text
    └─ planningTurn()
         └─ runAgentTurn($bot->message()->text)
              ├─ confirmation requested  →  send text + buttons, next('awaitConfirmation')
              └─ not requested           →  send plain text,     next('planningTurn')

user taps button
    └─ awaitConfirmation()
         ├─ not a callback query         →  send reminder, next('awaitConfirmation')
         ├─ data === 'confirm'           →  send "✓ Done. (coming soon)", end()
         └─ data === 'cancel'            →  send "Cancelled. Nothing was changed.", end()
```

---

## Data Flow (single planning turn)

1. User sends `/track mark dune as finished`
2. `start()` calls `runAgentTurn('mark dune as finished')`
3. A fresh `RequestConfirmation` tool is created
4. A fresh `MediaTrackingAgent` is created with current `$messageHistory` and the tool
5. `$agent->prompt($userText)` is called — agent uses WebSearch and SearchMedia internally
6. History is updated with user + assistant messages
7. `$confirmationTool->wasRequested()` is checked:
   - **False:** send `$response->text` as plain text, `next('planningTurn')`
   - **True:** send `$response->text` + inline keyboard, `next('awaitConfirmation')`

---

## Buttons

```php
InlineKeyboardMarkup::make()->addRow(
    InlineKeyboardButton::make('✓ Confirm', callback_data: 'confirm'),
    InlineKeyboardButton::make('✗ Cancel',  callback_data: 'cancel'),
)
```

`awaitConfirmation()` reads `$bot->callbackQuery()?->data` and calls `$bot->answerCallbackQuery()` to dismiss the loading indicator.

---

## Error Handling

- `AiException` caught in `runAgentTurn()` — sends error message, calls `end()` to terminate the conversation
- If `awaitConfirmation()` receives a non-callback-query update (e.g., a stray text message) — sends "Please tap Confirm or Cancel." and loops back to `awaitConfirmation`

---

## Out of Scope (this milestone)

- DB writes on Confirm (Milestone 3b)
- Ambiguity retry loop (Milestone 4)
- Editing or deleting events

---

## Testing

Feature tests covering:

- Single-turn happy path: agent returns confirmation immediately
- Multi-turn happy path: agent asks a clarifying question, user replies, agent then confirms
- Confirm button: sends acknowledgement message, ends conversation
- Cancel button: sends cancellation message, ends conversation
- Stray text while awaiting confirmation: sends reminder, stays in `awaitConfirmation`
- `AiException` during planning: sends error, ends conversation
- Unauthorized user: rejected by middleware (existing test, unchanged)
