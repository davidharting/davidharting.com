---
name: laravel-media-library-and-public-bucket
description: Wire up MarkdownEditor file attachments to R2, and build a standalone in-browser EXIF-strip tool
status: in-progress
---

# Image Uploads and EXIF Stripping

## Prerequisites / Current State

- **PHP:** 8.3 (platform config in `composer.json`; 8.4 locally)
- **Laravel:** 12.x
- **Filament:** 5.x (with Livewire 4.x)
- **Existing disk config (`config/filesystems.php`):** `local-private`, `local-public`, `r2-private`, and `r2-public` disks are defined. A `public` disk alias resolves to the correct disk via `FILESYSTEM_DISK_PUBLIC` env var (`local-public` in dev, `r2-public` in prod). Public bucket name (`davidhartingdotcom-public`) and URL (`https://cdn.davidharting.com`) are set as plain environment variables in `docker-compose.yml`.

## Background

I want to include images and other media in my Notes and Pages — e.g. photos from a trip embedded in a note. Filament's `MarkdownEditor` already supports dragging images directly into the editor; they get uploaded and the permanent URL is embedded in the markdown automatically.

The main concern: EXIF data (especially GPS location) must be stripped from photos before they reach the server or the public bucket. Since `MarkdownEditor` uploads are server-proxied through Livewire, the raw file — GPS data and all — would pass through PHP and land on R2 unchanged.

## Decision: MarkdownEditor for Uploads + Standalone EXIF-Strip Tool

Rather than building a separate file upload model, admin resource, and presigned URL infrastructure, the simpler approach:

1. **Configure `MarkdownEditor` with `fileAttachmentsDisk`** pointing to the public R2 bucket. Dragged images are uploaded via Livewire → PHP → R2, and the permanent R2 URL is embedded in the markdown automatically.

