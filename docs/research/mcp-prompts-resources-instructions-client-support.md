---
name: mcp-prompts-resources-instructions-client-support
description: Which MCP primitives (prompts, resources, server instructions) Claude clients actually surface — claude.ai/Desktop custom connectors vs Claude Code
status: research
researched: 2026-09-22
---

# Do Claude clients actually surface MCP prompts, resources, and server instructions?

Research note for the `/mcp` + `/mcp/admin` work in [`docs/projects/mcp-server.md`](../projects/mcp-server.md).
That doc's ["Why tools only (no MCP resources/prompts for now)"](../projects/mcp-server.md) section says
"tool support is universal across MCP clients while resource support is uneven." This note checks that
claim against primary sources and puts dates on it.

Every claim below is tagged:

- **[DOCUMENTED]** — stated in the MCP specification, Anthropic's own docs, or package source.
- **[REPORTED]** — from Anthropic's public issue tracker (`anthropics/claude-ai-mcp`, `anthropics/claude-code`).
  These are first-party _trackers_ but the reports are user-filed and, where noted, have no maintainer reply.
- **[UNDETERMINED]** — I could not settle it from a primary source.

## Bottom line

| Capability                                                | claude.ai web / Desktop (remote custom connector)                                                                                                                                                                                                                                                            | Claude Code (`claude mcp add`)                                                                                                                          |
| --------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Tools                                                     | Yes **[DOCUMENTED]**                                                                                                                                                                                                                                                                                         | Yes **[DOCUMENTED]**                                                                                                                                    |
| Prompts (`prompts/list` + `prompts/get`)                  | Listed as "Supported" in Anthropic's connector-building docs, and a step-by-step attach flow exists on modelcontextprotocol.io — **but no current Anthropic help-center page describes the affordance, and an open bug says custom-connector prompts vanished from the UI in May 2026.** Do not build on it. | Yes — `/servername:promptname` slash commands **[DOCUMENTED]**                                                                                          |
| Resources (`resources/list`, `resources/read`, templates) | Same status as prompts: documented as supported, attach flow documented only on modelcontextprotocol.io, no help-center coverage. Model cannot pull one on its own.                                                                                                                                          | Yes — `@server:uri` mentions **and** built-in `ListMcpResourcesTool` / `ReadMcpResourceTool`, so the model _can_ fetch them unprompted **[DOCUMENTED]** |
| `instructions` from `initialize`                          | **No.** Open Anthropic issue since 2026-03-11 says claude.ai silently drops it. No Anthropic doc claims it is used. **[REPORTED]**                                                                                                                                                                           | Yes — loaded into every session, truncated at 2 KB **[DOCUMENTED]**                                                                                     |

**The single most decision-relevant fact:** for a remote MCP server used as a claude.ai custom connector,
**tools are the only primitive you can rely on.** Prompts and resources are nominally supported but have no
user-facing documentation and an unresolved regression report; server `instructions` are reported as
silently discarded. Anything procedural you need the model to know on claude.ai has to travel in tool
descriptions or tool results.

---

## 1. MCP Prompts

### What the spec says

**[DOCUMENTED]** The spec's _Prompts_ page (spec revision 2025-06-18; the current revision is **2026-07-28**)
opens with a "User Interaction Model" section:

> Prompts are designed to be **user-controlled**, meaning they are exposed from servers to clients with the
> intention of the user being able to explicitly select them for use.
>
> Typically, prompts would be triggered through user-initiated commands in the user interface, which allows
> users to naturally discover and invoke available prompts.
>
> For example, as slash commands […]
>
> However, implementors are free to expose prompts through any interface pattern that suits their needs—the
> protocol itself does not mandate any specific user interaction model.

— <https://modelcontextprotocol.io/specification/2025-06-18/server/prompts>

So the spec _never_ promises a prompt will be reachable; it only says clients that expose prompts should let
the user pick one. Nothing auto-invokes a prompt.

**[DOCUMENTED]** The concept doc repeats this and lists slash commands, command palettes and buttons as
typical patterns: <https://modelcontextprotocol.io/docs/2026-07-28/learn/server-concepts> ("Prompts are
user-controlled, requiring explicit invocation rather than automatic triggering").

### Claude Code

**[DOCUMENTED]** Fully supported and documented, under the heading **"Use MCP prompts as commands"**:

