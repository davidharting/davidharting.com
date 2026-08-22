---
name: telegram-media-tracking-bot
status: in-progress
---

# Telegram Media Tracking Bot

A `/track` Telegram bot command for logging media events via natural language — no admin panel needed.

## Background

The site has a media tracking system with `Media` and `MediaEvent` models. Events are typed via `MediaEventTypeName`: `started`, `finished`, `abandoned`, `comment`. Today, media and events are managed via Filament or one-off scripts.

## Current State

- Telegram bot is live using **Nutgram**. Only David can use it (`OnlyDavidMiddleware`).
- Existing commands: `/whoami`, `/example`.
- `laravel/ai` installed with Anthropic as the default provider. `ANTHROPIC_API_KEY` in `.env`.
- `/track` is live as a multi-turn `TrackConversation` (Nutgram Conversation state machine).
    - Agent can ask clarifying questions before presenting the confirmation UI.
    - Plain-text (non-confirmation) responses include a `End` inline button to close the conversation.
    - Full conversation history (including tool calls and results) is persisted to `agent_conversations` / `agent_conversation_messages` via `RemembersConversations`.
    - Agent turns run on the queue worker via `App\Jobs\RunTrackAgentTurn`; the webhook only acknowledges and dispatches. See "Background agent turns" below.
    - `/end` purges an in-flight conversation.
    - DB writes on confirm are not yet implemented (placeholder acknowledgement sent).

## Data Model

```
Media           — title, year, media_type_id, creator_id
MediaEvent      — media_id, media_event_type_id, occurred_at, comment
MediaEventType  — name (started | finished | abandoned | comment)
MediaType       — name (Book, Movie, etc.)
Creator         — name
```

## Design

**Multi-turn conversation.** The user sends a natural-language message. The agent may ask clarifying questions (e.g. to resolve ambiguity between two works with the same title) before presenting a confirmation. Once ready, it presents a single confirmation. The user taps Confirm or Cancel. Informational queries (no action needed) receive a plain-text response with a `✓ End` button to close the conversation.

## Background agent turns

A `MediaTrackingAgent` turn is a multi-step Sonnet run with web search behind it and can
take minutes — far longer than Telegram waits for a webhook response before redelivering
the update, which used to mean a slow turn could run twice and produce two confirmation
prompts.

So the webhook never runs the agent. Each step of `TrackConversation` parks itself on a
`working` step, persists, and dispatches `RunTrackAgentTurn`; the job runs the agent on
`davidhartingdotcom-worker`, sends the reply, and advances the conversation to its next
step. The webhook returns in well under a second.

```
Telegram -> webhook -> TrackConversation: next('working') -> dispatch -> 200 OK
                                                                 |
                    worker: RunTrackAgentTurn -> agent turn -> sendMessage
                                                            -> stepConversation('converse')
```

Things worth knowing before changing this:

- **State must persist before the job is dispatched.** The worker is a separate container
  that can pick the job up as soon as the row lands, and its write to the conversation
  cache must not be clobbered by the webhook's.
- **`CACHE_DRIVER` must stay shared** between web and worker (`database` in `render.yaml`).
  Nutgram keeps conversation state in the cache, and the two services have separate
  filesystems, so a `file` driver would break the handoff silently.
- **The job carries a turn id** that must still match the parked conversation before it
  posts anything. That is what stops a turn superseded by `/end` — or by a newer `/track` —
  from dropping a stale reply on top of a fresher one.
- **The job never retries** (`$tries = 1`). A confirmed turn commits media events, and a
  retry would apply them twice. `failed()` reports the error and ends the conversation so
  a blown-up turn cannot leave the conversation parked on `working` forever.
- **`retry_after` must outrun the job timeout** on the database queue connection, or the
  driver re-reserves a turn that is still running and a second worker picks it up.

## Examples

```
User: /track Add The Hobbit to my backlog
Bot:  Add "The Hobbit" (1937) by J.R.R. Tolkien — Book to your library.
      (Not currently in your library.)
      [✓ Confirm]  [✗ Cancel]
User: [✓ Confirm]
Bot:  ✓ Done. "The Hobbit" added to your library.
```

```
User: /track mark dune as finished
Bot:  Add a "finished" event to "Dune" (1965) by Frank Herbert — Book.
      (Last event: Started on Jan 3.)
      [✓ Confirm]  [✗ Cancel]
```

```
User: /track log that i started reading blood meridian
Bot:  Add "Blood Meridian" (1985) by Cormac McCarthy — Book to your library.
      Add a "started" event.
      (Not currently in your library.)
      [✓ Confirm]  [✗ Cancel]
```

## Milestones

### ✓ 1 — NL intent parsing

`/track` implemented using `MediaTrackingAgent` (Anthropic Sonnet, `WebSearch` + `SearchMedia` tools). Agent returns a plain-text response. Registered in `routes/telegram.php` with `OnlyDavidMiddleware`.

### 2 — DB state resolution

After the agent identifies the media item and intent, cross-reference against the DB.

- Look up `Media` by title + type (case-insensitive)
- Surface current state in the confirmation message: "Not currently in your library", "Last event: Started on Jan 3", etc.
- Pass DB state back to the agent so it can refine the plan if needed (e.g. already finished)

### 3 — Confirmation UI and execution

#### ✓ 3a — Confirmation UI and multi-turn conversation

`/track` converted to a Nutgram `TrackConversation`. Agent signals readiness via a `RequestConfirmation` tool; conversation sends `[✓ Confirm] [✗ Cancel]` inline keyboard. Agent may ask clarifying questions before reaching confirmation. Plain-text responses include a `✓ End` button. Full history (including tool calls) persisted via `RemembersConversations`.

#### 3b — DB writes on confirm (current)

- **Confirm:** resolve or create `MediaType`, `Creator`, `Media` as needed; insert `MediaEvent`(s) with `occurred_at = now()`; reply with summary
- **Cancel:** already implemented ("Cancelled. Nothing was changed.")

### 4 — Ambiguity handling

- If the agent can't identify the media item or intent is unclear, ask for clarification rather than guessing
- Retry up to 2 times before giving up

## Optimizations

### ResolveMediaAgent — cheap Haiku sub-agent for media identification

Web search burns a lot of input tokens, making the main agent expensive even during testing. Extract media identification into a dedicated sub-agent that runs on **Claude Haiku** — the task is narrow enough (tool calling + structured output) that Haiku can handle it.

This follows the [Orchestrator-Worker pattern](https://laravel.com/blog/building-multi-agent-workflows-with-the-laravel-ai-sdk): `MediaTrackingAgent` is the orchestrator; `ResolveMediaAgent` is the worker. In the Laravel AI SDK, workers are implemented as a pair of classes: an **Agent** class (the worker logic) and a **Tool** class (the adapter that lets the orchestrator invoke it). Here that's `ResolveMediaAgent` + `ResolveMediaTool`.

**Responsibility:** Given a raw media reference (extracted by the orchestrator from the user's message), perform a web search to confirm the exact title, year, primary creator, and media type.

**Output:** An array of matches (to handle ambiguity), each with:

- `title` — official title
- `year` — publication/release year
- `creator` — primary creator (author, director, etc.)
- `media_type` — Book, Movie, etc.

**Integration:** The primary `MediaTrackingAgent` calls `ResolveMediaTool` and inspects the result:

- **One match:** proceed with the plan
- **Multiple matches:** present options to the user for disambiguation (ties into Milestone 4)
- **No matches:** fall through to ambiguity handling

## Out of Scope

- Editing or deleting existing events
- Browsing/searching the library via bot
