---
name: laravel-media-library-and-public-bucket
description: Set up a public bucket, integrate with Laravel Media Library, and possibly filament
status: in-progress
---

# Laravel Media Library and Public Bucket

## Background

I want to be able to reference / include media on my Notes and Pages. For instance, I might want to include a PDF on a Page for user's to donwload, or to include a transformed image on a Note to share pictures of a trip.

Whenever I upload an image, I want some way to ensure that I do not retain a version that has EXIF data and delete that from the bucket. i.e., I think I want to delete the originals.

I think with Filament, markdown pages can _not_ directly have Laravel Media Library attachments. The rich editor can, but I'm not using the rich editor.

So because of that, I wonder if the best way for me to integrate is to have a centralized media library - like a Media Eloquent model. I could then attach media-library media to that.
Of course, then Media model is a bit of a redundant name... Hm we can work on that I guess? or brainstorm other ways to assocaite it.

I guess what i'm saying is the expected flow given that I cannot attach media directly to Filament markdown fields is that I instead need to use a different portion of my Filaent admin to upload media (and have it transformed if appropriate), and then I get a link and reference that in my markdown.

Maybe where that leaves me is I actually need to attach media directly to the Note and Page objects instead of having markdown handle that, and somehow create filament inputs for those attachments? idk that is part of what i'm having you plan.

## Decision: Direct Attachments on Note & Page

We considered a standalone `Attachment` model vs. adding media-library directly to Note and Page. We chose **direct attachments** because:

- Almost everything on the site is already associated with a Note or Page
- Uploading inline on the same form is a nicer workflow than jumping between admin sections
- Fewer models and concepts to manage
- The `filament/spatie-laravel-media-library-plugin` handles the Filament integration with `SpatieMediaLibraryFileUpload`

The tradeoff is that reusing the same file across multiple Notes/Pages means re-uploading, but that's acceptable for a personal site.

**Workflow in practice:**
1. Edit a Note or Page in Filament admin
2. Upload files via the `SpatieMediaLibraryFileUpload` field on the form
3. Copy the public URL from the uploaded file
4. Reference it in the markdown content (`![alt](url)` for images, `[text](url)` for downloads)

---

## High-Level Plan

1. **Create a public R2 bucket** in Cloudflare with a custom domain
2. **Add `r2-public` filesystem disk** to Laravel
3. **Add image processing dependencies to Docker** — Imagick, Ghostscript, optimizer binaries (jpegoptim, optipng, pngquant, gifsicle)
4. **Install spatie/laravel-media-library** with custom table name `spatie_media` (avoids renaming existing `media` table)
5. **Install `filament/spatie-laravel-media-library-plugin` v5**
6. **Add `HasMedia` to Note and Page models** with image conversions (EXIF strip, max 2000px width, thumbnail for images + PDFs)
7. **Add `SpatieMediaLibraryFileUpload` to Note and Page Filament forms** — all file types allowed
8. **Update production deployment config** (docker secrets, env vars)

---

## Detailed Implementation Plan

### Phase 1: Cloudflare R2 Public Bucket Setup

**1.1 Create the public bucket in Cloudflare dashboard**
- Go to Cloudflare dashboard → R2 → Create Bucket
- Name: `davidharting-public` (or similar)
- Set up a custom domain like `cdn.davidharting.com` for clean public URLs
  - In Cloudflare R2 settings → Public Access → Custom Domain → `cdn.davidharting.com`
  - This automatically sets up the DNS and Cloudflare CDN caching

**1.2 Create R2 API credentials (or reuse existing)**
- If your existing R2 API token already has access to all buckets, you can reuse `R2_ACCESS_KEY_ID` and `R2_SECRET_ACCESS_KEY`
- Otherwise, create a new token with read/write access to the public bucket

### Phase 2: Laravel Filesystem Configuration

**2.1 Add the `r2-public` disk to `config/filesystems.php`**