> Type `/` to see the commands available to you, including those from MCP servers. Claude Code lists each MCP
> prompt as `/servername:promptname (MCP)`. Typing `/mcp__servername__promptname` also runs it.

Arguments are passed space-separated (`/mcp__github__pr_review 456`), split on whitespace, one token each.
Tips in the same section: "MCP prompts are dynamically discovered from connected servers"; "Prompt results
are injected directly into the conversation."

— <https://code.claude.com/docs/en/mcp> (section `Use MCP prompts as commands`)

Dated corroboration from the Claude Code changelog (<https://code.claude.com/docs/en/changelog>):

- 2.1.145, 2026-05-19 — "Fixed MCP prompt slash commands showing raw server validation errors when a required
  argument is omitted."
- 2.1.214, 2026-07-18 — "Fixed MCP transient errors during prompts/resources refresh clearing the server's
  slash commands and resources."
- 2.1.274, 2026-09-17 — "Fixed MCP prompts and resources not refreshing when a server sends list-changed
  notifications without declaring `listChanged`."

i.e. the feature is actively maintained as of two months ago.

### claude.ai web / Claude Desktop custom connectors

This is where it gets murky. Three primary sources disagree in emphasis.

**[DOCUMENTED] — "supported".** Anthropic's connector-building doc has a _Protocol features_ table:

> **Supported**
>
> - [Tools](…/server/tools), [prompts](…/server/prompts), and [resources](…/server/resources)
> - Text and image-based tool results
> - Text and binary resources
>
> **Not yet supported**
>
> - Resource subscriptions
> - Sampling
> - Advanced/draft capabilities

— <https://claude.com/docs/connectors/building> (no visible last-updated date; it references the 2025-11-25
auth spec, so it is at least that recent)

**[DOCUMENTED] — the only description of the affordance.** modelcontextprotocol.io's _Connect to remote MCP
Servers_ walkthrough (which uses Claude as its worked example) has a step titled **"Access Resources and
Prompts"**:

> After successful connection, the remote server's resources and prompts become available in your Claude
> conversations. You can access these by clicking the "Add files, connectors, and more /" indicator in the
> bottom-left corner of the message input area. Then hover over "Connectors", move the cursor over "Add to
> Example Remote Server", where hovering displays the attachment menu.
>
> The menu displays all available resources and prompts from your connected server. Select the items you want
> to include in your conversation.

— <https://modelcontextprotocol.io/docs/2026-07-28/develop/connect-remote-servers>

Caveats on that source: it is the MCP project's docs, not Anthropic's help centre; the screenshots show an
older claude.ai composer; and the page still links to `support.anthropic.com` article IDs that now redirect.
It is republished unchanged under every spec-revision path (2024-11-05 … 2026-07-28), so the `2026-07-28` in
the URL is **not** evidence that the page was reviewed in 2026.

**[DOCUMENTED] — Anthropic's own user docs never mention prompts or resources.** I grepped the full text of
every relevant Anthropic page for "prompt" and "resource" used as MCP primitives. None of these mention them:

- "Get started with custom connectors using remote MCP", **dated August 11, 2026** —
  <https://support.claude.com/en/articles/11175166-get-started-with-custom-connectors-using-remote-mcp>.
  Its capability section is headed "Taking actions with tools": "Remote MCP servers give Claude tools it can
  invoke during your conversation." No prompts, no resources, no attachment menu.
- "Use connectors to extend Claude's capabilities" — <https://support.claude.com/en/articles/11176164-…>
- "Manage Claude's tool access" — <https://support.claude.com/en/articles/13730515-…>. This is the page that
  documents today's `+` → **Connectors** menu, and what lives under it is **Tool access** (Auto / Always
  available / On demand), not an attachment picker.
- "When to use desktop and web connectors" — <https://support.claude.com/en/articles/11725091-…>
- "Getting Started with Local MCP Servers on Claude Desktop", **dated June 30, 2026** —
  <https://support.claude.com/en/articles/10949351-…>. Tools only, even for local servers.
- "Third party connectors with remote MCP" — <https://claude.com/docs/connectors/custom/remote-mcp>. Tools only.
- "Model Context Protocol (MCP)" — <https://claude.com/docs/connectors/building/mcp>. Names all three
  primitives conceptually, but every behavioural statement ("Tool hints", permissions) is about tools.

