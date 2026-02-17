---
name: laravel-media-library-and-public-bucket
description: Set up a public bucket, a FileUpload model with spatie/laravel-media-library, and a Filament admin UI for uploading files with client-side transformations
status: in-progress
---

# Laravel Media Library and Public Bucket

## Prerequisites / Current State

- **PHP:** 8.3 (platform config in `composer.json`; 8.4 locally)
- **Laravel:** 12.x
- **Filament:** 5.x (with Livewire 4.x)
- **Existing `media` table:** Tracks books, movies, etc. via `App\Models\Media` — completely unrelated to file uploads. This is why spatie's table must be named `spatie_media`.
- **Existing disk config (`config/filesystems.php`):** `local-private`, `local-public`, `r2-private`, and `r2-public` disks are defined. A `public` disk alias resolves to the correct config via a `match` on `FILESYSTEM_DISK_PUBLIC` env var. `FILESYSTEM_DISK_PRIVATE` controls private disk selection. Public bucket name (`davidhartingdotcom-public`) and URL (`https://cdn.davidharting.com`) are set as plain environment variables in docker-compose (not secrets, since they're public values).
- **Queue:** `database` driver, queue worker already running in production.

## Background

I want to be able to reference / include media on my Notes and Pages. For instance, I might want to include a PDF on a Page for users to download, or to include an image on a Note to share pictures of a trip.

Whenever I upload an image, I want to ensure that EXIF data (especially GPS location) is stripped before the file reaches the server or the public bucket.

I also want a centralized place to browse all uploaded files and copy URLs to paste into markdown content.

## Decision: Standalone `FileUpload` Model with Client-Side Transformations

We considered adding `HasMedia` directly to Note and Page vs. having a standalone upload model. We chose a **standalone `FileUpload` model** because:

- A centralized index of all uploads is more useful than files buried inside Note/Page edit forms — easier to find a URL uploaded weeks ago
- Files can be reused across multiple Notes/Pages without re-uploading
- Client-side image transformation (resize + EXIF strip) is cleaner than server-side: no Imagick, no Ghostscript, no optimizer binaries in Docker, no event listener complexity
- EXIF is stripped before the file ever leaves the browser — the bucket never sees raw GPS data
- The "social preview image" problem (picking an OG image per note) is best solved with an explicit `og_image_url` field anyway — the "first attached image" heuristic is fragile regardless of architecture

**Workflow in practice:**

1. Go to the FileUpload section in Filament admin
2. Select a file — the browser transforms it (strips EXIF, resizes, compresses) before upload
3. The processed file is uploaded to the `FileUpload` model via spatie/laravel-media-library
4. Spatie stores the file to the public R2 disk
5. Copy the public URL from the index page
6. Paste the URL into Note/Page markdown (`![alt](url)` for images, `[text](url)` for downloads)

---

## High-Level Plan

1. **Create a public R2 bucket** in Cloudflare with a custom domain *(already done)*
2. **Add `r2-public` filesystem disk** to Laravel *(already done)*
3. **Install spatie/laravel-media-library** with custom table name `spatie_media`
4. **Create `FileUpload` model and migration**
5. **Create `FileUploadResource` in Filament** — index with thumbnails and copyable URLs, create form with client-side image processing
6. **Wire up client-side transformation JS** — Alpine.js + `browser-image-compression` to strip EXIF and resize before upload
7. **Update production deployment config** (docker secrets, env vars)

---

## Detailed Implementation Plan

### Phase 1: Cloudflare R2 Public Bucket Setup *(complete)*

- Public R2 bucket created with `cdn.davidharting.com` as the custom domain
- `r2-public` filesystem disk is already configured in `config/filesystems.php`
- `FILESYSTEM_DISK_PUBLIC` env var selects `local-public` in dev and `r2-public` in prod

### Phase 2: Install and Configure spatie/laravel-media-library

We use a custom table name (`spatie_media`) and custom model to avoid conflicting with the existing `media` table (which tracks books/movies/etc.).

**2.1 Install the package**

```bash
composer require spatie/laravel-media-library
```

**2.2 Publish migration and change table name**

```bash
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
```

Edit the published migration to use `spatie_media` instead of `media` as the table name.

**2.3 Create a custom spatie Media model**

Create `app/Models/SpatieMedia.php` extending `Spatie\MediaLibrary\MediaCollections\Models\Media`:

```php
class SpatieMedia extends \Spatie\MediaLibrary\MediaCollections\Models\Media
{
    protected $table = 'spatie_media';
}
```

**2.4 Publish and configure media-library config**

```bash
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-config"
```

Key config changes in `config/media-library.php`:

- Set `media_model` to `App\Models\SpatieMedia`
- Set `disk_name` to `env('FILESYSTEM_DISK_PUBLIC', 'local-public')`
- No conversions, no queue needed — client handles image processing

**2.5 Run migrations**

```bash
php artisan migrate
```

### Phase 3: `FileUpload` Model and Migration

**3.1 Create the migration**

```php
Schema::create('file_uploads', function (Blueprint $table) {
    $table->id();
    $table->string('name')->nullable();
    $table->text('description')->nullable();
    $table->timestamps();
});
```

**3.2 Create the model**

```php
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class FileUpload extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')->singleFile();
    }

    // No registerMediaConversions — client handles transformations
}
```

A single `file` collection with `singleFile()` keeps it simple: one file per `FileUpload` record.

### Phase 4: Filament `FileUploadResource`

Install the Filament spatie media library plugin:

```bash
composer require filament/spatie-laravel-media-library-plugin:"^5.0"
```

**4.1 Index table**

The index page should show:
- Image thumbnail (for images — use the full file URL, lazy loaded; browsers handle this fine for a personal site)
- Name
- MIME type
- File size
- Public URL (copyable — a click-to-copy button or a read-only text input)
- Uploaded at

**4.2 Create form**

```php
SpatieMediaLibraryFileUpload::make('file')
    ->collection('file')
    ->columnSpanFull(),

TextInput::make('name')
    ->nullable(),

Textarea::make('description')
    ->nullable(),
```

**4.3 Client-side transformation**

Before the file reaches Livewire's upload handler, Alpine.js intercepts the file input change event, processes the image using `browser-image-compression`, and replaces the file in the input with the processed blob.

`browser-image-compression` handles:
- EXIF stripping (the canvas round-trip removes it)
- Resize to a max dimension (e.g. 2000px)
- Compression to a target size (e.g. 2MB max output)

Non-image files (PDFs, etc.) are passed through unchanged.

This can be wired up as a custom Filament form component or via a Livewire lifecycle hook. Determine the cleanest integration point at implementation time — the key requirement is that the file is processed *before* Livewire uploads it to the server.

### Phase 5: Production Deployment

**5.1 Docker/secrets updates**

The public bucket and URL env vars are already set as plain environment variables in `docker-compose.yml` (not secrets). No new secrets needed for the bucket config.

If any new env vars are added during implementation, add them to `.env.example` and `docker-compose.yml`.

**5.2 Deploy and verify**

- Deploy the new code
- Verify migrations run (creates `file_uploads` and `spatie_media` tables)
- Test uploading an image via Filament admin
- Verify the public URL is accessible via `cdn.davidharting.com`
- Verify EXIF/GPS data is absent from the uploaded file

---

## Implementation Order

Work through these in order. Each item may result in multiple small, focused commits — use as many as needed.

1. **Install spatie/laravel-media-library** — package, published migration (rename table to `spatie_media`), custom `SpatieMedia` model, published config
2. **Install `filament/spatie-laravel-media-library-plugin`**
3. **Create `FileUpload` model and migration**
4. **Create `FileUploadResource` in Filament** — index with thumbnails and copyable URLs, create/edit form
5. **Wire up client-side image transformation** — Alpine.js + `browser-image-compression`
6. **Docker/deployment config** — verify env vars are in place, update as needed

---

## Milestones

- [x] Public R2 bucket created with `cdn.davidharting.com` custom domain
- [x] `r2-public` filesystem disk configured in Laravel
- [ ] spatie/laravel-media-library installed and configured (custom `spatie_media` table, `SpatieMedia` model)
- [ ] Filament spatie media library plugin installed
- [ ] `FileUpload` model and migration created
- [ ] `FileUploadResource` in Filament with index (thumbnails + copyable URLs) and create form
- [ ] Client-side image transformation wired up (EXIF strip + resize before upload)
- [ ] Production deployment verified end-to-end

## Resolved Decisions

- **Table naming:** Use custom table name `spatie_media` with custom model `App\Models\SpatieMedia`. No need to rename existing `media` table.
- **EXIF handling:** Strip EXIF client-side in the browser before upload using `browser-image-compression`. The server and bucket never see raw EXIF data. No server-side listener needed.
- **Image conversions:** None. Client handles resizing and compression. No Imagick, Ghostscript, or optimizer binaries needed in Docker.
- **Server-side processing packages:** Not needed. `spatie/image-optimizer` and `spatie/pdf-to-image` are not required.
- **File types:** Images are transformed client-side; non-image files (PDFs, etc.) are uploaded as-is.
- **Disk strategy:** `local-public` for dev, `r2-public` for prod, controlled by `FILESYSTEM_DISK_PUBLIC` env var.
- **Custom domain:** `cdn.davidharting.com`
- **OG/social preview images:** Best handled with an explicit `og_image_url` field on Note/Page rather than inferring from content. The "first image in the post" heuristic is fragile regardless of architecture. This is a separate future feature.
- **File reuse:** Upload once, copy URL, paste anywhere. No re-uploading the same file per Note/Page.
- **R3 — Filament spatie media library plugin v5 compatibility:** Confirmed compatible. The plugin has stable v5 releases on Packagist (v5.2.0 as of Jan 29, 2026), tracking Filament v5 releases. The Filament plugins website incorrectly shows "Not compatible with v5" but this is outdated. Install with `filament/spatie-laravel-media-library-plugin:"^5.0"`. Import path is unchanged from v4: `use Filament\Forms\Components\SpatieMediaLibraryFileUpload;`. Filament v5 itself has no breaking API changes from v4 — it exists solely to support Livewire v4. No code changes needed compared to v4 usage.