```php
$r2PublicDisk = [
    'driver' => 's3',
    'key' => env('R2_ACCESS_KEY_ID'),
    'secret' => env('R2_SECRET_ACCESS_KEY'),
    'region' => 'auto',
    'bucket' => env('R2_PUBLIC_BUCKET'),
    'endpoint' => env('R2_ENDPOINT'),
    'url' => env('R2_PUBLIC_URL'), // e.g. https://cdn.davidharting.com
    'use_path_style_endpoint' => true,
    'visibility' => 'public',
    'throw' => false,
];
```

Add to the `disks` array: `'r2-public' => $r2PublicDisk`

**2.2 Add environment variables**

New env vars needed:
- `R2_PUBLIC_BUCKET` — the bucket name
- `R2_PUBLIC_URL` — the public-facing URL (e.g. `https://cdn.davidharting.com`)

Add to `.env.example`, and add secrets + environment entries to `docker-compose.yml`.

**2.3 Disk strategy: local for dev, R2 for prod**

We have 4 disks total — 2 local (dev) and 2 R2 (prod):

| Purpose | Dev disk | Prod disk |
|---------|----------|-----------|
| Private files | `local-private` | `r2-private` |
| Public media | `local-public` | `r2-public` |

Use an env var `FILESYSTEM_DISK_PUBLIC` (mirroring the existing `FILESYSTEM_DISK_PRIVATE` pattern) to select the public disk:
- `.env` (dev): `FILESYSTEM_DISK_PUBLIC=local-public`
- `docker-compose.yml` (prod): `FILESYSTEM_DISK_PUBLIC=r2-public`

Configure media-library's `disk_name` to read from this env var. This keeps R2 credentials out of local dev entirely.

### Phase 3: Docker Image Dependencies for Image Processing

Before installing the PHP packages, we need the system-level libraries they depend on. Measure Docker image size before and after to track the impact.

**3.1 Add PHP Imagick extension**

Add `imagick` to the `install-php-extensions` block in the Dockerfile. This is required for image conversions and PDF-to-image support.

**3.2 Add system packages for image processing**

Add to the `apt-get install` block in the Dockerfile:

```dockerfile
ghostscript libmagickwand-dev jpegoptim optipng pngquant gifsicle
```

