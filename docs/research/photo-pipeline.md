---
name: photo-pipeline
description: How memory photos get from an iPhone to a private, web-sized image — HEIC conversion, resizing, upload limits, R2 storage and private serving
status: research
researched: 2026-10-03
---

# Photo pipeline for memories

Research note for [#246](https://github.com/davidharting/davidharting.com/issues/246), part of the
_Monthly memories_ map [#245](https://github.com/davidharting/davidharting.com/issues/245). Standing
decisions from the map: up to 10 photos per **Memory**, each with an optional caption; keep the original
privately and show a web-sized copy; only David and Katie can see anything; removed photos are soft-deleted.

Every claim is tagged:

- **[DOCUMENTED]**: stated in official docs, a distro package index, or package source code (linked).
- **[REPO]**: read from this repo at `origin/main` (`120aca8`).
- **[UNVERIFIED]**: plausible, but I could not settle it from a primary source. Each one is a spike item.

## Bottom line

1. **Convert with the libvips command-line tool, not a PHP extension.** Add `libvips-tools` to the
   Dockerfile and call `vipsthumbnail` through Laravel's `Process`. It reads HEIC through libheif,
   auto-rotates, resizes with shrink-on-load, converts Display P3 to sRGB, strips metadata, and uses little
   memory. It is the only option that also runs under the Herd-lite static PHP binary, because it needs no
   extension. Dev installs it with `brew install vips`, and CI with `apt-get install libvips-tools`.
2. **Process on the queue worker.** The web service can't see files on the worker's disk, so the original
   goes to `r2-private` first. A job then downloads it, makes a `web` copy (~2048 px JPEG) and a `thumb`
   (~512 px), and uploads both. The UI shows a "processing" placeholder and polls until the photo is ready.
3. **Uploads already go direct to R2 in production.** Livewire uses presigned PUTs whenever the default
   disk uses the `s3` driver, and ours does (`default => 'private'` → `r2-private`). Photo bytes never touch
   Render, Caddy or PHP's 27 MB limits, so we need no chunking. Three caveats: Livewire's S3 mode **refuses
   `multiple`**, so upload one file per request; the R2 bucket needs a CORS rule for browser PUTs; and the
   default 12 MB temp-upload rule should be raised.
4. **Serve through an authorizing route that redirects to a short-lived presigned R2 URL.** Markup carries
   only stable app URLs (`/memories/photos/{photo}/web`). The route runs the policy (404 for anyone else)
   and returns a 302 to a presigned URL that expires in minutes. Streaming bytes through PHP is the
   fallback. It is safer against leaks but ties up the single FrankenPHP thread on the starter plan.

The trade-offs are at the end, in [Recommendation and trade-offs](#recommendation-and-trade-offs).

---

## 1. What we already have

- **Base image:** `dunglas/frankenphp:php8.4-bookworm`, with extensions installed via
  `install-php-extensions intl pcntl pdo pdo_pgsql zip`. No gd, imagick, vips or ffi in production.
  **[REPO]** `Dockerfile`
- **PHP limits:** `memory_limit = 256M`, `upload_max_filesize = 25M`, `post_max_size = 27M`. **[REPO]**
  `Dockerfile`
- **Caddy limit:** `request_body { max_size 27MB }` inside the route block. **[REPO]** `Caddyfile`. Caddy
  answers 413 when a body exceeds `max_size`. **[DOCUMENTED]**
  [Caddy `request_body`](https://caddyserver.com/docs/caddyfile/directives/request_body)
- **Octane:** `max_execution_time => 30` seconds per request. **[REPO]** `config/octane.php`
- **Render plan:** web and worker are both `starter`. The Dockerfile comment sizes PHP for "0.5 CPU,
  512 MB … ~1 thread". **[REPO]** `render.yaml`, `Dockerfile`
- **Disks:** `config/filesystems.php` defines `private` and `public` aliases. `private` resolves to
  `r2-private` (`s3` driver, `region: auto`, path-style endpoint, `visibility: private`) when
  `FILESYSTEM_DISK_PRIVATE=r2-private`, which `render.yaml` sets for prod and previews. Otherwise it is a
  local disk with `serve => true`. **The default disk is `private`.** **[REPO]**
- **Queue:** `database` driver, `retry_after => 90`. Worker runs
  `queue:work -v --tries=3 --backoff=30`. **[REPO]** `config/queue.php`, `render.yaml`
- **Precedent:** `FileShareController` already serves private files with
  `$disk->temporaryUrl($path, now()->addMinutes(5))`. **[REPO]**
- **Installed versions:** `laravel/framework` v13.18.1, `livewire/livewire` v4.3.3. **[REPO]**
  `composer.lock`
- **Local dev:** PHP comes from the Herd-lite static binary (`mise.toml`), which runs `queue:work`. The web
  server is `php artisan octane:frankenphp`, which runs FrankenPHP's own binary. **[REPO]**
  `pitchfork.toml`. The brief says Herd-lite has gd and exif but cannot load extensions.
- **Render filesystem:** "By default, Render services have an ephemeral filesystem … changes … are lost
  every time the service redeploys or restarts." A disk "is accessible by only a single service
  instance," so **the web service and the worker cannot share local files.** **[DOCUMENTED]**
  [Render: Persistent disks](https://render.com/docs/disks)

---

## 2. Formats: getting HEIC into a web format

### Will we actually receive HEIC?

Yes. Plan for it. Safari has historically transcoded HEIC to JPEG on file inputs in some cases. WebKit
bug 212489 added that for **macOS** when `accept` lists a type the platform can encode to. **[DOCUMENTED]**
[WebKit 212489](https://bugs.webkit.org/show_bug.cgi?id=212489). The Safari 27.0 release notes then
list: "Fixed an issue where HEIC images were incorrectly converted to JPEG when uploaded via drag-and-drop
or file input." **[DOCUMENTED]**
[WebKit features for Safari 27.0](https://webkit.org/blog/18325/webkit-features-for-safari-27-0/). That
behaviour has changed between releases, so the server must accept HEIC/HEIF and JPEG alike.

**Live Photos:** I expect a web file input to deliver only the still image and not the paired video. I
found no Apple or WebKit doc that says so. **[UNVERIFIED]** Accept only `image/*` and ignore anything else.

**Validation:** Laravel's `image` rule allows only `jpg, jpeg, png, gif, bmp, webp`, so it **rejects
HEIC**. **[DOCUMENTED]** `ValidatesAttributes::validateImage()` in
[laravel/framework v13.18.1](https://github.com/laravel/framework/blob/v13.18.1/src/Illuminate/Validation/Concerns/ValidatesAttributes.php).
Use `mimes:jpg,jpeg,png,webp,heic,heif` and test it with a real iPhone fixture. Whether `fileinfo` sniffs
every iPhone HEIC as `image/heic` is **[UNVERIFIED]**.

### What Debian bookworm (our base) ships

| Package (bookworm)                      | Version          | HEIC-relevant facts                                                               |
| --------------------------------------- | ---------------- | --------------------------------------------------------------------------------- |
| `libheif1`                              | 1.15.1-1+deb12u1 | Depends directly on `libde265-0` (HEVC decode), `libx265`, `libaom3`, `libdav1d6` |
| `libvips42`                             | 8.14.1-3+deb12u3 | Depends on `libheif1 (>= 1.6.0)`                                                  |
| `libvips-tools`                         | 8.14.1-3+deb12u3 | Ships `/usr/bin/vips`, `vipsthumbnail`, `vipsheader`                              |
| `libmagickcore-6.q16-6` (ImageMagick 6) | 6.9.11.60        | Depends on `libheif1 (>= 1.4.0)`                                                  |
| `libheif-examples`                      | 1.15.1-1+deb12u1 | Ships `/usr/bin/heif-convert`, `heif-enc`, `heif-info`                            |

**[DOCUMENTED]** packages.debian.org:
[libheif1](https://packages.debian.org/bookworm/libheif1),
[libvips42](https://packages.debian.org/bookworm/libvips42),
[libvips-tools files](https://packages.debian.org/bookworm/amd64/libvips-tools/filelist),
[libmagickcore-6.q16-6](https://packages.debian.org/bookworm/libmagickcore-6.q16-6),
[libheif-examples files](https://packages.debian.org/bookworm/amd64/libheif-examples/filelist).

On trixie, `libvips42t64` is 8.16.1 and `libheif1` is 1.23.4. There, the HEVC decoder is a separate
`libheif-plugin-libde265` package, which `libheif1` depends on. **[DOCUMENTED]**
[libvips42t64](https://packages.debian.org/trixie/libvips42t64),
[libheif1 (trixie)](https://packages.debian.org/trixie/libheif1). `dunglas/frankenphp` publishes
`php8.4-trixie` tags as well as bookworm ones. **[DOCUMENTED]** Docker Hub tag list. Moving to trixie is
optional. It would narrow the version gap with Homebrew (below) but is its own change.

### The options

#### A. Imagick (PHP extension) with ImageMagick 6 + libheif

- **Prod:** `install-php-extensions imagick` builds it. ImageMagick 6 on bookworm links libheif.
  **[DOCUMENTED]**, as above.
- **FrankenPHP known issue:** "ImageMagick's OpenMP threads conflict with FrankenPHP's threads, causing
  instability and crashes." The mitigation is `Imagick::setResourceLimit(RESOURCETYPE_THREAD, 1)` or
  rebuilding without OpenMP. **[DOCUMENTED]**
  [FrankenPHP known issues](https://frankenphp.dev/docs/known-issues/). The extension loads into the web
  process too, even if only the CLI worker calls it.
- **Laravel's first-party Image API** (`Illuminate\Support\Facades\Image`) arrived in **v13.20.0**
  ("Adds first-party support for `image` processing", #59276). A HEIC dimension fix followed in v13.25.0.
  **[DOCUMENTED]**
  [framework CHANGELOG](https://github.com/laravel/framework/blob/13.x/CHANGELOG.md). It supports only
  `gd` and `imagick`, requires `intervention/image:^4.0`, and needs an upgrade from our v13.18.1.
  **[DOCUMENTED]** [Laravel 13 Image Manipulation](https://laravel.com/docs/13.x/images)
- **Laravel's driver does not strip metadata.** `ImagickDriver` builds
  `ImageManager::usingDriver(InterventionImagickDriver::class)` with no options. **[DOCUMENTED]**
  [ImagickDriver.php](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Image/Drivers/ImagickDriver.php).
  Intervention v4's `strip` option defaults to `false`. **[DOCUMENTED]**
  [Intervention v4 configuration](https://image.intervention.io/v4/getting-started/configuration-drivers).
  An Imagick-encoded web copy would therefore keep EXIF, **including GPS**, unless we strip it ourselves.
- **Memory:** the Laravel driver decodes from a `string $contents` held in memory. **[DOCUMENTED]**
  `InterventionDriver::process()`. ImageMagick Q16 holds the full decoded bitmap, which is large for 12 MP
  and much larger for 48 MP. I have not measured it. **[UNVERIFIED]**
- **Dev/CI:** Herd-lite can't load Imagick, so local dev cannot run this path at all.

#### B. php-vips (FFI binding to libvips)

- Needs the FFI extension. "php-vips does not yet support preloading, so you need to enable FFI
  globally." On PHP 8.3+ it also needs `zend.max_allowed_stack_size=-1`. **[DOCUMENTED]**
  [libvips/php-vips README](https://github.com/libvips/php-vips)
- `ffi.enable` defaults to `"preload"`, which allows FFI only in the CLI SAPI and preloaded files.
  **[DOCUMENTED]** [PHP: FFI runtime configuration](https://www.php.net/manual/en/ffi.configuration.php).
  The queue worker is CLI, so it would work there with the default setting. The web SAPI would need
  `ffi.enable=true`, which the README warns lets "anyone who can run php on your server … call any native
  library."
- Intervention v4 lists libvips as a third driver via `intervention/image-driver-vips`, with HEIC
  supported. **[DOCUMENTED]**
  [Intervention v4 formats](https://image.intervention.io/v4/getting-started/formats). Laravel's facade
  would need a custom driver (`Image::extend('vips', …)`). **[DOCUMENTED]** Laravel Image docs.
- **Dev/CI:** Herd-lite can't load FFI. Same dead end as Imagick.

#### C. libvips CLI (`vipsthumbnail` / `vips`) via `Process` (recommended)

- **Prod:** `apt-get install -y libvips-tools`. This adds no PHP extension and leaves the web SAPI
  unchanged.
- **Behaviour:** `vips_thumbnail` interprets orientation tags "to rotate the image upright" unless
  `no_rotate` is set. It uses "shrink-on-load features available in the image load library" and "adds
  colour management". **[DOCUMENTED]**
  [vips_thumbnail](https://www.libvips.org/API/current/ctor.Image.thumbnail.html),
  [resample overview](https://www.libvips.org/API/current/libvips-resample.html)
- **Size syntax:** `WxH`, plus a trailing `>` to only shrink. **[DOCUMENTED]**
  [Using vipsthumbnail](https://www.libvips.org/API/current/using-vipsthumbnail.html)
- **Metadata:** libvips 8.15.0 added a `keep` flag to savers and deprecated `strip`. **[DOCUMENTED]**
  [libvips ChangeLog](https://github.com/libvips/libvips/blob/master/ChangeLog). Bookworm ships 8.14,
  which has only `strip`. Current `master` still accepts `strip`. It "sets 'keep' to none" and is marked
  `VIPS_ARGUMENT_DEPRECATED`. **[DOCUMENTED]**
  [foreign.c](https://github.com/libvips/libvips/blob/master/libvips/foreign/foreign.c). So `[strip]` is
  the spelling that works on bookworm, on trixie, and on Homebrew.
- **Colour:** iPhone photos carry a Display P3 profile. Stripping all metadata without converting would
  drop the profile and shift colours. So export to sRGB: `-e srgb` / `--export-profile` in 8.14. Master
  renamed the option `--output-profile` but keeps `export-profile` and `-e` as hidden aliases.
  **[DOCUMENTED]** `tools/vipsthumbnail.c` at
  [v8.14.1](https://github.com/libvips/libvips/blob/v8.14.1/tools/vipsthumbnail.c) and
  [master](https://github.com/libvips/libvips/blob/master/tools/vipsthumbnail.c). On master, the ICC
  profile is kept "by default when a user profile has been set". **[DOCUMENTED]** `foreign.c`.
- **One command that should work on 8.14 through current:**

  ```bash
  vipsthumbnail original.heic --size '2048x2048>' -e srgb -o 'web.jpg[Q=82,strip]'
  vipsthumbnail original.heic --size '512x512>'   -e srgb -o 'thumb.jpg[Q=80,strip]'
  ```

  Each piece is documented, but I have not run this combination against a real iPhone HEIC on both
  8.14 and 8.18. **[UNVERIFIED]** That is the first spike task.

- **Security note:** libvips master now tags `heifload` "as UNTRUSTED for libheif before 1.23.2".
  **[DOCUMENTED]** ChangeLog. Bookworm's 8.14 predates that tagging and gets Debian security patches to
  libheif 1.15.1. The only uploaders are two authenticated, trusted users, so I accept that risk.
- **Dev:** Homebrew `vips` is 8.18.7 and depends on `libheif` 1.23.5. **[DOCUMENTED]**
  [formulae.brew.sh/formula/vips](https://formulae.brew.sh/formula/vips). A static PHP binary can shell
  out to it. Add `brew install vips` to `docs/development-setup.md` (mise can't install it).
- **CI:** `.github/workflows/pr.yml` uses `ubuntu-latest` + `setup-php`. **[REPO]** Add an
  `apt-get install -y libvips-tools` step. I couldn't load Ubuntu's package page (503), so the noble version
  is **[UNVERIFIED]**. `strip` and `-e` should work on any 8.x.
- **Tests:** feature tests use `Process::fake()` and assert the command. One integration test runs the
  real binary on a small HEIC fixture and is skipped when `vipsthumbnail` is not on `PATH`, so a machine
  without vips stays green.

#### D. `heif-convert` + GD

`heif-convert` decodes HEIC to JPEG, then GD resizes and re-encodes. GD never writes EXIF. This is two
tools instead of one, and GD has no colour management, so P3 photos would render with the wrong colours
unless the profile is handled. GD also holds the full bitmap inside PHP's 256M `memory_limit`. Newer
libheif releases also changed the example tool names (`heif-dec`), so dev/prod naming could drift.
**[UNVERIFIED]** for the rename. No advantage over C.

#### E. Convert in the browser

The existing [image-resizer](https://davidharting.github.io/image-resizer/) does this for public notes
(`docs/projects/laravel-media-library-and-public-bucket.md`). It doesn't fit here. We must keep the
original anyway, canvas decoding of HEIC depends on Safari, and two uploads per photo double the
client-side work. Rejected.

---

## 3. Processing: where it runs and what the user sees

- Laravel's own docs advise: "Image manipulation can be CPU and memory-intensive. Consider performing
  large image processing workloads on a queued job." **[DOCUMENTED]** Laravel Image docs.
- The web service has ~1 FrankenPHP thread and a 30-second Octane `max_execution_time`. **[REPO]** Ten
  synchronous conversions in a request would block the site and risk the timeout.
- The web and worker containers do not share a disk. **[DOCUMENTED]** Render disks. Livewire's
  temporary upload already lands in R2 (section 4), so the flow is:

  1. **Save step (web, fast):** move the Livewire temp object to
     `memories/{memory}/photos/{uuid}/original.{ext}` on `private` with a server-side copy. Create a
     `memory_photos` row with `status = processing`. Dispatch `ProcessMemoryPhoto`.
  2. **Job (worker):** stream the original to a local temp file, run `vipsthumbnail` twice, put `web.jpg`
     and `thumb.jpg` beside the original, record width and height, and set `status = ready`. Delete the
     temp files in `finally`. On final failure, set `status = failed`. The original is still safe, so a
     retry or an artisan command can re-run it.
  3. One job per photo, so a single bad file fails alone. Set the job `$timeout` well below the queue's
     `retry_after` of 90. **[REPO]**

- **What the user sees:** right after picking files, show a client-side preview from
  `URL.createObjectURL(file)`. Safari can display HEIC, and both users are on iPhones (that Safari
  displays HEIC is **[UNVERIFIED]** here, but it is the iPhone default). Livewire won't preview HEIC
  itself, because `heic` is not in its default `preview_mimes`. **[DOCUMENTED]**
  [livewire config](https://github.com/livewire/livewire/blob/v4.3.3/config/livewire.php). After the
  save, a photo in `processing` renders a placeholder with "Processing…". The component `wire:poll`s only
  while any photo is still processing. With the database queue and a one-process worker, a batch of ten
  should clear in seconds. **[UNVERIFIED]** That needs timing.
- **Keep the original's EXIF in the original.** It carries the capture date and location, which might
  feed "taken on" later. Only the web and thumb copies are stripped.

---

## 4. Upload limits: request size, timeouts, and whether we need presigned uploads

### The proxied path (what dev uses, and prod if we changed the disk)

- **Render:** "Render web services allow HTTP responses to take up to 100 minutes." **[DOCUMENTED]**
  [Render vs Vercel](https://render.com/docs/render-vs-vercel-comparison). I found no request-body limit in
  Render's docs, only a community-forum answer saying there is none. **[UNVERIFIED]**
- **Caddy 27 MB / PHP 25 MB per file / 27 MB per POST.** **[REPO]**
- **Livewire's `multiple` sends every selected file in one POST** (`files[]` appended to one `FormData`).
  **[DOCUMENTED]** `handleSignedUrl()` in
  [supportFileUploads.js v4.3.3](https://github.com/livewire/livewire/blob/v4.3.3/js/features/supportFileUploads.js).
  Ten 3–8 MB photos make a 30–80 MB request, so `<input multiple wire:model>` would 413 at Caddy.
  Uploading one file per request (below) keeps each under 27 MB.

### What production actually does: direct-to-R2

- Livewire's temporary upload disk is `config('livewire.temporary_file_upload.disk') ?: config('filesystems.default')`.
  `isUsingS3()` is true when that disk's driver is `s3`. **[DOCUMENTED]**
  [FileUploadConfiguration.php v4.3.3](https://github.com/livewire/livewire/blob/v4.3.3/src/Features/SupportFileUploads/FileUploadConfiguration.php).
  Our default is `private`, which is `r2-private` (`s3`) in prod and previews. **[REPO]** So **Livewire
  already presigns a PUT straight to R2**, and photo bytes never pass through Render, Caddy or PHP.
  **[DOCUMENTED]**
  [GenerateSignedUploadUrl.php](https://github.com/livewire/livewire/blob/v4.3.3/src/Features/SupportFileUploads/GenerateSignedUploadUrl.php)
- **In S3 mode, `multiple` throws.** `_startUpload` does
  `throw_if($isMultiple, S3DoesntSupportMultipleFileUploads::class)`. **[DOCUMENTED]**
  [WithFileUploads.php v4.3.3](https://github.com/livewire/livewire/blob/v4.3.3/src/Features/SupportFileUploads/WithFileUploads.php).
  So the editor must call `$wire.upload('photos.N', file, …)` once per file from a small Alpine loop. That
  is also right for dev's proxied path, and it gives per-photo progress through `livewire-upload-progress`.
  **[DOCUMENTED]** [Livewire uploads](https://livewire.laravel.com/docs/4.x/uploads)
- **No chunking needed.** A single PUT carries an 8 MB photo. v4.3.3 has no chunking config at all;
  `main` has since added it. **[DOCUMENTED]** config at
  [v4.3.3](https://github.com/livewire/livewire/blob/v4.3.3/config/livewire.php) vs
  [main](https://github.com/livewire/livewire/blob/main/config/livewire.php)
- **Raise the temp-upload rule.** The default is `['required', 'file', 'max:12288']` (12 MB), and
  `max_upload_time` is 5 minutes. **[DOCUMENTED]** Livewire config. Set it to something like `max:30720`
  with `mimes:jpg,jpeg,png,webp,heic,heif`. Higher-resolution iPhone HEICs can exceed 12 MB.
  **[UNVERIFIED]** for exact sizes.
- **R2 CORS is required for browser PUTs:** "you still need to configure CORS when making requests from a
  browser." The example rule allows `PUT` with `AllowedHeaders: ["Content-Type"]`. **[DOCUMENTED]**
  [R2 CORS](https://developers.cloudflare.com/r2/buckets/cors/). Origins: `https://davidharting.com` on the
  prod bucket. Preview origins (`https://davidhartingdotcom-web-pr-*.onrender.com`) go on the staging
  bucket (`R2_PRIVATE_BUCKET` comes from the per-environment secrets). Whether R2 CORS accepts a wildcard
  in the middle of an origin is **[UNVERIFIED]**.
- **The `x-amz-acl` header:** Livewire signs `'ACL' => 'private'` into the PUT. **[DOCUMENTED]**
  `GenerateSignedUploadUrl::forS3`. R2 marks `x-amz-acl` on PutObject as unsupported (❌).
  **[DOCUMENTED]** [R2 S3 API compatibility](https://developers.cloudflare.com/r2/api/s3/api/). Whether R2
  ignores or rejects it, and whether CORS must allow the header, is **[UNVERIFIED]**. If Filament
  uploads already work in prod, that settles it. Otherwise the spike must check.
- **Temp cleanup:** with an S3 temp disk, Livewire relies on a bucket lifecycle rule
  (`php artisan livewire:configure-s3-upload-cleanup`, 24 h). **[DOCUMENTED]** Livewire uploads docs.
  Whether that command's S3 API call works against R2 is **[UNVERIFIED]**. An R2 dashboard lifecycle rule
  on the `livewire-tmp/` prefix does the same job.

---

## 5. Storage and private serving

All three files live on the `private` disk: `original.*`, `web.jpg`, `thumb.jpg`.

### Option 1: Presigned R2 GET URLs

- Expiry from 1 second to 7 days. "Anyone with the URL can perform the specified operation until it
  expires." Presigned URLs "work with the S3 API domain (`<ACCOUNT_ID>.r2.cloudflarestorage.com`) and
  cannot be used with custom domains." **[DOCUMENTED]**
  [R2 presigned URLs](https://developers.cloudflare.com/r2/api/s3/presigned-urls/)
- **Cost:** egress is free. Class B reads cost $0.36 per million, with 10 million free per month.
  **[DOCUMENTED]** [R2 pricing](https://developers.cloudflare.com/r2/pricing/). For two people, the cost is
  zero.
- **Caching:** the S3 API domain isn't behind a custom-domain cache. Every newly signed URL is a
  different URL, so the browser re-downloads after each re-sign. Laravel passes extra S3 params such as
  `ResponseContentType` / `ResponseContentDisposition` through `temporaryUrl()`. **[DOCUMENTED]**
  [Laravel filesystem: temporary URLs](https://laravel.com/docs/13.x/filesystem#temporary-urls).
  `ResponseCacheControl` is a standard GetObject parameter, so the bytes can carry
  `private, max-age=…`. At ~10 × few-hundred-KB per page view, re-downloads don't matter.
- **Leakage:** the URL is a bearer token until it expires. It would end up in page HTML and history, and
  a copied link keeps working for the expiry window.
- **Server load:** none. Bytes go R2 → browser.

### Option 2: Stream through a Laravel route

- `Storage::disk('private')->response($path)` behind the policy. Stable URLs, and the policy runs on
  every request. Nothing leaks, because the URL is useless without a session. Browser caching works well
  with `Cache-Control: private` and stable URLs.
- **Cost:** every byte goes R2 → Render → browser on the web service, which has about **one** FrankenPHP
  thread on the starter plan. **[REPO]** A memory with 10 photos means 10 concurrent requests that each
  hold the thread for an R2 round-trip plus transfer. Page loads stall, and the rest of the site with
  them.

### Option 3: Authorizing route that redirects to a presigned URL (recommended)

`GET /memories/photos/{photo}/{variant}` (`web` | `thumb` | `original`) runs the same policy as the
Blade views and returns 404 to anyone but David and Katie. It then `redirect()`s to
`temporaryUrl($path, now()->addMinutes(10))`. For `original`, add
`ResponseContentDisposition: attachment` so it downloads.

- Markup holds only stable app URLs, and authorization happens on every view, as in option 2.
- Each request is a cheap 302, so the PHP thread is never tied up with bytes, as in option 1.
- A leaked presigned URL is short-lived and names a single object. The leakage surface is 10 minutes,
  not "until the HTML is gone".
- This extends what `FileShareController::show` already does. **[REPO]**
- In dev, the local `private` disk has `serve => true`, so `temporaryUrl()` returns Laravel's own signed
  local URL and the same code path works with no R2. **[REPO]** +
  **[DOCUMENTED]** Laravel filesystem docs.

---

## Recommendation and trade-offs

**Recommended pipeline**

1. Dockerfile: `apt-get install -y libvips-tools`. Optionally move to the `php8.4-trixie` base later.
   Dev: `brew install vips`. CI: `apt-get install -y libvips-tools`.
2. Editor: an Alpine loop calls `$wire.upload()` once per file (≤ 10), directly to R2 in prod. Show an
   `objectURL` preview and per-file progress. Validate `mimes:jpg,jpeg,png,webp,heic,heif` and raise
   Livewire's temp rule to ~30 MB. Add an R2 CORS `PUT` rule for the prod and staging buckets.
3. On save, move the temp object to `…/original.ext` on `private`, insert a `processing` row, and dispatch
   one `ProcessMemoryPhoto` job per photo.
4. Worker: `vipsthumbnail … -e srgb -o 'web.jpg[Q=82,strip]'` at 2048 px and the same at 512 px, written
   beside the original. Mark the row `ready` (or `failed` after retries). The UI polls while anything is
   processing.
5. Serve through a policy-checked route that 302s to a 10-minute presigned URL.

**Trade-offs we accept**

| Choice                         | Gain                                                                                       | Cost                                                                                                                                                      |
| ------------------------------ | ------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| vips **CLI** over an extension | Works with Herd-lite and FrankenPHP's own binary; no FFI/Imagick thread issues; low memory | Shelling out and temp files; dev (8.18) and prod (8.14) versions differ, so pin flags to ones valid on both (`strip`, `-e`); not Laravel's `Image` facade |
| vips over Imagick              | Colour management + strip + autorotate in one command; no OpenMP/FrankenPHP crash class    | Forgo Laravel 13.20's `Image` facade, which needs a framework upgrade and doesn't strip with Imagick anyway                                               |
| Queue over synchronous         | Web thread stays free; failures are per-photo and retryable                                | A "processing" state and polling in the UI; the photo isn't viewable for a few seconds after save                                                         |
| Direct-to-R2 temp uploads      | No body-size or timeout limits on Render, Caddy or PHP; already how prod behaves           | One request per file (Livewire S3 mode rejects `multiple`); R2 CORS config outside `render.yaml`; dev takes a different (proxied) path                    |
| Redirect to presigned URL      | Stable markup, policy on every view, no byte streaming on the single thread                | A presigned URL is briefly a bearer token; re-signed URLs defeat browser caching (irrelevant at this scale)                                               |
| JPEG web copy                  | Universal, simple, matches `strip`/sRGB pipeline                                           | WebP/AVIF would be smaller; easy to switch later since originals are kept                                                                                 |

**Spike before building (the [UNVERIFIED] items that matter)**

1. Run the two `vipsthumbnail` commands on a real iPhone HEIC on bookworm 8.14 (Docker) and Homebrew
   8.18. Check orientation, colour, dimensions, and that `exiftool` shows no EXIF/GPS.
2. Confirm a Livewire S3-mode PUT to the `r2-private` bucket works: the `x-amz-acl` header, the CORS rule,
   and the preview-origin wildcard.
3. Check what `fileinfo` reports for an iPhone HEIC and whether `mimes:heic,heif` passes.
4. Time ten photos through the worker on the starter plan.

## Sources

Repo (at `origin/main` `120aca8`): `Dockerfile`, `Caddyfile`, `render.yaml`, `config/filesystems.php`,
`config/octane.php`, `config/queue.php`, `app/Http/Controllers/FileShareController.php`, `mise.toml`,
`pitchfork.toml`, `.github/workflows/pr.yml`, `composer.lock`,
`docs/projects/laravel-media-library-and-public-bucket.md`.

Render:

- Persistent disks / ephemeral filesystem: <https://render.com/docs/disks>
- 100-minute HTTP responses: <https://render.com/docs/render-vs-vercel-comparison>

FrankenPHP / Caddy:

- Known issues (Imagick/OpenMP): <https://frankenphp.dev/docs/known-issues/>
- `request_body`: <https://caddyserver.com/docs/caddyfile/directives/request_body>

Laravel / Livewire / Intervention:

- Image manipulation (13.x): <https://laravel.com/docs/13.x/images>
- Framework changelog (Image API in v13.20.0): <https://github.com/laravel/framework/blob/13.x/CHANGELOG.md>
- `ImagickDriver`: <https://github.com/laravel/framework/blob/13.x/src/Illuminate/Image/Drivers/ImagickDriver.php>
- `validateImage` (v13.18.1): <https://github.com/laravel/framework/blob/v13.18.1/src/Illuminate/Validation/Concerns/ValidatesAttributes.php>
- File storage / temporary URLs: <https://laravel.com/docs/13.x/filesystem>
- Livewire uploads: <https://livewire.laravel.com/docs/4.x/uploads>
- Livewire v4.3.3 source: `config/livewire.php`, `js/features/supportFileUploads.js`,
  `src/Features/SupportFileUploads/{WithFileUploads,FileUploadConfiguration,GenerateSignedUploadUrl}.php`
  at <https://github.com/livewire/livewire/tree/v4.3.3>
- Intervention Image v4 formats: <https://image.intervention.io/v4/getting-started/formats>
- Intervention Image v4 configuration: <https://image.intervention.io/v4/getting-started/configuration-drivers>

libvips / libheif / PHP:

- `vips_thumbnail`: <https://www.libvips.org/API/current/ctor.Image.thumbnail.html>
- Using vipsthumbnail: <https://www.libvips.org/API/current/using-vipsthumbnail.html>
- ChangeLog: <https://github.com/libvips/libvips/blob/master/ChangeLog>
- `foreign.c` (strip → keep): <https://github.com/libvips/libvips/blob/master/libvips/foreign/foreign.c>
- `vipsthumbnail.c` v8.14.1 / master: <https://github.com/libvips/libvips/blob/v8.14.1/tools/vipsthumbnail.c>,
  <https://github.com/libvips/libvips/blob/master/tools/vipsthumbnail.c>
- php-vips: <https://github.com/libvips/php-vips>
- PHP FFI configuration: <https://www.php.net/manual/en/ffi.configuration.php>
- Debian packages: <https://packages.debian.org/bookworm/libheif1>, <https://packages.debian.org/bookworm/libvips42>,
  <https://packages.debian.org/bookworm/amd64/libvips-tools/filelist>,
  <https://packages.debian.org/bookworm/libmagickcore-6.q16-6>,
  <https://packages.debian.org/bookworm/amd64/libheif-examples/filelist>,
  <https://packages.debian.org/trixie/libvips42t64>, <https://packages.debian.org/trixie/libheif1>
- Homebrew vips: <https://formulae.brew.sh/formula/vips>

Cloudflare R2:

- Presigned URLs: <https://developers.cloudflare.com/r2/api/s3/presigned-urls/>
- CORS: <https://developers.cloudflare.com/r2/buckets/cors/>
- S3 API compatibility: <https://developers.cloudflare.com/r2/api/s3/api/>
- Pricing: <https://developers.cloudflare.com/r2/pricing/>

WebKit:

- Bug 212489 (macOS HEIF transcoding on file input): <https://bugs.webkit.org/show_bug.cgi?id=212489>
- Safari 27.0 features (HEIC no longer converted to JPEG): <https://webkit.org/blog/18325/webkit-features-for-safari-27-0/>
