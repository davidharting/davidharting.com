<?php

use Illuminate\Support\Facades\Storage;

function createTestImage(int $width, int $height, string $extension = 'jpg'): string
{
    $image = imagecreatetruecolor($width, $height);
    $color = imagecolorallocate($image, 255, 0, 0);
    imagefill($image, 0, 0, $color);

    $path = tempnam(sys_get_temp_dir(), 'test_img_').'.'.$extension;

    match ($extension) {
        'png' => imagepng($image, $path),
        default => imagejpeg($image, $path, 100),
    };

    imagedestroy($image);

    return $path;
}

function createTestFile(string $content, string $extension): string
{
    $path = tempnam(sys_get_temp_dir(), 'test_file_').'.'.$extension;
    file_put_contents($path, $content);

    return $path;
}

afterEach(function () {
    // Clean up any temp files left behind by test helpers
    foreach (glob(sys_get_temp_dir().'/test_img_*') as $f) {
        @unlink($f);
    }
    foreach (glob(sys_get_temp_dir().'/test_file_*') as $f) {
        @unlink($f);
    }
});

test('uploads a regular file to the disk', function () {
    Storage::fake('public');

    $file = createTestFile('hello world', 'txt');

    $this->artisan('file:upload', ['path' => $file, 'name' => 'docs/readme', '--disk' => 'public'])
        ->expectsOutputToContain('Uploaded:')
        ->assertExitCode(0);

    Storage::disk('public')->assertExists('docs/readme.txt');
    expect(Storage::disk('public')->get('docs/readme.txt'))->toBe('hello world');
});

test('uploads an image with normal and thumb versions', function () {
    Storage::fake('public');

    $file = createTestImage(4000, 3000);

    $this->artisan('file:upload', ['path' => $file, 'name' => 'photos/beach', '--disk' => 'public'])
        ->expectsOutputToContain('Normal:')
        ->expectsOutputToContain('Thumb:')
        ->assertExitCode(0);

    Storage::disk('public')->assertExists('photos/beach.jpg');
    Storage::disk('public')->assertExists('photos/beach-thumb.jpg');
});

test('normal image is resized to max 2000px', function () {
    Storage::fake('public');

    $file = createTestImage(4000, 3000);

    $this->artisan('file:upload', ['path' => $file, 'name' => 'photos/large', '--disk' => 'public']);

    $normalContent = Storage::disk('public')->get('photos/large.jpg');
    $normalTmp = tempnam(sys_get_temp_dir(), 'test_verify_').'.jpg';
    file_put_contents($normalTmp, $normalContent);

    $size = getimagesize($normalTmp);
    unlink($normalTmp);

    expect($size[0])->toBeLessThanOrEqual(2000);
    expect($size[1])->toBeLessThanOrEqual(2000);
});

test('small images are not upscaled', function () {
    Storage::fake('public');

    $file = createTestImage(800, 600);

    $this->artisan('file:upload', ['path' => $file, 'name' => 'photos/small', '--disk' => 'public']);

    $normalContent = Storage::disk('public')->get('photos/small.jpg');
    $normalTmp = tempnam(sys_get_temp_dir(), 'test_verify_').'.jpg';
    file_put_contents($normalTmp, $normalContent);

    $size = getimagesize($normalTmp);
    unlink($normalTmp);

    expect($size[0])->toBe(800);
    expect($size[1])->toBe(600);
});

test('thumbnail fits within 300x300', function () {
    Storage::fake('public');

    $file = createTestImage(4000, 3000);

    $this->artisan('file:upload', ['path' => $file, 'name' => 'photos/big', '--disk' => 'public']);

    $thumbContent = Storage::disk('public')->get('photos/big-thumb.jpg');
    $thumbTmp = tempnam(sys_get_temp_dir(), 'test_verify_').'.jpg';
    file_put_contents($thumbTmp, $thumbContent);

    $size = getimagesize($thumbTmp);
    unlink($thumbTmp);

    expect($size[0])->toBeLessThanOrEqual(300);
    expect($size[1])->toBeLessThanOrEqual(300);
});

test('fails when file does not exist', function () {
    $this->artisan('file:upload', ['path' => '/nonexistent/file.jpg', 'name' => 'nope'])
        ->expectsOutputToContain('File not found')
        ->assertExitCode(1);
});

test('defaults to public disk', function () {
    Storage::fake('public');

    $file = createTestFile('data', 'csv');

    $this->artisan('file:upload', ['path' => $file, 'name' => 'data/export'])
        ->assertExitCode(0);

    Storage::disk('public')->assertExists('data/export.csv');
});

test('supports nested directory paths in name', function () {
    Storage::fake('public');

    $file = createTestFile('nested content', 'md');

    $this->artisan('file:upload', ['path' => $file, 'name' => 'a/b/c/deep', '--disk' => 'public'])
        ->assertExitCode(0);

    Storage::disk('public')->assertExists('a/b/c/deep.md');
});

test('works with png images', function () {
    Storage::fake('public');

    $file = createTestImage(1000, 800, 'png');

    $this->artisan('file:upload', ['path' => $file, 'name' => 'photos/diagram', '--disk' => 'public'])
        ->assertExitCode(0);

    Storage::disk('public')->assertExists('photos/diagram.png');
    Storage::disk('public')->assertExists('photos/diagram-thumb.png');
});