- `ghostscript` — PDF-to-image conversion (used by spatie's PDF image generator)
- `libmagickwand-dev` — Imagick dependency
- `jpegoptim` — JPEG optimization
- `optipng` — PNG optimization
- `pngquant` — PNG quantization (lossy, significant size reduction)
- `gifsicle` — GIF optimization

**3.3 Add composer packages for optimization**

```bash
composer require spatie/image-optimizer
composer require spatie/pdf-to-image
```

`spatie/image-optimizer` automatically detects and uses whichever optimizer binaries are available on the system. `spatie/pdf-to-image` enables PDF thumbnail generation.

### Phase 4: Install and Configure spatie/laravel-media-library (was Phase 3)

We use a custom table name (`spatie_media`) and custom model to avoid conflicting with the existing `media` table (which tracks books/movies/etc.). No need to rename anything existing.

**3.1 Install the packages**

```bash
composer require spatie/laravel-media-library
composer require filament/spatie-laravel-media-library-plugin:"^5.2"
```

**3.2 Publish migration and change table name**

```bash
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
```

Edit the published migration to use `spatie_media` instead of `media` as the table name.

**3.3 Create a custom spatie Media model**

Create `app/Models/SpatieMedia.php` extending `Spatie\MediaLibrary\MediaCollections\Models\Media`:

```php
class SpatieMedia extends \Spatie\MediaLibrary\MediaCollections\Models\Media
{
    protected $table = 'spatie_media';
}
```

**3.4 Publish and configure media-library config**

```bash
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-config"
```

Key config changes in `config/media-library.php`:
- Set `media_model` to `App\Models\SpatieMedia`
- Set `disk_name` — see Phase 2.3 for disk strategy
- Set `queue_connection_name` to `database` so image conversions run in background via the queue

**3.5 Run migrations**

```bash
php artisan migrate
```

### Phase 5: Add HasMedia to Note and Page Models

**4.1 Implement the interface on both models**

```php
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class Note extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }

    public function registerMediaConversions(?SpatieMedia $media = null): void
    {
        // 'optimized' only applies to actual images (EXIF strip + resize).
        // PDFs and other files don't get this conversion.
        if ($media && str_starts_with($media->mime_type, 'image/')) {
            $this->addMediaConversion('optimized')
                ->stripExif()
                ->width(2000)
                ->queued();
        }

        // 'thumb' applies to images and PDFs (spatie's PDF generator
        // converts page 1 to a JPG thumbnail automatically).
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->queued();
    }
}
```

Same pattern for `Page`. Consider extracting a trait or shared method if the config is identical.

**4.2 Mixed file types in a single collection (R1 resolved)**

The `attachments` collection accepts any file type. Spatie uses "image generators" to decide whether conversions apply — when no generator matches, conversions are silently skipped. The `optimized` conversion is gated behind a MIME type check so it only applies to actual images. The `thumb` conversion applies to both images and PDFs (spatie's PDF generator converts page 1 to a JPG thumbnail via Imagick + Ghostscript). Non-visual files (`.docx`, `.zip`, `.txt`, etc.) get no conversions and are stored as-is. Note: `getUrl('optimized')` returns an empty string for files that didn't get that conversion — always fall back to `getUrl()` for non-images.

**4.3 Conversions are queued**

All conversions run via the queue (`->queued()`). This means after uploading an image, the `optimized` and `thumb` versions won't be available immediately — they'll be ready after the queue worker processes the job. For a personal site this is fine; the queue worker is already running in production.

**4.4 EXIF handling: delete originals after conversion**

After conversions run, delete the original image (which still has EXIF data) from the bucket. The `optimized` conversion becomes the canonical version you reference in markdown. See research item R2 below for the best approach.

**4.3 Responsive images: deferred**

Spatie supports automatic responsive image generation via `withResponsiveImages()`, which generates `srcset` markup with a blur-up SVG placeholder. However, since we're referencing single URLs in markdown (not using Blade's `{{ $media }}` rendering), responsive images aren't useful yet. This could be revisited if we build Blade components for rendering note content.

### Phase 6: Add File Upload to Filament Forms

**5.1 Add `SpatieMediaLibraryFileUpload` to NoteResource and PageResource forms**

```php
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

// Add to the form components array:
SpatieMediaLibraryFileUpload::make('attachments')
    ->collection('attachments')
    ->multiple()
    ->reorderable()
    ->columnSpanFull(),
```

No `.image()` restriction — allow any file type (PDFs, images, etc.).

After uploading, each file's public URL is accessible via `$note->getFirstMediaUrl('attachments', 'optimized')` for images or `$note->getFirstMediaUrl('attachments')` for non-image files.

**5.2 Display public URLs for copying**

Add a section to the edit form (or a custom view) that lists uploaded media with their public URLs so you can copy them into the markdown. This could be a `Placeholder` or `ViewField` component showing the URLs.

**Important:** For images, always show the `optimized` conversion URL (not the original), since the original may be deleted after conversion. For non-image files, show the base URL. Alternatively, if we can copy the `optimized` file over the original path in the bucket (so both URLs resolve to the same EXIF-stripped file), we could simplify and always show the base URL. See research item R2.

### Phase 7: Production Deployment

**6.1 Docker/secrets updates**

Add to `docker-compose.yml`:
- New secrets: `R2_PUBLIC_BUCKET`, `R2_PUBLIC_URL`
- New secret files in `./secrets/`
- New environment variables referencing the secrets

**6.2 Create the secret files on the server**

```bash
echo "your-public-bucket-name" > secrets/R2_PUBLIC_BUCKET.txt
echo "https://cdn.davidharting.com" > secrets/R2_PUBLIC_URL.txt
```

**6.3 Deploy and verify**

- Deploy the new code
- Verify migrations run (creates `spatie_media` table)
- Test uploading via Filament admin on a Note
- Verify public URLs are accessible via the custom domain

---

## Implementation Order (suggested commit sequence)

1. **Add `r2-public` filesystem disk** — just config, no behavior change
2. **Add image processing deps to Dockerfile** — Imagick ext, ghostscript, jpegoptim, optipng, pngquant, gifsicle. Measure image size before/after.
3. **Install spatie/laravel-media-library + Filament plugin + optimizer packages** — packages, custom `SpatieMedia` model with `spatie_media` table, config
4. **Add `HasMedia` to Note and Page models** — media collections, `optimized` (images only) + `thumb` (images + PDFs) conversions
5. **Add `SpatieMediaLibraryFileUpload` to Filament forms** — the upload UI + URL display
6. **Docker/deployment config** — secrets and environment variables

## Research Needed

These items need investigation before or during implementation:

~~**R1: Mixed file types in a single media collection.**~~ Resolved — see Resolved Decisions below.

**R2: EXIF original deletion strategy.** After the queued `optimized` conversion runs, what's the best way to delete or replace the original image file? Options to evaluate:
- Event listener on conversion completion that deletes the original from disk
- Copying the `optimized` file over the original path so both URLs resolve to the stripped version (simpler for URL copying in Filament, but possibly wasteful — or maybe R2/Cloudflare deduplicates by hash)
- `performConversionsOnOriginal` — does spatie support modifying the original in-place?
- Accepting that originals persist but are never linked (simplest, but leaves EXIF data in the bucket)

**R3: Filament spatie media library plugin v5 compatibility.** Packagist shows v5.2.1 released Feb 2026, which looks right. Confirm that the `SpatieMediaLibraryFileUpload` component works with Filament v5's form API (the import path may have changed, e.g. from `Filament\Forms\Components\SpatieMediaLibraryFileUpload` to a different namespace). Check for any migration guides or breaking changes.

## Resolved Decisions

- **Table naming:** Use custom table name `spatie_media` with custom model `App\Models\SpatieMedia`. No need to rename existing `media` table.
- **EXIF handling:** Delete the original after conversion. The `optimized` conversion (EXIF-stripped, max 2000px) replaces it. Exact mechanism TBD (see R2).
- **Image conversions:** `optimized` (EXIF strip + max 2000px width) and `thumb` (300x300 EXIF-stripped). Always queued.
- **Responsive images:** Deferred — not useful when pasting single URLs in markdown. Revisit if we build Blade rendering components.
- **File types:** No restrictions — allow images, PDFs, any file type in a single collection. Conversions only apply to images.
- **Disk strategy:** `local-public` for dev, `r2-public` for prod, controlled by `FILESYSTEM_DISK_PUBLIC` env var.
- **Custom domain:** `cdn.davidharting.com`
- **R1 — Mixed file types in a single collection:** Confirmed working. Spatie uses "image generators" to decide whether conversions apply. When no generator matches a file type (e.g., `.docx`, `.zip`, `.txt`), conversions are silently skipped. PDFs *do* have a generator — with Imagick + Ghostscript + `spatie/pdf-to-image` installed, PDFs get a page-1 thumbnail via the `thumb` conversion. Use a MIME type check in `registerMediaConversions` to limit `stripExif` to actual images (PDFs don't have EXIF but shouldn't get the `optimized` conversion meant for photos). If `getUrl('optimized')` is called on a file that didn't get that conversion, it returns an empty string — fall back to `getUrl()`.
- **Dependencies:** Install Imagick (PHP extension), Ghostscript, and all spatie image optimizer binaries (jpegoptim, optipng, pngquant, gifsicle). Measure Docker image size before and after to track bloat.
