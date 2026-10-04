<?php

namespace App\Jobs;

use finfo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Throwaway spike for the "Spike the photo pipeline" ticket (#249).
 * Runs the researched vipsthumbnail commands and records what happened.
 */
class ProcessSpikePhoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public string $id, public string $originalPath) {}

    public function handle(): void
    {
        $disk = Storage::disk('private');
        $workDir = sys_get_temp_dir().'/spike-'.Str::random(8);
        mkdir($workDir);

        $result = ['vips_version' => trim(Process::run(['vips', '--version'])->output())];

        try {
            $original = $workDir.'/original.'.pathinfo($this->originalPath, PATHINFO_EXTENSION);
            file_put_contents($original, $disk->get($this->originalPath));

            $result['original'] = [
                'bytes' => filesize($original),
                'fileinfo_mime' => (new finfo(FILEINFO_MIME_TYPE))->file($original),
                'vipsheader' => $this->header($original),
            ];

            foreach (['web' => ['2048x2048>', 82], 'thumb' => ['512x512>', 80]] as $variant => [$size, $quality]) {
                $output = "{$workDir}/{$variant}.jpg";
                $started = microtime(true);
                $process = Process::timeout(45)->run([
                    'vipsthumbnail', $original,
                    '--size', $size,
                    '-e', 'srgb',
                    '-o', "{$output}[Q={$quality},strip]",
                ]);

                $result[$variant] = [
                    'exit_code' => $process->exitCode(),
                    'stderr' => trim($process->errorOutput()),
                    'seconds' => round(microtime(true) - $started, 2),
                ];

                if ($process->successful()) {
                    $disk->put("spike/{$this->id}/{$variant}.jpg", file_get_contents($output));
                    $result[$variant]['bytes'] = filesize($output);
                    $result[$variant]['vipsheader'] = $this->header($output);
                }
            }
        } catch (Throwable $exception) {
            $result['exception'] = $exception->getMessage();
        } finally {
            Process::run(['rm', '-rf', $workDir]);
            $disk->put("spike/{$this->id}/result.json", json_encode($result, JSON_PRETTY_PRINT));
        }
    }

    /**
     * The vipsheader fields that show whether metadata survived: size, orientation, camera, GPS and colour profile.
     *
     * @return array{summary: string, exif_field_count: int, interesting: array<int, string>}
     */
    private function header(string $path): array
    {
        $lines = collect(explode("\n", Process::run(['vipsheader', '-a', $path])->output()))
            ->map(fn (string $line): string => trim($line))
            ->filter();

        return [
            'summary' => Str::after((string) $lines->first(), ': '),
            'exif_field_count' => $lines->filter(fn (string $line): bool => str_starts_with($line, 'exif-'))->count(),
            'interesting' => $lines
                ->filter(fn (string $line): bool => (bool) preg_match('/^(exif-ifd0-(Orientation|Make|Model)|exif-ifd3-|icc-profile-data|orientation|interpretation)/', $line))
                ->map(fn (string $line): string => Str::limit($line, 120))
                ->values()
                ->all(),
        ];
    }
}