**[REPORTED] — a regression that was never closed.** `anthropics/claude-ai-mcp` issue **#333, "Prompts
disappeared from chat client"**, opened **2026-05-21**, label `bug`, **still open**, no maintainer reply:
custom MCP connector prompts were visible and usable on 2026-05-13 and gone by 2026-05-21 from the chat GUI,
from slash commands, and from Claude Desktop — while the server still answered `prompts/list` correctly. The
reporter notes Atlassian's (directory/verified) prompts started appearing at the same time.
— <https://github.com/anthropics/claude-ai-mcp/issues/333>

**[REPORTED]** Related: issue **#23, "Allow model to programmatically access prompts and resources"**, opened
2026-01-23, label `enhancement`, open, no maintainer reply. It asserts Claude exposes only `tools/list` /
`tools/call` and that a server's 22 resources and its prompts were invisible. Note this issue is about the
_model_ reaching them, which is a different question from the user attaching them.
— <https://github.com/anthropics/claude-ai-mcp/issues/23>

### Verdict on prompts

**[UNDETERMINED, leaning negative.]** I could not verify from any Anthropic source dated later than
May 2026 that a _custom_ remote connector's prompts are reachable in the claude.ai or Desktop UI. The one
document that describes the affordance is not Anthropic's and is visibly stale; Anthropic's own current
help-centre page for custom connectors describes tools and nothing else; and the open bug report says the
affordance was removed for custom connectors in May 2026 while remaining for a directory connector.

**Practical guidance: do not design the `/mcp/admin` server around prompts.** If a prompt is the right
shape for something, ship it for Claude Code (where it is documented and maintained) and do not assume a
claude.ai user can reach it.

### Is Desktop different from web?

**[DOCUMENTED]** For _remote_ connectors, no. The custom-connectors article (2026-08-11) is explicit:

> Even though Cowork and Claude Desktop run on your computer, remote connectors are configured and brokered
> through your Claude account. The connection to your MCP server originates from Anthropic's servers, not
> from your machine's network interface.

So Desktop and web share one connector backend, and issue #333 reports the disappearance on both. Desktop's
_separate_ mechanism — local servers via `claude_desktop_config.json` / `.mcpb` extensions — is documented
tools-only too (support article 10949351, 2026-06-30).

---

## 2. MCP Resources

### What the spec says

**[DOCUMENTED]** The _Resources_ page (current revision 2026-07-28) has the mirror-image interaction model:

> Resources in MCP are designed to be **application-driven**, with host applications determining how to
> incorporate context based on their needs.
>
> For example, applications could:
>
> - Expose resources through UI elements for explicit selection, in a tree or list view
> - Allow the user to search through and filter available resources
> - Implement automatic context inclusion, based on heuristics or the AI model's selection

— <https://modelcontextprotocol.io/specification/2026-07-28/server/resources>

So "does the model auto-read them or must the user attach them?" is **entirely a client decision**; the spec
permits both. Templates are `resources/templates/list` returning RFC 6570 `uriTemplate` entries. Resource
`annotations` (`audience: ["user"|"assistant"]`, `priority` 0.0–1.0, `lastModified`) exist precisely so a
client can decide what to auto-include — but honouring them is optional.

### Claude Code

**[DOCUMENTED]** Both paths exist:

1. User attaches — section **"Use MCP resources"**: "Type `@` in your prompt to see available resources from
   all connected MCP servers. Resources appear alongside files in the autocomplete menu." Format is
   `@server:protocol://resource/path`, e.g. `Can you analyze @github:issue://123 and suggest a fix?`. Tips:
   "Resources are automatically fetched and included as attachments when referenced"; "Resource paths are
   fuzzy-searchable in the @ mention autocomplete."
2. Model fetches on its own — same section: "Claude Code automatically provides tools to list and read MCP
   resources when servers support them." The tools reference names them: **`ListMcpResourcesTool`** ("Lists
   resources exposed by connected MCP servers") and **`ReadMcpResourceTool`** ("Reads a specific MCP resource
   by URI"), both listed as not requiring permission.

— <https://code.claude.com/docs/en/mcp>, <https://code.claude.com/docs/en/tools-reference>

Changelog: "MCP resources can now be @-mentioned" — v1.0.27, **2025-06-18**.

**[UNDETERMINED]** Whether Claude Code surfaces `resources/templates/list` entries in the `@` autocomplete
(as opposed to only concrete `resources/list` URIs). The docs don't say, and `ListMcpResourcesTool`'s
description mentions only "resources".

### claude.ai web / Desktop

