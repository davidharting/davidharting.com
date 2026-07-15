---
name: mcp-server
status: planned
---

# Public MCP Server

A public, unauthenticated, read-only MCP server so AI agents (Claude, Codex, etc.) can query the public content of davidharting.com — published notes and the media library — over the Model Context Protocol.

## Goals

- Agents can query published notes: list them, search them, and read full content.
- Agents can query the media library **flexibly** — any combination of title, creator, media type, tracking status, and year, not just the fixed lists the website renders.
- No OAuth / no auth of any kind for now. The server exposes only information that is already public on the website.
- Use `laravel/mcp` in the most vanilla, first-party way possible: `make:mcp-server` / `make:mcp-tool` scaffolding, `routes/ai.php` registration, first-party testing helpers, and the MCP Inspector for manual verification.

### Phase 2: `AuthenticatedServer` with OAuth (out of scope, but shapes v1 naming)

The longer-term plan is to replace the custom Telegram agent (`TrackConversation` + Nutgram) with MCP plus first-party chat bots, doing authentication **properly with OAuth 2.1** (Laravel Passport) as a deliberate learning exercise. That phase adds a second server class alongside this one, which is why the v1 server is named for its audience contract:

- **`PublicServer`** (this project) — registered at `/mcp` with no auth middleware, and it stays that way forever. Its contract: everything it returns is public information. This route can never gain OAuth, because OAuth on a route is all-or-nothing: the 401 challenge to anonymous requests is what triggers MCP OAuth discovery, so an OAuth-protected route has no anonymous access by definition.
- **`AuthenticatedServer`** (phase 2) — registered at `/mcp/auth` behind Passport (`Mcp::oauthRoutes()` + `->middleware('auth:api')`). Serves OAuth-capable clients such as Claude.ai custom connectors. Anonymous access impossible by design.

```php
// routes/ai.php — phase 2 end state
Mcp::web('/mcp', PublicServer::class)->middleware('throttle:60,1');

Mcp::oauthRoutes();
Mcp::web('/mcp/auth', AuthenticatedServer::class)
    ->middleware(['auth:api', 'throttle:60,1']);
```

Two server classes, one implementation: laravel/mcp server classes are thin manifests (a `$tools` array plus instructions), and the **tool classes are shared** between them. `AuthenticatedServer` registers a superset:

- The same four read tools as `PublicServer`, which disclose more per-user (below).
- Write tools — port of the existing `App\Ai\Tools\CreateMedia` / `CreateMediaEvent` logic.
- New read-only, auth-only tools — e.g. a `GetMedia` detail tool with full event history (the MCP equivalent of the admin-only `/media/{id}` page).

