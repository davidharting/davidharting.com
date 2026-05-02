<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Facade;
use Prism\Prism\Prism;
use Prism\Prism\PrismManager;
use Prism\Prism\Enums\Provider;
use Prism\Prism\ValueObjects\ProviderTool;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------

$apiKey = getenv('ANTHROPIC_API_KEY');
if (! $apiKey) {
    fwrite(STDERR, "ERROR: Set ANTHROPIC_API_KEY in environment.\n");
    exit(1);
}

$model = 'claude-opus-4-5'; // adjust to the model you're seeing the bug on
$logFile = __DIR__ . '/prism-outbound.log';

// Reset the log so each run is clean
file_put_contents($logFile, "=== Prism repro run: " . date('c') . " ===\n\n");

// ---------------------------------------------------------------------------
// Bootstrap a minimal Laravel-compatible container so Prism resolves correctly
// ---------------------------------------------------------------------------

$app = new Container();
Container::setInstance($app);
Facade::setFacadeApplication($app);

// Config — required by PrismManager::getConfig()
$app->singleton('config', fn () => new \Illuminate\Config\Repository([
    'prism' => [
        'providers' => [
            'anthropic' => [
                'api_key' => $apiKey,
                'url' => 'https://api.anthropic.com/v1',
                'version' => '2023-06-01',
            ],
        ],
    ],
]));

// HTTP client factory — required by the Http facade used in InitializesClient
$app->singleton(HttpFactory::class, fn () => new HttpFactory());

// PrismManager — its constructor requires Application (a Laravel interface);
// we bypass that with an anonymous subclass since $this->app is never accessed
// when using built-in providers.
$app->singleton(PrismManager::class, function () {
    return new class extends PrismManager {
        public function __construct() {}
    };
});

// ---------------------------------------------------------------------------
// Guzzle middleware: dump outbound request body to a file
// ---------------------------------------------------------------------------

$stack = HandlerStack::create();

$stack->push(Middleware::tap(
    function ($request, $options) use ($logFile) {
        $body = (string) $request->getBody();
        $request->getBody()->rewind(); // critical — body is a stream

        file_put_contents(
            $logFile,
            "--- OUTBOUND REQUEST ---\n"
            . $request->getMethod() . ' ' . $request->getUri() . "\n"
            . "Body:\n"
            . json_encode(json_decode($body, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            . "\n\n",
            FILE_APPEND
        );
    },
    function ($request, $options, $promise) use ($logFile) {
        $promise->then(function ($response) use ($logFile) {
            $body = (string) $response->getBody();
            $response->getBody()->rewind();

            file_put_contents(
                $logFile,
                "--- INBOUND RESPONSE [" . $response->getStatusCode() . "] ---\n"
                . substr($body, 0, 4000) // truncate huge responses
                . "\n\n",
                FILE_APPEND
            );
        });
    }
));

// ---------------------------------------------------------------------------
// Turn 1: prompt that forces web_search
// ---------------------------------------------------------------------------

echo "=== Turn 1: forcing web_search ===\n";

try {
    $turn1 = Prism::text()
        ->using(Provider::Anthropic, $model)
        ->withProviderTools([new ProviderTool('web_search')])
        ->withMaxSteps(5)
        ->withClientOptions(['handler' => $stack])
        ->withPrompt(
            'Use the web_search tool to find one recent news headline from today. '
            . 'Quote the headline and cite the source.'
        )
        ->asText();

    echo "Turn 1 succeeded.\n";
    echo "Text length: " . strlen($turn1->text) . "\n";
    echo "additionalContent keys: " . count($turn1->additionalContent ?? []) . "\n\n";

    // Dump the parsed response structure so we can see how Prism interpreted it
    file_put_contents(
        __DIR__ . '/turn1-parsed.json',
        json_encode([
            'text' => $turn1->text,
            'additionalContent' => $turn1->additionalContent,
            'finishReason' => $turn1->finishReason->name ?? null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
} catch (\Throwable $e) {
    fwrite(STDERR, "Turn 1 FAILED: " . $e->getMessage() . "\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// Turn 2: replay turn 1 + send a follow-up — this is where it should break
// ---------------------------------------------------------------------------

echo "=== Turn 2: replaying assistant message + follow-up ===\n";

try {
    $turn2 = Prism::text()
        ->using(Provider::Anthropic, $model)
        ->withProviderTools([new ProviderTool('web_search')])
        ->withMaxSteps(5)
        ->withClientOptions(['handler' => $stack])
        ->withMessages([
            new UserMessage(
                'Use the web_search tool to find one recent news headline from today. '
                . 'Quote the headline and cite the source.'
            ),
            new AssistantMessage(
                $turn1->text,
                [], // toolCalls
                $turn1->additionalContent ?? []
            ),
            new UserMessage('Summarize that headline in one short sentence.'),
        ])
        ->asText();

    echo "Turn 2 SUCCEEDED — bug did not reproduce.\n";
    echo "Response: " . $turn2->text . "\n";
} catch (\Throwable $e) {
    echo "Turn 2 FAILED (expected):\n";
    echo "  " . $e->getMessage() . "\n\n";
    echo "Outbound payload logged to: $logFile\n";
    echo "Inspect the second OUTBOUND REQUEST block — the 'messages' array there\n";
    echo "is what Prism sent and what Anthropic rejected.\n";
}