**[DOCUMENTED]** Same two sources as prompts, same conflict: listed under "Supported" in
<https://claude.com/docs/connectors/building> (including "Text and binary resources", with "Resource
subscriptions" explicitly _not_ supported), and described as attachable via the composer's `+` menu only on
<https://modelcontextprotocol.io/docs/2026-07-28/develop/connect-remote-servers>. No Anthropic help-centre
page mentions them. **[REPORTED]** Issue #23 says the model cannot reach them at all.

**[DOCUMENTED] — for the API, it's a flat no.** Distinct surface, but worth knowing because it's the one
place Anthropic states the limit in writing: the Messages API MCP connector says

> Of the feature set of the MCP specification, only tool calls are currently supported.

— <https://platform.claude.com/docs/en/agents-and-tools/mcp-connector>

### Verdict on resources

Same as prompts: rely on them in Claude Code, not on claude.ai. If published notes need to be readable by a
claude.ai connector, they must come back from a **tool**, which is what `docs/projects/mcp-server.md` already
chose.

---

## 3. The server-level `instructions` field

### What the spec says

**[DOCUMENTED]** In the handshake revisions (2024-11-05 … 2025-11-25) it rides on the `initialize` result:

```json
{
    "result": {
        "protocolVersion": "2025-06-18",
        "capabilities": { "…": {} },
        "serverInfo": { "name": "ExampleServer", "version": "1.0.0" },
        "instructions": "Optional instructions for the client"
    }
}
```

— <https://modelcontextprotocol.io/specification/2025-06-18/basic/lifecycle>

The authoritative description is the schema doc comment (`schema/2025-06-18/schema.ts`, `InitializeResult`):

> Instructions describing how to use the server and its features.
>
> This can be used by clients to improve the LLM's understanding of available tools, resources, etc. It can be
> thought of like a "hint" to the model. For example, this information MAY be added to the system prompt.

— <https://raw.githubusercontent.com/modelcontextprotocol/modelcontextprotocol/main/schema/2025-06-18/schema.ts>

Note `MAY`. **Nothing in the spec obliges a client to use `instructions` at all**, and there is **no length
limit anywhere in the spec**.

**[DOCUMENTED]** In the current revision (**2026-07-28**) the handshake is gone; `initialize` is replaced by a
mandatory `server/discover` RPC, and `instructions` moves to `DiscoverResult` with a sharper doc comment:

> `instructions`: Optional natural-language guidance for LLMs on how to use this server effectively
>
> Natural-language guidance describing the server and its features. This can be used by clients to improve an
> LLM's understanding of available tools (e.g., by including it in a system prompt). It should focus on
> information that helps the model use the server effectively **and should not duplicate information already in
> tool descriptions.**

— <https://modelcontextprotocol.io/specification/2026-07-28/server/discover> and
<https://modelcontextprotocol.io/specification/2026-07-28/schema> (`DiscoverResult.instructions`)

That last clause is the closest thing to a spec-level answer to question 4: **server instructions are for
cross-tool guidance; per-tool facts belong in tool descriptions.**

### Claude Code

**[DOCUMENTED]** Injected into every session, and capped:

> Tool search keeps MCP context usage low by deferring tool definitions until Claude needs them. **Only tool
> names and server instructions load at session start**, so adding more MCP servers has minimal impact on your
> context window.

> ### For MCP server authors
>
> If you're building an MCP server, the server instructions field becomes more useful with tool search
> enabled. Server instructions help Claude understand when to search for your tools, similar to how
> [skills](/docs/en/skills) work.
>
> Add clear, descriptive server instructions that explain:
>
> - What category of tasks your tools handle
> - When Claude should search for your tools
> - Key capabilities your server provides
>
> **Claude Code truncates tool descriptions and server instructions at 2KB each.** Keep them concise to avoid
> truncation, and put critical details near the start.

— <https://code.claude.com/docs/en/mcp> (section `Scale with MCP tool search`)

The quickstart confirms it is unconditional, not a tool-search-only behaviour: "Each connected server takes
some space in Claude's context window because **its tool names and server instructions load into every
session**." — <https://code.claude.com/docs/en/mcp-quickstart>

