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
- Nutgram supports multi-turn **Conversations** (state machines) — used for the confirmation step.

## Data Model

```
Media           — title, year, media_type_id, creator_id
MediaEvent      — media_id, media_event_type_id, occurred_at, comment
MediaEventType  — name (started | finished | abandoned | comment)
MediaType       — name (Book, Movie, etc.)
Creator         — name
```

## Design

**One back-and-forth.** The user sends a natural-language message. The bot figures everything out — parses intent, identifies the media item (web search + DB lookup), checks current state, plans the actions — then presents a single confirmation. The user taps Confirm or Cancel.

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

### 1 — NL intent parsing (current)

Implement `/track` using a Laravel AI Agent. The agent parses the user's message, uses the `WebSearch` tool to identify the media item, and returns a structured plan. No DB interaction or confirmation UI yet.

- `TrackCommand` at `app/Telegram/Commands/TrackCommand.php`
- `MediaTrackingAgent` at `app/Ai/Agents/MediaTrackingAgent.php` with `#[Provider('anthropic')]` and `WebSearch` enabled
- Agent returns structured output: `intent`, `media` (title/type/creator/year), `comment` (if any) via `HasStructuredOutput`
- Command sends the plan back as plain text (no buttons yet)
- Register in `routes/telegram.php` with `OnlyDavidMiddleware`

### 2 — DB state resolution

After the agent identifies the media item and intent, cross-reference against the DB.

- Look up `Media` by title + type (case-insensitive)
- Surface current state in the confirmation message: "Not currently in your library", "Last event: Started on Jan 3", etc.
- Pass DB state back to the agent so it can refine the plan if needed (e.g. already finished)

### 3 — Confirmation UI and execution

Add the inline keyboard and write to DB on confirm.

- Convert `/track` into a Nutgram **Conversation** to hold state across the button tap
- Send confirmation with `[✓ Confirm]  [✗ Cancel]` inline keyboard
- **Confirm:** resolve or create `MediaType`, `Creator`, `Media` as needed; insert `MediaEvent`(s) with `occurred_at = now()`; reply with summary
- **Cancel:** reply "Cancelled. Nothing was changed."

### 4 — Ambiguity handling

- If the agent can't identify the media item or intent is unclear, ask for clarification rather than guessing
- Retry up to 2 times before giving up

## Out of Scope

- Editing or deleting existing events
- Browsing/searching the library via bot
