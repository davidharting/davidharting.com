---
name: editor-interactivity
description: Autosave and photo uploads for the monthly-memory editor — Filament forms vs plain Livewire 4 vs htmx + Alpine
status: research
researched: 2026-10-03
---

# Autosave and photo uploads: Filament vs Livewire vs htmx + Alpine

Research note for [#247](https://github.com/davidharting/davidharting.com/issues/247), part of the
_Monthly memories_ map [#245](https://github.com/davidharting/davidharting.com/issues/245).

The editor has to autosave a long plain-text **Draft** often, upload/caption/order up to 10 photos, keep
**Share** as a separate explicit step, work well on a phone, and later take a rich-text editor.

Versions checked: `livewire/livewire` **v4.3.3** and `filament/forms` **v5.6.8** (both from `composer.lock`),
htmx **2.0.11** (`latest` on npm) and **4.0.0** (`next` on npm, released 2026-08-28), Alpine `main`.

Every claim is tagged:

- **[DOCUMENTED]** — stated in official docs (Livewire, Filament, htmx, Alpine, Cloudflare R2, MDN).
- **[SOURCE]** — read in the package's source at the version above. Paths are relative to that repo.
- **[REPO]** — a fact about this repo's own config.
- **[UNDETERMINED]** — no primary source settles it.

## Bottom line

**Use a plain Livewire 4 component**, with a small amount of Alpine (Livewire already bundles it). Don't use Filament
forms for this editor, and don't add htmx.

|                            | Filament v5 form in a Livewire component                                                                                                                          | Plain Livewire 4                                                                                                        | htmx + Alpine                                                                                         |
| -------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| Autosave                   | None built in. You'd wire `live(debounce:)` or a Livewire hook to `getState()` yourself, and `getState()` validates and saves the **whole** form each time.       | Built from primitives: `wire:model`, `wire:dirty`, Alpine debounce, actions that run one after another. About 30 lines. | `hx-trigger="input changed delay:2s"` plus `hx-sync`. A plain controller per endpoint.                |
| Multiple photos + progress | Best in class: FilePond, per-file progress, grid, `maxFiles`, EXIF orientation, image editor.                                                                     | `WithFileUploads` + progress events. You build the tile UI.                                                             | htmx 2: `htmx:xhr:progress`. **htmx 4 dropped it** (it uses `fetch()`), so you'd write your own XHR.  |
| Per-photo caption          | **Not supported** by `FileUpload`. You need a `Repeater` with one single-file `FileUpload` per item, which means one tap per photo instead of picking 10 at once. | A plain input per photo row.                                                                                            | A plain input per photo row.                                                                          |
| Ordering                   | `reorderable()`, or a relationship `Repeater` with `orderColumn()` and `reorderableWithButtons()`.                                                                | `wire:sort` (SortableJS), or up/down buttons calling an action.                                                         | Alpine Sort plugin (another dependency) or buttons.                                                   |
| Fits the stack             | Filament is installed but used only for the admin panel. You'd have to load Filament CSS/JS on the DaisyUI front end.                                             | Livewire is installed. Its JS (with Alpine) is injected only on pages that use it.                                      | **Needs two new npm dependencies** (`htmx.org`, `alpinejs`). Alpine isn't installed on its own today. |
| Rich text later            | `RichEditor` (TipTap, HTML or JSON).                                                                                                                              | Embed Filament's `RichEditor` as a one-field schema, or TipTap via Alpine + `wire:ignore`.                              | TipTap + hidden input + custom trigger.                                                               |
| Custom code                | Medium, and it fights the abstraction (autosave, captions, styling).                                                                                              | Medium, and every line is ours and plain.                                                                               | Highest: controllers, partials, progress, ordering, error handling.                                   |

**The most decision-relevant finding is the same for every option.** In production, Livewire's temporary uploads
would go **straight to R2** without anyone choosing that. This is because `config/filesystems.php` sets `default` to the
`private` alias, which resolves to an S3-driver disk. On that path, `multiple` file inputs throw, the browser needs
CORS on the bucket, and the presigned PUT carries an `x-amz-acl` header that R2 doesn't support. Dev uses the local
driver, so none of this shows up until production. Set `livewire.temporary_file_upload.disk` explicitly (see
[§2.4](#24-file-uploads-in-production-the-r2-trap)). Filament's `FileUpload` sits on the same mechanism, so this
applies to it too.

---

## 1. Filament v5 forms outside the panel

### 1.1 It works, but it is still a Livewire component

**[DOCUMENTED]** Filament forms are rendered outside a panel by writing a Livewire component that implements `HasSchemas`,
uses `InteractsWithSchemas`, keeps state in a public array with `statePath('data')`, calls `$this->form->fill()` in
`mount()`, and reads validated data with `$this->form->getState()` — "It's important that you use this method instead of
accessing the `$this->data` property directly".
— Filament docs, _Rendering a form in a Blade view_ (`docs/12-components/02-form.md`)

**[DOCUMENTED]** The page layout also needs `@filamentStyles` in `<head>`, `@filamentScripts` at the end of `<body>`,
and Filament's CSS files `@import`ed into the app's Tailwind (v4.1+) entry point.
— Filament docs, _Installation → individual components_ (`docs/01-introduction/02-installation.md`)

**[REPO]** The public site is styled with DaisyUI 5 (`.claude/rules/blade-templates.md`) and has no Filament assets
(`resources/css/app.css`, `resources/views/components/layout/app.blade.php`). Tailwind is 4.1.17, so the version
requirement is met. Filament's own component styling would sit next to DaisyUI on the one page that uses it.

So Filament here means "Livewire, plus Filament's schema layer, plus Filament's CSS".

### 1.2 No autosave or draft feature

**[SOURCE]** A search of `packages/forms`, `packages/panels` and `docs/` at v5.6.8 finds "autosave" only inside the
vendored EasyMDE markdown editor, which autosaves to the browser's localStorage. Filament has no server-side autosave or
draft feature.

**[DOCUMENTED]** The closest built-in is reactivity. `live()` re-renders the schema on every interaction, and
`live(onBlur: true)` and `live(debounce: 500)` reduce that ("making network requests while the user is still typing results
in suboptimal performance"). — `packages/forms/docs/01-overview.md`

To autosave you'd hang a save off `afterStateUpdated()` or the Livewire `updated()` hook and call `getState()`. That
validates the whole form, and with a relationship `Repeater` it also saves related records. That is a lot of work to run
every couple of seconds while someone types.

### 1.3 `FileUpload`: strong for files, no captions

**[DOCUMENTED]** All from `packages/forms/docs/09-file-upload.md`:

- It is built on FilePond.
- `->multiple()` stores the paths as a JSON array, which needs an `array` cast.
- `->maxParallelUploads()` defaults to FilePond's 2.
- `->reorderable()` should be paired with `->appendFiles()`, because otherwise "FilePond may add newly-uploaded files to
  the beginning of the list".
- There is a grid panel layout, an image editor, EXIF orientation and `maxFiles` validation.
- Default visibility is private.
- "It is the responsibility of the developer to delete these files from the disk if they are removed".
- The field value is a client-controlled path, so `->preventFilePathTampering()` is recommended.

**[SOURCE]** FilePond uploads **each file in its own request** through `$wire.upload("{statePath}.{fileKey}", file, …)`,
and passes Livewire's progress events to FilePond's per-file progress bar
(`packages/forms/resources/views/components/file-upload.blade.php`).

There is **no per-file caption**. With `multiple()`, the only state is the path array. The documented way to attach data
to each file is a `Repeater`:

**[DOCUMENTED]** `Repeater::make('photos')->relationship()->orderColumn('sort')` saves the items as related records and
stores their order. `reorderableWithButtons()` adds up/down buttons. — `packages/forms/docs/12-repeater.md`

Each item would then be a single-file `FileUpload` plus a caption `TextInput`. That gives up FilePond's multi-select: the
user taps "Add photo" and picks one file at a time, and the page holds up to 10 FilePond instances. The photo model also
needs revisions of captions and soft-deleted photos (map #245). Those fit our own `memory_photos` table better than
Filament's path-array state.

### 1.4 Verdict

Filament has the best upload widget, but captions, autosave and front-end styling all work against it. Map #245 also lists
"MCP or Filament access to memories" as out of scope. That's about the admin panel, but it points the same way: keep
memories as a front-end feature.

---

## 2. Plain Livewire 4

### 2.1 Autosave primitives

**[DOCUMENTED]** `wire:model` syncs only when an action runs. `.live` syncs as the user types, with a 150 ms debounce by
default. `.live.debounce.Xms`, `.blur` and `.change` exist, and `.throttle.Xms` too. — `docs/wire-model.md`

**[DOCUMENTED]** `wire:dirty` shows an element while "the client-side state diverges from the server-side state", and
`wire:target` scopes it to one property. — `docs/wire-dirty.md`

**[DOCUMENTED]** `wire:offline` shows an element while the device is offline. — `docs/wire-offline.md`

**[DOCUMENTED]** `wire:poll` is cut back by 95% in a background tab unless `.keep-alive` is added. Polling isn't needed for
autosave. — `docs/wire-poll.md`

**[DOCUMENTED]** Livewire 4 runs "`wire:model.live` requests … in parallel". — `docs/upgrading.md`, _Performance improvements_

**[SOURCE]** `js/request/interactions.js` confirms this. If every queued action and the incoming action are `model.live`,
"let them run in parallel". Any **other** action is `defer()`red until the in-flight message finishes. So ordinary actions
run one at a time per component.

**Implication:** don't save from an `updated()` hook driven by `wire:model.live`. Two of those saves could be in flight at
once and land out of order. Instead:

- Bind the textarea with plain `wire:model`, which is deferred.
- Call a `saveDraft()` action from Alpine after about 2 s idle, on `blur`, and when `visibilitychange` goes to `hidden`.
  Actions run in order, so the last save wins.

**[DOCUMENTED]** `#[Async]` would break that ordering ("Never use async actions if they modify component state").
— `docs/attribute-async.md`

**[SOURCE]** Unsaved text survives a failed request. `js/component.js` sends `getUpdates()` as the diff between
`canonical` (the last known server state) and `ephemeral` (the client state). `canonical` is only replaced in
`mergeNewSnapshot()` after a successful response, so after a network failure the next action re-sends the same change.
`onFailure` and `onError` interceptors (`docs/javascript.md`) can show "Not saved — retrying". The same doc shows how to
replace the 419 "page expired" modal.

**[SOURCE]** The default payload guard is 1 MB per request (`config/livewire.php`, `payload.max_size`). The whole draft is
in the component snapshot, so it travels on every request. That's fine for plain text and worth knowing for rich text.

### 2.2 The same draft open in two tabs or devices

**[SOURCE]** Livewire has no cross-client conflict detection. Each page holds its own snapshot, and whichever save
reaches the server last wins. Its snapshot checksum guards against tampering, not concurrency.

The fix is ours, and it's the same for all three approaches. Keep a `revision` (or `updated_at`) on the memory. Send it
with every save, and refuse a stale one ("Edited on another device — reload"). Only the memory's owner can edit it, so
the conflict is always one person on two devices. Refusing is enough; no merge is needed.

### 2.3 File uploads

**[DOCUMENTED]** All from `docs/uploads.md`:

- Upload flow: get a signed URL, upload to the temporary store, then set the property to a `TemporaryUploadedFile`.
- `multiple` adds to an array.
- `->temporaryUrl()` previews images.
- There are progress events (`livewire-upload-start/progress/finish/error/cancel`), `$cancelUpload()`, and a JS API:
  `$wire.upload(name, file, onSuccess, onError, onProgress, onCancel)` and `$wire.uploadMultiple(...)`.
- Default rule is `file|max:12288` (12 MB).
- The upload endpoint is throttled at `throttle:60,1`.
- Temporary files more than 24 h old are cleaned up automatically on non-S3 disks.

**[SOURCE]** Defaults in `config/livewire.php`: `max_upload_time` is 5 minutes (how long the signed URL lasts), and
`preview_mimes` doesn't include `heic`.

**[SOURCE]** With a local temporary disk, a `multiple` selection is sent as **one** POST containing every file
(`js/features/supportFileUploads.js`, `handleSignedUrl`: `files[]` appended in a loop).

**[REPO]** Caddy caps request bodies at 27 MB (`Caddyfile`, `request_body max_size 27MB`). PHP allows
`upload_max_filesize = 25M` and `post_max_size = 27M` (`Dockerfile`). Ten phone photos in one request could go over that.
**Upload one file per request.** An Alpine loop over `input.files` calling `$wire.upload('upload', file, …)` does this,
and so does FilePond.

**Persist each photo as soon as it arrives.** When an upload finishes, move it to permanent private storage and create the
photo row right away. Don't hold a batch until Save. That way a reload or a killed tab only loses the photo that was
uploading.

### 2.4 File uploads in production: the R2 trap

**[SOURCE]** `FileUploadConfiguration::disk()` returns `livewire.temporary_file_upload.disk`, or else
`filesystems.default`. `isUsingS3()` checks whether that disk's `driver` is `s3`.

**[REPO]** `config/filesystems.php` sets `'default' => 'private'`. `private` resolves to the R2 disk (`driver: s3`) when
`FILESYSTEM_DISK_PRIVATE=r2-private`, which `render.yaml` sets for production and previews. Locally it's the `local`
driver.

So with no override, production takes Livewire's S3 path and development doesn't:

- **[SOURCE]** `WithFileUploads::_startUpload()` throws `S3DoesntSupportMultipleFileUploads` for multiple uploads on S3.
- **[SOURCE]** `GenerateSignedUploadUrl::forS3()` presigns a `putObject` with `'ACL' => 'private'`. The browser PUTs
  straight to the bucket.
- **[DOCUMENTED]** R2's S3 compatibility table lists `x-amz-acl` on `PutObject` as unsupported
  (<https://developers.cloudflare.com/r2/api/s3/api/>). Browser uploads to presigned URLs also need CORS rules on the
  bucket (<https://developers.cloudflare.com/r2/api/s3/presigned-urls/>).
- **[UNDETERMINED]** Whether R2 rejects or ignores the signed ACL header. Not tested.
- **[DOCUMENTED]** Livewire warns that file validation rules can fail on S3 when the temporary object isn't publicly
  readable. — `docs/uploads.md`

**Recommendation:** publish `config/livewire.php` and set `temporary_file_upload.disk` to a disk with the `local` driver,
so dev and prod behave the same. Uploads then go through the web container, within the 27 MB limit. The finished upload
is promoted to `Storage::disk('private')` (R2) in the same request. The queue worker is a separate Render service, so it
must only ever see the R2 copy, never `livewire-tmp/`. Direct-to-R2 can be reconsidered later. It would mean single-file
uploads, a bucket CORS policy, and checking how R2 handles the ACL header.

### 2.5 Ordering

**[DOCUMENTED]** `wire:sort="handler"` plus `wire:sort:item="{{ $id }}"` calls `handler($id, $position)`, and
`wire:sort:handle` limits dragging to a handle. — `docs/wire-sort.md`

**[SOURCE]** The feature maps onto Alpine's `x-sort`. `@alpinejs/sort` is registered in Livewire's bundled Alpine
(`js/lifecycle.js`).

**[DOCUMENTED]** Dragging is provided by SortableJS. — Alpine docs, _Sort plugin_

On a phone, dragging inside a long scrolling page is fiddly, so make **up/down buttons** (a `move($photoId, $direction)`
action) the main control. `wire:sort` with a handle is a cheap extra.

### 2.6 Rich text later

Two paths, and neither needs a different stack:

- **[DOCUMENTED]** Filament's `RichEditor` (TipTap; HTML by default, `->json()` for TipTap JSON) can be dropped into this
  same component as a one-field schema (`packages/forms/docs/10-rich-editor.md`, together with §1.1). This is the
  smallest step, since Filament is already installed.
- Or mount TipTap directly in an Alpine component inside `wire:ignore` and set the Livewire property from Alpine with
  `$wire.body = …`.
  - **[DOCUMENTED]** `wire:ignore` is "most useful in the context of working with third-party javascript libraries for
    custom form inputs" (`docs/wire-ignore.md`).
  - **[DOCUMENTED]** Setting `$wire` properties from Alpine is shown in `docs/alpine.md`.

Either way, the autosave design above (deferred binding plus an ordered `saveDraft()` action) carries over unchanged.

### 2.7 Verdict

Everything needed is first-party and installed. The custom code is ours and easy to read: one component, an Alpine
upload queue, a status line, and a revision check.

---

## 3. htmx + Alpine with ordinary controllers

### 3.1 Dependency cost

**[REPO]** Neither `htmx.org` nor `alpinejs` is in `package.json`. Alpine only reaches a page today inside Livewire's
bundle (`docs/installation.md`: "Livewire bundles Alpine.js with its JavaScript"), so this option adds **two** front-end
dependencies, and both need David's approval.

### 3.2 Which htmx?

**[DOCUMENTED]** htmx 4.0.0 is out (npm `next` tag). 2.0.11 is still `latest`. htmx 4's migration guide
(<https://four.htmx.org/migration-guide-htmx-4/>) says:

- "`fetch()` replaces XMLHttpRequest … This cannot be reverted."
- `htmx:xhr:progress`, `htmx:xhr:loadstart` and `htmx:xhr:abort` are **removed**.
- The `hx-trigger` `queue` modifier is removed.
- There is a new 60-second default request timeout. A large upload over a weak mobile connection could hit it.
- A `hx-alpine-compat` extension exists "to run htmx alongside Alpine.js without conflicts".

Starting on 2.x means a migration soon. Starting on 4.x means writing our own XHR upload code to get progress, which
gives up most of htmx's value for the photo half of the page.

### 3.3 Autosave and uploads

**[DOCUMENTED]** Autosave is `hx-post="/memories/…/draft" hx-trigger="input changed delay:2s"`; `delay` resets on each
event (htmx 2 `hx-trigger`). `hx-sync` (`drop`/`abort`/`replace`/`queue`) controls overlapping requests. Upload progress in
htmx 2 is a listener on `htmx:xhr:progress` (htmx 2 _File Upload_ example).

### 3.4 What we'd build ourselves

We'd write:

- draft, upload, caption, reorder, delete and share controllers and their partials
- error and retry UI
- dirty/offline indicators
- the ordering widget (Alpine Sort is another plugin to install)
- the revision check
- progress code, on htmx 4

Livewire gives us most of that already, through one component and the same Blade.

### 3.5 Verdict

htmx is a reasonable way to build pages, but here it needs new dependencies to reach parity with what's installed, and
its newest major makes upload progress harder.

---

## 4. iOS Safari: backgrounding mid-edit or mid-upload

This applies to every option. None of the three frameworks changes it.

- **[DOCUMENTED]** "Transitioning to `hidden` is the last event that's reliably observable by the page, so developers
  should treat it as the likely end of the user's session." — MDN, `visibilitychange` event. So save on
  `visibilitychange` → `hidden`, not on `beforeunload`/`unload`.
- **[DOCUMENTED]** `fetch(…, { keepalive: true })` survives the page being unloaded, but its body is limited to
  **64 KiB**. — MDN, `RequestInit.keepalive`. That covers a plain-text draft. It doesn't cover a photo, and it isn't how
  Livewire sends requests.
- **[SOURCE]** Livewire sends component requests with `fetch()` without `keepalive` (`js/request/index.js`). It uploads
  files with `XMLHttpRequest` (`js/features/supportFileUploads.js`), and on XHR `error` it calls `_uploadErrored`. There is
  no automatic retry.
- **[UNDETERMINED]** Exactly when iOS Safari suspends network activity in a background tab, or throws the tab away. Apple
  doesn't document it. Design as if any in-flight request can die, and as if the page can come back reloaded from scratch.

What that means for the design:

1. Text: save on idle, on blur and on `hidden`. Livewire's resend-on-next-action behaviour (§2.1) covers a failed save
   while the page is still alive. Optionally, keep a localStorage copy of unsaved text (Alpine's Persist plugin is
   already bundled) to survive the tab being discarded.
2. Photos: one file per request, each saved as soon as it lands (§2.3). On `livewire-upload-error`, show "Tap to retry" on
   that tile. Never lose photos that already finished.
3. Show the save state ("Saved 12:04", "Saving…", "Not saved — offline") with `wire:dirty`/`wire:offline` or an Alpine
   flag, so it's obvious when coming back to the tab.

**[UNDETERMINED]** HEIC: whether iOS Safari converts HEIC photos to JPEG when the input says `accept="image/*"`. Not
settled from a primary source. It matters for the photo-processing ticket, not this choice.

---

## 5. Recommendation

Build the editor as **one full-page Livewire 4 component** on the normal DaisyUI layout:

1. **Draft text:** `<textarea wire:model="body">` (deferred), and an Alpine debounce (about 2 s idle) plus `blur` and
   `visibilitychange:hidden` calling `$wire.saveDraft(revision)`. Don't use `.live` or `#[Async]` for saving. Show the
   status from `wire:dirty`/`wire:offline` and the interceptor hooks.
2. **Two-device safety:** a revision check in `saveDraft()` and in caption saves. A stale save gets a reload prompt.
3. **Photos:** a plain `<input type="file" accept="image/*" multiple>` read by Alpine, which uploads each file with
   `$wire.upload(...)` one at a time and shows a per-tile progress bar. The server promotes each upload to R2 at once and
   creates the photo row. Enforce the 10-photo limit on the server.
4. **Config:** publish `config/livewire.php` and pin `temporary_file_upload.disk` to a local-driver disk. Set the rules
   (`image`, size within the 25 MB PHP limit).
5. **Captions:** an input per photo with the same deferred binding plus debounced save.
6. **Order:** up/down buttons; `wire:sort` with a handle is optional.
7. **Share:** a separate button and action. Saving never shares.
8. **Rich text later:** swap the textarea for Filament's `RichEditor` as a one-field schema, or TipTap in `wire:ignore`.
   The autosave design doesn't change.

No dependency changes are needed.

## Sources

- Livewire v4.3.3 docs (`docs/uploads.md`, `wire-model.md`, `wire-dirty.md`, `wire-offline.md`, `wire-poll.md`,
  `wire-sort.md`, `wire-ignore.md`, `attribute-async.md`, `javascript.md`, `upgrading.md`, `installation.md`, `alpine.md`) and source
  (`src/Features/SupportFileUploads/*`, `js/features/supportFileUploads.js`, `js/request/interactions.js`,
  `js/request/index.js`, `js/component.js`, `js/lifecycle.js`, `config/livewire.php`) —
  <https://github.com/livewire/livewire/tree/v4.3.3>
- Filament v5.6.8 docs (`packages/forms/docs/09-file-upload.md`, `10-rich-editor.md`, `12-repeater.md`,
  `01-overview.md`; `docs/12-components/02-form.md`; `docs/01-introduction/02-installation.md`) and source
  (`packages/forms/resources/views/components/file-upload.blade.php`) — <https://github.com/filamentphp/filament/tree/v5.6.8>
- htmx 2 docs (`hx-trigger`, `hx-sync`, File Upload example) — <https://htmx.org/>; htmx 4 migration guide —
  <https://four.htmx.org/migration-guide-htmx-4/>; npm dist-tags — <https://registry.npmjs.org/htmx.org>
- Alpine Sort and Persist plugin docs — <https://alpinejs.dev/plugins/sort>, <https://alpinejs.dev/plugins/persist>
- Cloudflare R2 S3 API compatibility and presigned URLs — <https://developers.cloudflare.com/r2/api/s3/api/>,
  <https://developers.cloudflare.com/r2/api/s3/presigned-urls/>
- MDN — `visibilitychange` event, `RequestInit.keepalive`
- This repo — `config/filesystems.php`, `render.yaml`, `Dockerfile`, `Caddyfile`, `package.json`, `composer.lock`
