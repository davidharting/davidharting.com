<?php

use Illuminate\Log\LogManager;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Tests\TestCase;

describe('stderr-json channel', function () {
    test('uses JsonFormatter', function () {
        /** @var TestCase $this */
        $manager = $this->app->make(LogManager::class);
        $logger = $manager->channel('stderr-json');

        $handlers = $logger->getHandlers();
        expect($handlers)->toHaveCount(1);

        $handler = $handlers[0];
        expect($handler)->toBeInstanceOf(StreamHandler::class);
        expect($handler->getFormatter())->toBeInstanceOf(JsonFormatter::class);
    });

    test('produces valid JSON per line', function () {
        /** @var TestCase $this */
        $stream = fopen('php://memory', 'rw');

        $manager = $this->app->make(LogManager::class);
        $logger = $manager->channel('stderr-json');

        // Swap the handler's stream to an in-memory buffer
        $handler = $logger->getHandlers()[0];
        $originalStream = (function () {
            return $this->stream;
        })->call($handler);
        (function ($s) {
            $this->stream = $s;
        })->call($handler, $stream);

        $logger->info('Test message', ['key' => 'value']);
        $logger->warning('Test warning');
        $logger->error('Test error', ['code' => 500]);

        rewind($stream);
        $output = stream_get_contents($stream);

        // Restore original stream
        (function ($s) {
            $this->stream = $s;
        })->call($handler, $originalStream);
        fclose($stream);

        $lines = array_filter(explode("\n", trim($output)));
        expect($lines)->toHaveCount(3);

        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            expect($decoded)->toBeArray("Line is not valid JSON: {$line}");
            expect($decoded)->toHaveKey('message');
            expect($decoded)->toHaveKey('level_name');
            expect($decoded)->toHaveKey('datetime');
            expect($decoded)->toHaveKey('channel');
        }
    });

    test('includes stack traces for exceptions', function () {
        /** @var TestCase $this */
        $stream = fopen('php://memory', 'rw');

        $manager = $this->app->make(LogManager::class);
        $logger = $manager->channel('stderr-json');

        $handler = $logger->getHandlers()[0];
        $originalStream = (function () {
            return $this->stream;
        })->call($handler);
        (function ($s) {
            $this->stream = $s;
        })->call($handler, $stream);

        try {
            throw new RuntimeException('Test exception');
        } catch (Throwable $e) {
            $logger->error('Caught', ['exception' => $e]);
        }

        rewind($stream);
        $output = stream_get_contents($stream);

        (function ($s) {
            $this->stream = $s;
        })->call($handler, $originalStream);
        fclose($stream);

        $decoded = json_decode(trim($output), true);
        expect($decoded['context']['exception'])->toHaveKey('class');
        expect($decoded['context']['exception'])->toHaveKey('trace');
        expect($decoded['context']['exception']['class'])->toBe('RuntimeException');
    });

    test('PHP errors converted to ErrorException log as JSON', function () {
        /** @var TestCase $this */
        $stream = fopen('php://memory', 'rw');

        $manager = $this->app->make(LogManager::class);
        $logger = $manager->channel('stderr-json');
        $handler = $logger->getHandlers()[0];
        $originalStream = (function () {
            return $this->stream;
        })->call($handler);
        (function ($s) {
            $this->stream = $s;
        })->call($handler, $stream);

        // HandleExceptions converts PHP warnings to ErrorException.
        // Simulate that by catching the exception and logging it as the
        // error handler would before it reaches the exception handler.
        try {
            trigger_error('Test PHP warning', E_USER_WARNING);
        } catch (ErrorException $e) {
            $logger->warning($e->getMessage(), ['exception' => $e]);
        }

        rewind($stream);
        $output = stream_get_contents($stream);
        (function ($s) {
            $this->stream = $s;
        })->call($handler, $originalStream);
        fclose($stream);

        $decoded = json_decode(trim($output), true);
        expect($decoded)->toBeArray('PHP error logged as non-JSON');
        expect($decoded)->toHaveKey('message');
        expect($decoded['level_name'])->toBe('WARNING');
        expect($decoded['context']['exception']['class'])->toBe('ErrorException');
    });
});