**Authenticated ≠ admin.** The system has at least one non-admin user (David's wife), who must be able to complete the OAuth flow and connect to `AuthenticatedServer` without gaining admin-level disclosure. So shared tools never branch on "is a user present" — they ask the same gates and policies the website uses:

- `QueryMedia` includes `media.note` and event comments only when `$request->user()?->can('seeNote', Media::class)`.
- Note tools include `visible = false` notes only for users passing `NotePolicy::viewAny`.
- Write tools and `GetMedia` hide themselves via `shouldRegister()` backed by policy abilities (`can('create', Media::class)`, `can('view', $media)`), so a non-admin user doesn't even see them in `tools/list`.

A non-admin authenticated user therefore sees exactly the public data set until policies grant more. Authorization lives in the policies; MCP is just another consumer of them, like the Blade templates.

Consequences for v1:

- Name the server `PublicServer` now, so the phase-2 pair is symmetrical from day one.
- Each tool makes its visibility decision in one obvious place (the `visible = true` predicate in note tools, the column list in `QueryMedia`), written with the intent of later replacing that hardcoded "guest" assumption with the corresponding policy check — a deliberate, testable change per tool rather than a hunt.

## Current state

- `laravel/mcp` is **not** a direct dependency. It is only installed transitively in `require-dev` via `laravel/boost`, so it is unavailable in production today.
- `laravel/ai` agent tools already exist (`App\Ai\Tools\SearchMedia`, etc.) for the Telegram bot. They are Laravel AI `Tool` implementations, not MCP tools — similar shape (description / handle / schema), different contract.
- Reusable query objects exist in `App\Queries\Media` (`SearchMediaQuery`, `LogbookQuery`, `BacklogQuery`, `InProgressQuery`) and the `media_tracking_summary` Postgres view (`MediaTrackingSummary` model) already computes title, year, media type, creator, current status, and started/finished/abandoned timestamps per media item.

## What is public (visibility rules)

The MCP server must expose exactly what the website exposes to a logged-out visitor — no more.

| Data                                                                 | Public?             | Source of truth                                              |
| -------------------------------------------------------------------- | ------------------- | ------------------------------------------------------------ |
| Notes with `visible = true` (slug, title, lead, markdown, published) | Yes                 | `NotePolicy::view`, `NotesIndexController`                   |
| Notes with `visible = false`                                         | No (site 404s)      | `NotePolicy::view`                                           |
| Media title, year, media type, creator                               | Yes                 | `/media` index                                               |
| Media tracking status + started/finished/abandoned dates             | Yes                 | `/media` index (finished / backlog / in-progress lists)      |
| `media.note`                                                         | **No** (admin only) | `MediaPolicy::seeNote` gate in `media/index.blade.php`       |
| Media event `comment` (incl. `finished_comment`)                     | **No** (admin only) | Rendered inside the same `$canSeeNote` guard in the template |
| Media detail page `/media/{id}`                                      | No (admin only)     | `MediaPolicy::view`                                          |

The two admin-only text fields are the main trap: `LogbookQuery` _selects_ `media.note` and `finished_comment`, but the Blade template hides them from guests. MCP tools must never include these columns. `MediaTrackingSummary` conveniently contains no private columns, which is one reason to build the media tool on it.

## Design

### Package installation

Promote `laravel/mcp` to a direct production dependency and publish the routes file:

```bash
composer require laravel/mcp
php artisan vendor:publish --tag=ai-routes   # creates routes/ai.php
```

`routes/ai.php` is auto-loaded by the package's service provider via `Route::group([], $path)` — no `web` middleware group, so no CSRF or session involvement. `Mcp::web()` registers a plain POST route (plus GET/DELETE 405 responders) handling the MCP Streamable HTTP transport.

### Server registration

```php
// routes/ai.php
use App\Mcp\Servers\PublicServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', PublicServer::class)
    ->middleware('throttle:60,1');
```

- Public endpoint at `https://davidharting.com/mcp`. No auth middleware — that is the entire "no OAuth, public only" story; anonymous requests get `$request->user() === null` and every registered tool is public.
- A standard `throttle` middleware protects the database from abusive clients. Start with the inline `60,1` limiter; introduce a named limiter only if we need something fancier.

### Server class

```bash
php artisan make:mcp-server PublicServer
```

`app/Mcp/Servers/PublicServer.php` extends `Laravel\Mcp\Server`, registering the four tools below in its `$tools` array. The name is the contract: this server only ever serves public information, anonymously — its authenticated sibling (`AuthenticatedServer`, phase 2) is a separate class on a separate route. Set `$serverName` ("davidharting.com"), `$serverVersion`, and `$instructions` — the instructions string is where we tell agents what this server is (David Harting's personal website: published notes and a media tracking library), the media type / status vocabularies, and that all data is public.

### Tools

Four tools, created with `php artisan make:mcp-tool <Name>`, living in `app/Mcp/Tools/`. All are annotated `#[IsReadOnly]` and `#[IsIdempotent]` (`Laravel\Mcp\Server\Tools\Annotations`), with `#[Description]` text modeled on the thorough style of the existing `App\Ai\Tools\SearchMedia::description()`. Input schemas use the first-party `JsonSchema` builder; argument validation uses `$request->validate()` so invalid input returns MCP-friendly errors.

Structured results use `Response::structured([...])` with a matching `outputSchema()`; prose/markdown results use `Response::text()`.

#### 1. `ListNotes`

List published notes, newest first.

- **Input**: optional `page` / `per_page` (bounded, e.g. max 50, default 20).
- **Query**: `Note::where('visible', true)->orderByDesc('published_at')` — the same predicate as `NotesIndexController`.
- **Output** (structured): `notes: [{slug, title, lead, published_at, url}]` plus `total` and paging info. `url` comes from `route('notes.show', $slug)` so agents can cite the public page.
- Excludes `markdown_content` to keep list responses small; agents follow up with `GetNote`.

#### 2. `SearchNotes`

Full-text-ish search over published notes.

- **Input**: required `query` string.
- **Query**: `visible = true` AND case-insensitive `whereLike` (with `App\Support\LikePattern::escape`, matching existing convention) against `title`, `lead`, and `markdown_content`.
- **Output** (structured): same item shape as `ListNotes` plus a short matched-context `snippet` from `markdown_content` so agents can judge relevance without fetching every note.

#### 3. `GetNote`

Fetch one published note in full.

- **Input**: required `slug`.
- **Query**: by slug, `visible = true`. A missing **or invisible** slug returns the same `Response::error('Note not found.')` — mirroring the site's 404-for-invisible behavior, never confirming a hidden note exists.
- **Output**: `Response::text()` — the note rendered as markdown (title, lead, `published_at`, canonical URL, then raw `markdown_content`). Markdown text is the friendliest shape for LLM consumption of an article, and matches how the site already serves markdown to agents via `spatie/laravel-markdown-response`.

#### 4. `QueryMedia`

The flexible one. A single tool that can answer everything the website's three lists answer, plus free combinations the website can't.

- **Backed by** the `media_tracking_summary` view via the `MediaTrackingSummary` model — it already computes `current_status` (backlog / started / finished / abandoned) and the three timestamps, and contains no admin-only columns.
- **Input** (all optional; no arguments = the full library):
    - `title` — partial, case-insensitive match
    - `creator` — partial, case-insensitive match
    - `media_type` — enum from `MediaTypeName` (album, book, movie, tv show, video game)
    - `status` — enum: backlog, started, finished, abandoned
    - `finished_year` / `started_year` — integer; year extracted from `finished_at` / `started_at` (this is how the site's logbook year filter works)
    - `year` — integer; the work's release year (distinct from the above — the schema descriptions must spell out the difference)
    - `sort` — enum: `recently_finished` (default when status=finished), `recently_started`, `recently_added`, `title`, `year`
    - `limit` / `page` — bounded pagination (e.g. max 100 per page)
- **Output** (structured): `results: [{media_id, title, year, media_type, creator, current_status, started_at, finished_at, abandoned_at}]` plus `total` and paging info. **Never** `media.note` or event comments.
- **Implementation**: extend `App\Queries\Media\SearchMediaQuery` with the new optional constructor parameters (status, years, sort, pagination) rather than writing a parallel query class. All new parameters default to null/off, so the existing `App\Ai\Tools\SearchMedia` caller is unaffected — and the Telegram agent's tool gets the richer query object for free, which is a nice step toward the eventual convergence.

With this one tool an agent can express: "what did David finish in 2025?" (`status=finished, finished_year=2025`), "what books are in the backlog?" (`media_type=book, status=backlog`), "has David played anything by FromSoftware?" (`creator=FromSoftware`), "what is he reading right now?" (`media_type=book, status=started`).

### Why tools only (no MCP resources/prompts for now)

Notes could also be modeled as MCP resources with a resource template. Skipping that for v1: tool support is universal across MCP clients while resource support is uneven, and one content model keeps the surface small. Resources can be added to the same server later without breaking anything.

## Testing

Feature tests in `tests/Feature/Mcp/`, using the first-party server testing API (`PublicServer::tool(QueryMedia::class, [...])` returning a `TestResponse` with `assertOk` / `assertSee` / structured-content assertions):

- **ListNotes / SearchNotes / GetNote**: returns visible notes; **never** returns `visible = false` notes (list, search, and direct-slug fetch all tested); `GetNote` gives identical not-found errors for missing vs. invisible slugs; pagination bounds enforced.
- **QueryMedia**: each filter individually and in combination (factories already exist for Media, MediaEvent, Creator); status derivation matches the view (backlog = no events); year filters distinguish release year from finished/started year; response **never contains** `note` or `comment` values even when set on the underlying rows; sort orders; pagination bounds.
- **Transport/registration**: an HTTP feature test POSTing a `tools/list` (and one `tools/call`) JSON-RPC request to `/mcp` as a guest, asserting 200 and the four tools — proving the endpoint is genuinely public, unauthenticated, and CSRF-exempt in this app's middleware setup.
- **SearchMediaQuery**: extend the existing query tests in `tests/Feature/Queries` for the new parameters.

Manual verification with the first-party inspector:

```bash
php artisan mcp:inspector mcp
```

## Deployment

Nothing new in `render.yaml` — the MCP endpoint is just another route on the existing web service (Octane/FrankenPHP). Tools are stateless request-scoped classes, so no Octane state concerns. PR preview environments automatically expose the endpoint at `https://davidhartingdotcom-web-pr-<N>.onrender.com/mcp` with seeded data, which is a convenient way to point Claude Code / Claude.ai at the server before merging.

## Implementation steps

Each step is an atomic commit with its tests:

1. `composer require laravel/mcp` + publish `routes/ai.php` + `PublicServer` skeleton registered at `Mcp::web('/mcp')` with throttle. Test: guest `tools/list` HTTP round-trip.
2. `ListNotes` + `GetNote` tools with visibility tests.
3. `SearchNotes` tool with tests.
4. Extend `SearchMediaQuery` (status / years / sort / pagination) with query tests.
5. `QueryMedia` tool with tests (including the no-private-fields assertions).
6. Server `$instructions` polish + docs: short section in `CLAUDE.md`/README pointing at `/mcp`, update this doc's status.
