<?php

use Illuminate\Support\Facades\Log;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Tests\TestCase;

// Production logs to the "stderr-json" channel. The JSON must survive Octane:
// octane:frankenphp re-renders the FrankenPHP subprocess's stderr unless it is
// started with --log-level, and when it re-renders it reads the "msg" key. Monolog
// writes "message", so a re-rendered line collapses to the literal "unknown error".
// These tests lock the JSON contract that the --log-level=WARN passthrough relies on.

describe('stderr-json channel', function () {
    test('is configured to format records as JSON', function () {
        /** @var TestCase $this */
        $channel = config('logging.channels.stderr-json');

        expect($channel['driver'])->toBe('monolog');
        expect($channel['handler'])->toBe(StreamHandler::class);
        expect($channel['formatter'])->toBe(JsonFormatter::class);
        expect($channel['with']['stream'])->toBe('php://stderr');
    });

    test('emits a single line of valid JSON with the keys we rely on', function () {
        /** @var TestCase $this */
        $stream = fopen('php://temp', 'r+');

        $log = Log::build([
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'formatter' => JsonFormatter::class,
            'with' => ['stream' => $stream],
        ]);

        $log->error('something broke', ['foo' => 'bar']);

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        // One record must be exactly one newline-terminated line so log shippers
        // can parse it line-by-line.
        expect(trim($output))->not->toContain("\n");

        $decoded = json_decode(trim($output), true);

        expect($decoded)->toBeArray();
        expect($decoded['message'])->toBe('something broke');
        expect($decoded['level_name'])->toBe('ERROR');
        expect($decoded['context'])->toBe(['foo' => 'bar']);
    });
});
