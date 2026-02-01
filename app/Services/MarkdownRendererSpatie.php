<?php

namespace App\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Spatie\CommonMarkHighlighter\FencedCodeRenderer;
use Spatie\CommonMarkHighlighter\IndentedCodeRenderer;

/**
 * Renders markdown with server-side syntax highlighting using Spatie/highlight.php.
 *
 * Usage:
 *   $renderer = new MarkdownRendererSpatie();
 *   $html = $renderer->render($markdown);
 *
 * Requires: composer require spatie/commonmark-highlighter
 *
 * CSS: Include a highlight.js theme, e.g.:
 *   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
 *
 * Or copy from vendor/scrivo/highlight.php/styles/
 */
class MarkdownRendererSpatie
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        // Common languages for auto-detection (optional, improves accuracy)
        $languages = ['php', 'javascript', 'typescript', 'html', 'css', 'json', 'bash', 'sql'];

        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => true,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        // Register Spatie Highlighter renderers
        $environment->addRenderer(FencedCode::class, new FencedCodeRenderer($languages), 10);
        $environment->addRenderer(IndentedCode::class, new IndentedCodeRenderer($languages), 10);

        $this->converter = new MarkdownConverter($environment);
    }

    public function render(?string $markdown): ?string
    {
        if (! $markdown) {
            return null;
        }

        return $this->converter->convert($markdown)->getContent();
    }
}