2. **Build a standalone in-browser EXIF-strip tool** — a separate static site that accepts an image, runs `browser-image-compression` entirely in the browser (strips EXIF, resizes, compresses), and offers the clean file as a download. The user downloads the clean image and then drags it into the MarkdownEditor. _(Built and deployed at <https://davidharting.github.io/image-resizer/>)_

No spatie/laravel-media-library, no FileUpload model, no presigned URL endpoints, no Filament resource. The EXIF tool is pure client-side JS — the server is never involved.

**Workflow in practice:**

1. Open the EXIF-strip tool at <https://davidharting.github.io/image-resizer/>
2. Pick a photo — the browser strips EXIF and resizes it
3. Download the clean image
4. Open a Note (or Page), drag the clean image onto the MarkdownEditor
5. Filament uploads it to R2 and embeds the permanent URL in the markdown

---

## High-Level Plan

1. **Create a public R2 bucket** with a custom domain _(already done)_
2. **Add `r2-public` filesystem disk** to Laravel _(already done)_
3. **Configure `MarkdownEditor` file attachments** — set `fileAttachmentsDisk`, `fileAttachmentsDirectory`, and `fileAttachmentsVisibility` on all relevant editors
4. **Build the EXIF-strip tool** — Filament custom page with Alpine.js + `browser-image-compression`
5. **Production deployment** — verify env vars, deploy, test end-to-end

---

## Detailed Implementation Plan

### Phase 1: Cloudflare R2 Public Bucket Setup _(complete)_

- Public R2 bucket created with `cdn.davidharting.com` as the custom domain
- `r2-public` filesystem disk is already configured in `config/filesystems.php`
- `FILESYSTEM_DISK_PUBLIC` env var selects `local-public` in dev and `r2-public` in prod via the `public` disk alias

### Phase 2: Configure MarkdownEditor File Attachments

On all `MarkdownEditor` instances where file attachments should be allowed (currently `NoteResource` and any `PageResource` with a content editor), add:

```php
MarkdownEditor::make('content')
    ->fileAttachmentsDisk(config('filesystems.default_public')) // or env('FILESYSTEM_DISK_PUBLIC', 'local-public')
    ->fileAttachmentsDirectory(fn ($record) => $record
        ? 'notes/' . $record->slug
        : 'notes/draft'
    )
    ->fileAttachmentsVisibility('public'),
```

`fileAttachmentsVisibility('public')` ensures Filament embeds the permanent public URL rather than a short-lived presigned URL (which would expire before the markdown is rendered).

`fileAttachmentsDirectory()` accepts a closure with injected Filament utilities — `$record` gives access to the current model instance. This organizes uploads into per-note directories (`notes/{slug}/{uuid}.jpg`) rather than a flat `attachments/` bucket. The `'notes/draft'` fallback handles the case where a new record hasn't been saved yet (no slug). Use equivalent `pages/{slug}` for `PageResource`.

The filenames themselves remain UUID-based (see Resolved Decisions), but the directory structure provides meaningful organization and makes it easy to identify or clean up attachments for a given note.

Determine the correct way to reference the `public` disk alias at implementation time — either the env var directly or a config helper.

### Phase 2.5: Raise Upload Size Limits

`MarkdownEditor` file attachments are server-proxied through Livewire → PHP → R2. PHP's defaults (`upload_max_filesize = 2M`, `post_max_size = 8M`) will reject anything larger. Since non-image files (PDFs, etc.) skip the EXIF tool and go straight through the editor, they need generous limits.

**Memory vs. disk:** PHP streams file uploads directly to a temporary file on disk (`/tmp` by default, controlled by `upload_tmp_dir`). File content is **not held in memory** — only normal PHP process overhead applies regardless of file size.

**Two places to configure:**

**1. PHP ini — set in the Dockerfile**

```dockerfile
RUN echo "upload_max_filesize = 50M\npost_max_size = 52M" \
    > /usr/local/etc/php/conf.d/uploads.ini
```

`post_max_size` must be slightly larger than `upload_max_filesize` (it covers the entire POST body including multipart boundaries).

**2. Caddy — request body limit in `Caddyfile`**

FrankenPHP embeds Caddy, so Caddy also needs to allow large bodies. Add inside the `route` block:

```
route {
    request_body {
        max_size 50MB
    }
    # ... existing directives
}
```

Without this, Caddy will reject large requests before PHP ever sees them.

**Choosing a limit:** 50MB is a reasonable cap for a personal site. Compressed images from the EXIF tool will be well under 2MB; this headroom is mainly for PDFs and other non-image files. Tune if needed.

### Phase 3: EXIF-Strip Tool _(complete)_

Built and deployed as a standalone static site at <https://davidharting.github.io/image-resizer/> (source: <https://github.com/davidharting/image-resizer>). Entirely client-side — no server action, no model, no database, and no integration with the Laravel app.

**The tool does:**

1. File input (image files only)
2. On select, `browser-image-compression` runs in the browser:
    - Strips EXIF (the canvas round-trip removes it)
    - Resizes to max 2000px on the longest side
    - Compresses to a target output size (e.g. 2MB max)
3. Shows a before/after size comparison and a preview of the processed image
4. Offers a download button for the clean file

Non-image files are out of scope for this tool — the MarkdownEditor handles non-image attachments (PDFs, etc.) as-is.

### Phase 4: Production Deployment

The public bucket and URL env vars are already set in `docker-compose.yml`. No new secrets needed.

If any new env vars are added during implementation, add them to `.env.example` and `docker-compose.yml`.

**Verify after deploy:**

- Drag an image into the MarkdownEditor on a Note — confirm the permanent R2 URL is embedded
- Use the EXIF-strip tool on a GPS-tagged photo, download the result, inspect with exiftool — confirm GPS data is absent
- Drag the clean image into the MarkdownEditor — confirm it uploads and renders correctly

---

## Open Questions

- **Disk alias in `fileAttachmentsDisk`:** The `public` disk alias in `filesystems.php` resolves to the correct disk via `FILESYSTEM_DISK_PUBLIC`. It's unclear whether Filament/Livewire resolves this alias the same way Laravel's `Storage` facade does, or whether the disk name must be passed explicitly (e.g. `env('FILESYSTEM_DISK_PUBLIC', 'local-public')`). Verify at implementation time before wiring this up.

## Implementation Order

1. **Raise upload size limits** — PHP ini (`upload_max_filesize`, `post_max_size`) in Dockerfile + Caddy `request_body max_size` in Caddyfile
2. **Configure MarkdownEditor** — add `fileAttachmentsDisk`, `fileAttachmentsDirectory`, `fileAttachmentsVisibility` to all relevant editors; write a feature test confirming file attachments work
3. **Build EXIF-strip tool** — Filament custom page + Alpine.js + `browser-image-compression`
4. **Deployment config** — verify env vars, deploy, test end-to-end

---

## Milestones

- [x] Public R2 bucket created with `cdn.davidharting.com` custom domain
- [x] `r2-public` filesystem disk configured in Laravel
- [ ] PHP ini and Caddy upload size limits raised (50MB)
- [ ] MarkdownEditor configured with R2 file attachments (public visibility, permanent URLs)
- [x] EXIF-strip tool built — standalone static site at <https://davidharting.github.io/image-resizer/>
- [ ] Production deployment verified end-to-end

## Resolved Decisions

- **Upload mechanism:** `MarkdownEditor` file attachments — server-proxied via Livewire → PHP → R2. Simpler than presigned URLs for this use case. PHP streams uploads to a temp file on disk (not in-memory), so large files don't affect memory. PHP ini limits (`upload_max_filesize`, `post_max_size`) and Caddy's body size limit must both be raised — see Phase 2.5.
- **No media library model:** `spatie/laravel-media-library`, a `FileUpload` model, and a Filament resource are not needed. Filament's built-in file attachment handling is sufficient.
- **EXIF handling:** Strip EXIF client-side using `browser-image-compression` in a dedicated standalone tool (<https://davidharting.github.io/image-resizer/>) before the file is dragged into the editor. The server never sees raw EXIF data. Built as a GitHub Pages static site rather than a Filament custom page — keeps it completely decoupled from the Laravel app.
- **Image conversions:** None server-side. No Imagick, Ghostscript, or optimizer binaries needed in Docker.
- **File types in EXIF tool:** Images only. Non-image files (PDFs, etc.) can be dragged into the MarkdownEditor directly — no processing needed.
- **fileAttachmentsVisibility:** `public` — permanent URLs must be embedded in markdown because short-lived presigned URLs expire before the content is rendered.
- **Disk strategy:** `local-public` for dev, `r2-public` for prod, controlled by `FILESYSTEM_DISK_PUBLIC` env var.
- **Custom domain:** `cdn.davidharting.com`
- **OG/social preview images:** Best handled with an explicit `og_image_url` field on Note/Page rather than inferring from content. Separate future feature.
- **Attachment filename customization:** `MarkdownEditor` does not expose a filename hook equivalent to `FileUpload`'s `getUploadedFileNameForStorageUsing()` — filenames remain UUID-based. However, `fileAttachmentsDirectory()` accepts a closure with injected utilities including `$record`, so files can be organized into per-record directories (`notes/{slug}/{uuid}.jpg`, `pages/{slug}/{uuid}.pdf`). This is good enough: the slug is in the URL path, and cleanup by record is straightforward.
