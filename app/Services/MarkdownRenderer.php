<?php

namespace App\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Tempest\Highlight\CommonMark\CodeBlockRenderer;
use Tempest\Highlight\CommonMark\InlineCodeBlockRenderer;

/**
 * Renders markdown with server-side syntax highlighting using Tempest Highlight.
 *
 * Usage:
 *   $renderer = new MarkdownRenderer();
 *   $html = $renderer->render($markdown);
 *
 * Requires: composer require tempest/highlight:^1.0
 *
 * CSS: Include one of the themes from vendor/tempest/highlight/src/Themes/
 * or use the InlineTheme for inline styles.
 */
class MarkdownRenderer
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => true,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        // Register Tempest Highlight renderers
        $environment->addRenderer(FencedCode::class, new CodeBlockRenderer(), 10);
        $environment->addRenderer(IndentedCode::class, new CodeBlockRenderer(), 10);
        $environment->addRenderer(Code::class, new InlineCodeBlockRenderer(), 10);

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