Dated history (<https://code.claude.com/docs/en/changelog>):

- v1.0.52, **2025-07-18** — "Added support for MCP server instructions."
- v2.1.84, **2026-03-26** — "MCP tool descriptions and server instructions are now capped at 2KB to prevent
  OpenAPI-generated servers from bloating context."
- v2.1.70, 2026-03-06 — "Fixed prompt-cache bust when an MCP server with `instructions` connects after the
  first turn."

So: **2 KB per server, truncated silently, put the important part first.** 2 KB ≈ 2048 bytes ≈ 300–350 words.

**[REPORTED]** `anthropics/claude-code` issue #43474 reports that with several servers configured the
combined instructions block is truncated again, mid-sentence, without warning. I did not verify this
independently.

### claude.ai web / Desktop

**[REPORTED] — not used.** `anthropics/claude-ai-mcp` issue **#93, "Server instructions from initialize
response not passed to model"**, opened **2026-03-11**, labels `backlog` / `enhancement` / `triaged`, **still
open**, no public maintainer reply. It reports claude.ai silently ignores `instructions`; the model gets tool
names, descriptions and schemas only; there is no error or indication the field was discarded; and the same
server's instructions _are_ honoured in Claude Code. Issue **#131** was filed later and closed as a duplicate
of #93.
— <https://github.com/anthropics/claude-ai-mcp/issues/93>, <https://github.com/anthropics/claude-ai-mcp/issues/131>

The `triaged` + `backlog` labels are meaningful: Anthropic has looked at it and filed it as an enhancement
rather than a bug, which is consistent with "claude.ai does not implement this," not "claude.ai has a
regression."

**[DOCUMENTED, negative]** No Anthropic page states that claude.ai reads `instructions`. The
connector-building doc's _Protocol features_ table lists tools/prompts/resources and says nothing about
`instructions` either way.

**[UNDETERMINED]** Whether claude.ai imposes any length limit on tool descriptions. Issue #93 claims ~500
characters; I found no Anthropic documentation of any such limit. The only documented claude.ai/Desktop size
limit is **~150,000 characters max tool result size** (and a 240-second tool-call timeout) —
<https://claude.com/docs/connectors/building>.

---

## 4. Where Anthropic says to put procedural / workflow guidance

There is no single page that answers this. Assembling what exists:

**[DOCUMENTED] Spec — server instructions are for cross-cutting guidance, not tool restatement.**
`DiscoverResult.instructions`: "It should focus on information that helps the model use the server
effectively and should not duplicate information already in tool descriptions."
— <https://modelcontextprotocol.io/specification/2026-07-28/schema>

**[DOCUMENTED] Claude Code — server instructions are the "when to reach for this server" layer.**
"Server instructions help Claude understand **when to search for your tools**, similar to how skills work.
Add clear, descriptive server instructions that explain: what category of tasks your tools handle; when
Claude should search for your tools; key capabilities your server provides." Cap 2 KB, critical details
first. — <https://code.claude.com/docs/en/mcp>

**[DOCUMENTED] Anthropic directory review — tool descriptions are for _what_, not _how to behave_.** The
pre-submission checklist for the Connectors Directory is unusually direct, and is a hard constraint if the
server is ever submitted:

> ### Write narrow, accurate descriptions
>
> Each tool description should state precisely what the tool does and when to invoke it. The description must
> match the tool's actual behavior.
>
> ## Avoid prompt-injection patterns
>
> Tool descriptions are rejected if they:
>
> - Instruct Claude to call external software or tools the user didn't request
> - Interfere with Claude calling other tools
> - Direct Claude to pull behavioral instructions from external sources
> - Contain hidden, obfuscated, or encoded instructions
> - Tell Claude to behave in ways unrelated to the tool's function, attempt to override system instructions,
>   or promote products and services
>
> **Describe what the tool does. Do not tell Claude how to behave.**

— <https://claude.com/docs/connectors/building/review-criteria>

**[DOCUMENTED] Spec — prompts are the user-invoked workflow primitive.** "Prompts provide reusable templates.
They allow MCP server authors to provide parameterized prompts for a domain, or **showcase how to best use the
MCP server**." — <https://modelcontextprotocol.io/docs/2026-07-28/learn/server-concepts>

**[DOCUMENTED] Anthropic engineering, "Writing effective tools for AI agents" (2025-09-11)** —
<https://www.anthropic.com/engineering/writing-tools-for-agents>. Does not address the three-way split, but
pushes domain context _into_ tool descriptions: "think of how you would describe your tool to a new hire on
your team… Consider the context that you might implicitly bring — specialized query formats, definitions of
niche terminology, relationships between underlying resources — and make it explicit." Read together with the
review criteria: **domain vocabulary and query semantics in the tool description; behavioural directives
nowhere.**

### Synthesis for this project

| Kind of guidance                                                                                                      | Put it in                                                                                                            | Why                                                                                                              |
| --------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| "This server is David Harting's personal site: published notes + a media-tracking library; everything here is public" | Server `instructions` **and** restated compactly in each tool description                                            | Claude Code reads instructions; claude.ai (reportedly) does not, so it has to survive in tool descriptions too   |
| Media-type / tracking-status vocabularies, date semantics, what a field means                                         | Tool description of the tool that takes them, plus the JSON-Schema field descriptions                                | Survives everywhere; matches "make niche terminology explicit"; schema descriptions are never the truncated part |
| "Call `search_notes` before `read_note`" style sequencing                                                             | Tool descriptions, phrased as _what the tool is for_ ("finds the slug you need for `read_note`"), not as a directive | Directive phrasing is a directory-review rejection reason                                                        |
| A multi-step workflow a human would kick off                                                                          | An MCP **prompt** — Claude Code only                                                                                 | Documented and maintained there; unreliable on claude.ai                                                         |
| Anything that must reach the model on claude.ai                                                                       | Tool descriptions, or the tool's own response body                                                                   | The only two channels reported to work                                                                           |

Keep server `instructions` under 2 KB with the "what is this server / when to use it" sentence first, and
treat it as a Claude Code optimisation, not as load-bearing.

---

## 5. What `laravel/mcp` v0.8.2 actually emits

Read from the pinned commit in `composer.lock` (`laravel/mcp` `v0.8.2`, `0c32bf3`, 2026-06-25). The package is
not currently vendored in this checkout, so I read it from the upstream repo at that exact commit.

**[DOCUMENTED — source]** `src/Enums/ProtocolVersion.php`: supports `2025-11-25` (declared `LATEST`),
`2025-06-18`, `2025-03-26`, `2024-11-05`. **No `2026-07-28` and no `server/discover`** — it is a
handshake-era server, which is fine: the current spec keeps a backward-compatibility path for
initialize-based versions.

**[DOCUMENTED — source]** `src/Server/Methods/Initialize.php` always returns `instructions`:

```php
return JsonRpcResponse::result($request->id, [
    'protocolVersion' => $protocolVersion,
    'capabilities'    => $context->serverCapabilities,
    'serverInfo'      => $context->implementation->toArray(),
    'instructions'    => $context->instructions,
]);
```

It echoes the client's requested `protocolVersion` if supported, else throws `-32602` with a `supported` list.

**[DOCUMENTED — source]** `src/Server.php` defaults, and a trap worth knowing:

```php
protected string $instructions = <<<'MARKDOWN'
    This MCP server lets AI agents interact with our Laravel application.
MARKDOWN;

protected array $capabilities = [
    self::CAPABILITY_TOOLS     => ['listChanged' => false],
    self::CAPABILITY_RESOURCES => ['listChanged' => false],
    self::CAPABILITY_PROMPTS   => ['listChanged' => false],
];
```

**The `prompts` and `resources` capabilities are advertised unconditionally**, even when `$prompts` and
`$resources` are empty. So our tools-only `PublicServer` still tells every client "I do prompts and
resources," and every client will call `prompts/list` / `resources/list` and get an empty array. Harmless, but
it means capability advertisement is not a signal of anything, and `listChanged: false` means we never emit
`notifications/*/list_changed`.

Registered methods (`src/Server.php`): `tools/list`, `tools/call`, `resources/list`, `resources/read`,
`resources/templates/list`, `prompts/list`, `prompts/get`, `completion/complete`, `ping`.

`instructions` can be set either as the `$instructions` property or via the class attribute
`#[Instructions('…')]`, which wins when both are present (`Server::createContext()`).

**[DOCUMENTED — Laravel docs]** <https://laravel.com/docs/master/mcp> documents prompts
(`make:mcp-prompt`, `$prompts`, arguments, validation, DI, conditional registration) and resources
(`make:mcp-resource`, `$resources`, URI templates, MIME types, annotations) as first-class. Nothing in the
Laravel docs says anything about which clients surface them — that is the gap this note fills.

---

## 6. What I could not determine

- Whether a custom (non-directory) connector's prompts and resources are reachable in the claude.ai composer
  **today**. The only walkthrough is stale and unowned by Anthropic; the only current data point is an open,
  unanswered bug saying they vanished in May 2026. Settling this needs someone to connect a server with a
  prompt and look at the `+` menu.
- Whether _directory/verified_ connectors get prompt surfacing that custom connectors do not. Issue #333 hints
  at this (Atlassian's prompts appeared as the reporter's own disappeared) but nothing documents it.
- Whether claude.ai truncates tool descriptions, and at what length. The ~500-character figure comes only from
  issue #93.
- Whether Claude Code's `@` autocomplete includes `resources/templates/list` entries or only concrete URIs.
- Exact last-reviewed dates for `claude.com/docs/connectors/*` and
  `modelcontextprotocol.io/docs/*/develop/connect-remote-servers`; neither publishes one, and the GitHub API
  was not reachable from this session to check commit history.

---

## Sources

Spec (modelcontextprotocol.io) — current revision **2026-07-28**, per <https://modelcontextprotocol.io/specification/versioning>:

- Prompts (2025-06-18): <https://modelcontextprotocol.io/specification/2025-06-18/server/prompts>
- Resources (2026-07-28): <https://modelcontextprotocol.io/specification/2026-07-28/server/resources>
- Lifecycle / `initialize` (2025-06-18): <https://modelcontextprotocol.io/specification/2025-06-18/basic/lifecycle>
- Discovery / `server/discover` (2026-07-28): <https://modelcontextprotocol.io/specification/2026-07-28/server/discover>
- Schema (2026-07-28), `DiscoverResult.instructions`: <https://modelcontextprotocol.io/specification/2026-07-28/schema>
- `schema/2025-06-18/schema.ts`, `InitializeResult.instructions`: <https://github.com/modelcontextprotocol/modelcontextprotocol/blob/main/schema/2025-06-18/schema.ts>
- Server concepts: <https://modelcontextprotocol.io/docs/2026-07-28/learn/server-concepts>
- Connect to remote MCP servers: <https://modelcontextprotocol.io/docs/2026-07-28/develop/connect-remote-servers>

Anthropic:

- Building custom connectors: <https://claude.com/docs/connectors/building>
- Pre-submission checklist / review criteria: <https://claude.com/docs/connectors/building/review-criteria>
- Third party connectors with remote MCP: <https://claude.com/docs/connectors/custom/remote-mcp>
- MCP overview: <https://claude.com/docs/connectors/building/mcp>
- Messages API MCP connector: <https://platform.claude.com/docs/en/agents-and-tools/mcp-connector>
- Claude Code MCP: <https://code.claude.com/docs/en/mcp>
- Claude Code MCP quickstart: <https://code.claude.com/docs/en/mcp-quickstart>
- Claude Code tools reference: <https://code.claude.com/docs/en/tools-reference>
- Claude Code changelog: <https://code.claude.com/docs/en/changelog>
- Get started with custom connectors using remote MCP (2026-08-11): <https://support.claude.com/en/articles/11175166-get-started-with-custom-connectors-using-remote-mcp>
- Manage Claude's tool access: <https://support.claude.com/en/articles/13730515-manage-claude-s-tool-access>
- When to use desktop and web connectors: <https://support.claude.com/en/articles/11725091-when-to-use-desktop-and-web-connectors>
- Getting Started with Local MCP Servers on Claude Desktop (2026-06-30): <https://support.claude.com/en/articles/10949351-getting-started-with-local-mcp-servers-on-claude-desktop>
- Writing effective tools for AI agents (2025-09-11): <https://www.anthropic.com/engineering/writing-tools-for-agents>

Anthropic issue trackers (user-filed; treat as reports, not statements):

- claude-ai-mcp #93 — server instructions not passed to model (open, 2026-03-11): <https://github.com/anthropics/claude-ai-mcp/issues/93>
- claude-ai-mcp #131 — duplicate of #93 (closed): <https://github.com/anthropics/claude-ai-mcp/issues/131>
- claude-ai-mcp #333 — prompts disappeared from chat client (open, 2026-05-21): <https://github.com/anthropics/claude-ai-mcp/issues/333>
- claude-ai-mcp #23 — allow model to access prompts and resources (open, 2026-01-23): <https://github.com/anthropics/claude-ai-mcp/issues/23>

Package source:

- `laravel/mcp` v0.8.2 @ `0c32bf369c6432cab21458f9f4479da33a49ba37`: <https://github.com/laravel/mcp>
- Laravel MCP docs: <https://laravel.com/docs/master/mcp>
