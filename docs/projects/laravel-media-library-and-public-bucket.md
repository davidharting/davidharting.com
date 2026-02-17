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
5. **Add a presign endpoint** — authenticated Laravel route that generates a presigned PUT URL for R2
6. **Add an upload-complete endpoint** — registers the uploaded file with spatie via `addMediaFromDisk()`
7. **Create `FileUploadResource` in Filament** — index with thumbnails and copyable URLs, custom Alpine.js upload component that transforms client-side then PUTs directly to R2
8. **Update production deployment config** (env vars)

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

### Phase 4: Server Endpoints for Signed Upload

The `filament/spatie-laravel-media-library-plugin` is **not needed** — its main value is the `SpatieMediaLibraryFileUpload` Livewire component, which we're bypassing entirely. Spatie itself is still used for the data model and `addMediaFromDisk()`.

**4.1 Presign endpoint**

An authenticated route (Filament admin middleware) that generates a presigned PUT URL for R2:

```
POST /admin/file-uploads/presign
Body: { filename, content_type }
Response: { upload_url, object_key }
```

The controller uses the S3 client (R2 is S3-compatible) to generate a presigned PUT URL with a short TTL (e.g. 5 minutes). The `object_key` should be a UUID-based path to avoid collisions and guessability.

**4.2 Upload-complete endpoint**

After the client successfully PUTs to R2, it notifies the server:

```
POST /admin/file-uploads
Body: { object_key, name, description }
Response: { id, url }
```

The controller:
1. Creates a `FileUpload` record
2. Calls `$fileUpload->addMediaFromDisk($objectKey, 'r2-public')` — tells spatie the file is already on the disk, creating the `spatie_media` row without re-uploading

### Phase 5: Filament `FileUploadResource`

**5.1 Index table**

Built as a standard Filament resource table. Columns:
- Image thumbnail (for images — access via `$record->getFirstMedia('file')?->getUrl()`, lazy loaded)
- Name
- MIME type and file size (from the spatie media record via `$record->getFirstMedia('file')`)
- Public URL (copyable — click-to-copy action)
- Uploaded at

**5.2 Create form — custom Alpine.js upload component**

The create page uses a custom Alpine.js component instead of any Livewire file upload. The full client-side flow:

1. User selects a file
2. If it's an image, `browser-image-compression` runs in the browser:
   - Strips EXIF (canvas round-trip removes it)
   - Resizes to max 2000px
   - Compresses to a target size (e.g. 2MB max)
3. Non-image files are passed through unchanged
4. Component POSTs to the presign endpoint → receives `{ upload_url, object_key }`
5. Component PUTs the file (or blob) directly to `upload_url` — bypasses Laravel entirely
6. On success, component POSTs to the upload-complete endpoint with `object_key` + form fields
7. Redirects to the index page

The form fields (`name`, `description`) are plain inputs handled by the same Alpine component or a surrounding Livewire form — determine the cleanest integration at implementation time.

### Phase 6: Production Deployment

**6.1 Docker/secrets updates**

The public bucket and URL env vars are already set as plain environment variables in `docker-compose.yml` (not secrets). No new secrets needed for the bucket config.

If any new env vars are added during implementation, add them to `.env.example` and `docker-compose.yml`.

**6.2 Deploy and verify**

- Deploy the new code
- Verify migrations run (creates `file_uploads` and `spatie_media` tables)
- Test uploading an image via Filament admin
- Verify the public URL is accessible via `cdn.davidharting.com`
- Verify EXIF/GPS data is absent from the uploaded file

---

## Implementation Order

Work through these in order. Each item may result in multiple small, focused commits — use as many as needed.

1. **Install spatie/laravel-media-library** — package, published migration (rename table to `spatie_media`), custom `SpatieMedia` model, published config
2. **Create `FileUpload` model and migration**
3. **Add presign endpoint** — authenticated route returning a presigned R2 PUT URL and object key
4. **Add upload-complete endpoint** — creates `FileUpload` record and registers media with `addMediaFromDisk()`
5. **Create `FileUploadResource` in Filament** — index table with thumbnails and copyable URLs
6. **Build custom Alpine.js upload component** — client-side transform + presigned PUT + completion POST
7. **Deployment config** — verify env vars are in place, update as needed

---

## Milestones

- [x] Public R2 bucket created with `cdn.davidharting.com` custom domain
- [x] `r2-public` filesystem disk configured in Laravel
- [ ] spatie/laravel-media-library installed and configured (custom `spatie_media` table, `SpatieMedia` model)
- [ ] `FileUpload` model and migration created
- [ ] Presign endpoint — returns presigned R2 PUT URL + object key
- [ ] Upload-complete endpoint — creates `FileUpload` record and registers media with spatie
- [ ] `FileUploadResource` in Filament — index with thumbnails and copyable URLs
- [ ] Custom Alpine.js upload component — client-side transform + direct PUT to R2 + completion POST
- [ ] Production deployment verified end-to-end

## Resolved Decisions

- **Table naming:** Use custom table name `spatie_media` with custom model `App\Models\SpatieMedia`. No need to rename existing `media` table.
- **Upload mechanism:** Browser uploads directly to R2 via a presigned PUT URL. The file never passes through the Laravel server, eliminating PHP upload size limits entirely.
- **EXIF handling:** Strip EXIF client-side in the browser before upload using `browser-image-compression`. The server and bucket never see raw EXIF data. No server-side listener needed.
- **Image conversions:** None. Client handles resizing and compression. No Imagick, Ghostscript, or optimizer binaries needed in Docker.
- **Server-side processing packages:** Not needed. `spatie/image-optimizer` and `spatie/pdf-to-image` are not required.
- **Filament spatie plugin:** Not needed. `filament/spatie-laravel-media-library-plugin` is designed around Livewire-proxied uploads (`SpatieMediaLibraryFileUpload`), which we're bypassing. Spatie itself is still used for the data model and `addMediaFromDisk()`.
- **File types:** Images are transformed client-side; non-image files (PDFs, etc.) are uploaded as-is.
- **Disk strategy:** `local-public` for dev, `r2-public` for prod, controlled by `FILESYSTEM_DISK_PUBLIC` env var.
- **Custom domain:** `cdn.davidharting.com`
- **OG/social preview images:** Best handled with an explicit `og_image_url` field on Note/Page rather than inferring from content. The "first image in the post" heuristic is fragile regardless of architecture. This is a separate future feature.
- **File reuse:** Upload once, copy URL, paste anywhere. No re-uploading the same file per Note/Page.
