---
name: telegram-media-tracking-bot
description: A /track Telegram bot command for logging media events against the existing media library via natural language
status: in-progress
---

# Telegram Media Tracking Bot

## Background

The site already has a media tracking system (books, movies, etc.) with a `Media` model and
`MediaEvent` model. Events are typed via the `MediaEventTypeName` enum: `started`, `finished`,
`abandoned`, `comment`. Today, media and events are managed via the Filament admin panel or
one-off import scripts.

The goal is a `/track` Telegram bot command — accepting **natural language** — that lets me log
media events conversationally from my phone without opening the admin panel.

## Current State

- Telegram bot is live, using **Nutgram**. Only David can use it (via `OnlyDavidMiddleware`).
- Existing commands: `/whoami`, `/example`.
- Laravel AI SDK (`laravel/ai`) installed and configured with Anthropic as the default provider.
- `ANTHROPIC_API_KEY` env var wired up in `config/ai.php` and `.env.example`.
- Nutgram supports multi-turn **Conversations** (state machines) — used for the confirmation step.

## Data Model (relevant parts)

```
Media           — title, year, media_type_id, creator_id
MediaEvent      — media_id, media_event_type_id, occurred_at, comment
MediaEventType  — name (enum: started | finished | abandoned | comment)
MediaType       — name (Book, Movie, etc.)
Creator         — name
```

## Design Philosophy

**One back-and-forth.** The user sends a natural-language message describing what they want to
do. The bot figures everything out — parses intent, identifies the media item (via web search
and/or DB lookup), checks current DB state, and plans the action(s) — then presents a single
confirmation. The user taps "Confirm" or "Cancel".

This means the AI agent does all the reasoning upfront before surfacing anything to the user.

---

## Example Interactions

```
User: /track Add The Hobbit to my backlog
Bot:  Here's what I'll do:
      Add "The Hobbit" (1937) by J.R.R. Tolkien — Book — to your library.
      (Not currently in your library.)
      [✓ Confirm]  [✗ Cancel]

User: [✓ Confirm]
Bot:  ✓ Done. "The Hobbit" added to your library.
```

```
User: /track mark dune as finished
Bot:  Here's what I'll do:
      Add a "finished" event to "Dune" (1965) by Frank Herbert — Book.
      (Last event: Started on Jan 3.)
      [✓ Confirm]  [✗ Cancel]
```

```
User: /track add comment to Dune that says "slow start but worth it"
Bot:  Here's what I'll do:
      Add comment to "Dune" (1965) by Frank Herbert — Book:
      "slow start but worth it"
      [✓ Confirm]  [✗ Cancel]
```

```
User: /track log that i started reading blood meridian
Bot:  Here's what I'll do:
      Add "Blood Meridian" (1985) by Cormac McCarthy — Book to your library.
      Add a "started" event.
      (Not currently in your library.)
      [✓ Confirm]  [✗ Cancel]
```

---

## Full Intended Flow

```
1. User sends: /track <natural language>
2. Bot replies: "🔍 Working on it…"
3. Agent runs:
   a. Parse intent and target media from the message
   b. Web search to identify the media item (title, type, creator, year)
      — skippable if the item is clearly in DB already
   c. DB lookup: does this item exist? What are its current events?
   d. Plan the action(s) based on intent + DB state
   e. Build a confirmation summary
4. Bot sends confirmation message with [✓ Confirm] / [✗ Cancel] buttons
5. User taps Confirm → execute actions, report success
   User taps Cancel → "Cancelled. Nothing was changed."
```

---

## Milestones

### Milestone 1 — Natural language identification and intent parsing ✅ (current)

**What:** Implement the `/track` command using a Laravel AI Agent. The agent parses the user's
message, uses the `WebSearch` built-in tool to identify the media item, and returns a structured
plan of what it intends to do. No DB interaction, no confirmation UI yet — just get the agent
returning a well-structured plan.

**Scope:**
- New `TrackCommand` at `app/Telegram/Commands/TrackCommand.php`
- New `MediaTrackingAgent` at `app/Ai/Agents/MediaTrackingAgent.php` using `laravel/ai`
  with `#[Provider('anthropic')]` and the `WebSearch` tool enabled
- Agent prompt instructs it to:
  - Parse the user's intent (add to backlog, mark started/finished/abandoned, add comment)
  - Identify the specific media item (title, type, creator, year) via web search
  - Return structured output: `intent`, `media` (title/type/creator/year), `comment` (if any)
- Command sends the agent's plan back as a plain-text message (no buttons yet)

**Technical notes:**
- Use `HasStructuredOutput` on the agent to enforce JSON shape
- Keep the agent stateless for now (no conversation memory needed)
- Register command in `routes/telegram.php` with `OnlyDavidMiddleware`

---

### Milestone 2 — DB state resolution

**What:** After the agent identifies the media item and intent, cross-reference against the DB to
show the user the current state as part of the confirmation.

**Scope:**
- After agent returns its structured plan, look up `Media` by title + type (case-insensitive)
- Surface current event state in the confirmation message:
  - "Not currently in your library"
  - "Last event: Started on Jan 3"
  - "Already finished on Dec 12"
- Pass DB state back into the agent's context so it can refine the plan if needed
  (e.g. if user says "mark as started" but it's already finished, the agent should note that)

---

### Milestone 3 — Confirmation UI and execution

**What:** Add the inline keyboard confirmation step. On confirm, execute the planned actions
against the DB.

**Scope:**
- Convert `/track` into a Nutgram **Conversation** to hold state between the plan message and
  the user's button tap
- After agent + DB resolution, send confirmation message with:
  `[✓ Confirm]  [✗ Cancel]` inline keyboard buttons
- **Confirm:** execute all planned actions:
  - Resolve or create `MediaType`, `Creator`, `Media` as needed
  - Insert `MediaEvent`(s) with `occurred_at = now()`
  - Reply: `✓ Done. [summary]`
- **Cancel:** reply "Cancelled. Nothing was changed." and end the conversation

---

### Milestone 4 — Retry on failure / ambiguity

**What:** Handle cases where the agent can't determine intent or identify the media item.

**Scope:**
- If agent returns low-confidence or `null` for the media item, ask the user to clarify
  rather than guessing: "I couldn't identify that media item. Can you be more specific?"
- If intent is ambiguous (e.g. "update Dune" with no clear action), ask what they want to do
- Retry up to 2 times before giving up: "I'm having trouble with that. Try again?"

---

## Out of Scope (for now)

- Editing or deleting existing events
- Setting a custom `occurred_at` date
- Searching/browsing the existing library via bot
- Handling multiple matching DB records for the same title
