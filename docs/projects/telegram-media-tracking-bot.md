---
name: telegram-media-tracking-bot
description: A /track Telegram bot command for logging media events against the existing media library
status: in-progress
---

# Telegram Media Tracking Bot

## Background

The site already has a media tracking system (books, movies, etc.) with a `Media` model and
`MediaEvent` model. Events are typed via the `MediaEventTypeName` enum: `started`, `finished`,
`abandoned`, `comment`. Today, media and events are managed via the Filament admin panel or
one-off import scripts.

The goal is a `/track` Telegram bot command that lets me log media events conversationally from
my phone — without opening the admin panel.

## Current State

- Telegram bot is live, using **Nutgram**. Only David can use it (via `OnlyDavidMiddleware`).
- Existing commands: `/whoami`, `/example`.
- Bot responds to free-form messages with "I cannot respond to general conversation yet."
- Nutgram supports multi-turn **Conversations** (state machines) — this is the mechanism for
  confirmation and action-selection flows.

## Data Model (relevant parts)

```
Media           — title, year, media_type_id, creator_id
MediaEvent      — media_id, media_event_type_id, occurred_at, comment
MediaEventType  — name (enum: started | finished | abandoned | comment)
MediaType       — name (Book, Movie, etc.)
Creator         — name
```

## Full Intended Flow (all milestones)

```
User: /track Dune
Bot:  🔍 Looking that up...
Bot:  I found: Dune (1965) — Book by Frank Herbert
      [✓ Yes, that's it]  [✗ No, try again]

User: [✓ Yes]
Bot:  [checks DB]
      Found in your library. Last event: Started on Jan 3.
      What would you like to record?
      [Mark finished]  [Mark abandoned]  [Add comment]

  — or if not in DB —

      Not in your library yet. Adding it now.
      What would you like to record?
      [Add to backlog]  [Mark started]  [Add comment]

User: [Mark finished]
Bot:  ✓ Logged: Dune — finished on Mar 1, 2026.
```

---

## Milestones

### Milestone 1 — Web search identification ✅ (current)

**What:** Implement the `/track` command. It takes the user's query, calls the Claude API with
the built-in web search tool, and returns a formatted identification of the media item.

**Scope:**
- New `TrackCommand` class at `app/Telegram/Commands/TrackCommand.php`
- Calls Anthropic API (HTTP or SDK) with the `web_search` tool and a prompt designed to identify
  a single media item (title, type, creator, year)
- Responds with a plain-text summary, e.g. `I found: Dune (1965) — Book by Frank Herbert`
- No confirmation, no DB interaction yet
- Bot replies with "I'm not sure what that is" if Claude cannot identify it

**Technical notes:**
- Use the `ANTHROPIC_API_KEY` env var (add to `.env.example`)
- Call the Anthropic Messages API directly via HTTP (no extra package needed unless one is
  already present)
- Keep the prompt tightly scoped: ask Claude to return structured JSON (title, type, creator,
  year) and parse it in PHP

---

### Milestone 2 — Confirmation loop

**What:** After presenting the identified item, show inline keyboard buttons so the user can
confirm or reject it. On rejection, retry the search (optionally with a follow-up clarification).

**Scope:**
- Convert `/track` into a Nutgram **Conversation** to hold multi-turn state
- After identification, send the result with two inline keyboard buttons:
  `✓ Yes, that's it` / `✗ No, try again`
- **Yes:** acknowledge with "Got it! (Actions coming soon)" and end the conversation
- **No:** ask "What should I search for instead?" then re-run Milestone 1 logic with the
  new query. Retry up to 3 times before giving up.
- Conversation state must persist between bot messages (Nutgram handles this via cache)

---

### Milestone 3 — Media record resolution

**What:** After the user confirms the item (Milestone 2 "Yes" branch), resolve it against the DB.
Create a new `Media` record if it doesn't exist; look it up if it does.

**Scope:**
- After confirmation, query `Media` by title (case-insensitive) and `MediaType`
- **Found:** surface current event state (e.g. "Last event: Started on Jan 3") — proceed to
  Milestone 4
- **Not found:**
  - Resolve or create the `MediaType` by name
  - Resolve or create the `Creator` by name
  - Create the `Media` record (title, year, type, creator)
  - Proceed to Milestone 4
- Acknowledge creation: "Not in your library yet — added it."

---

### Milestone 4 — Action selection and event recording

**What:** Present context-aware action buttons based on the media item's current event history,
then record the chosen `MediaEvent`.

**Scope:**
- Derive available actions from the most recent event (or lack thereof):

  | Current state                | Available actions                          |
  |------------------------------|--------------------------------------------|
  | No events                    | Add to backlog (no event), Mark started    |
  | Last event: started          | Mark finished, Mark abandoned, Add comment |
  | Last event: finished         | Add comment, Mark started (re-read/rewatch)|
  | Last event: abandoned        | Add comment, Mark started (re-read/rewatch)|

- "Add to backlog" records no event — it just ensures the Media row exists
- "Add comment" prompts for comment text before recording
- All other actions insert a `MediaEvent` with `occurred_at = now()`
- Final bot message: `✓ Logged: [title] — [event type] on [date].`

---

## Out of Scope (for now)

- Editing or deleting existing events
- Setting a custom `occurred_at` date
- Searching/browsing the existing library via bot
- Handling multiple matching DB records for the same title
