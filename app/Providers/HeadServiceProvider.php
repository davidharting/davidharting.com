<?php

namespace App\Providers;

use App\Support\DeploymentInfo;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Head\Enums\OgType;
use Laravel\Head\Enums\TwitterCard;
use Laravel\Head\Facades\Head;
use Laravel\Head\HeadBuilder;

/**
 * Site-wide document <head> defaults.
 *
 * Everything registered here is the lowest-priority layer. Route metadata
 * (routes/web.php) and runtime metadata (controllers) override it field by
 * field.
 */
class HeadServiceProvider extends ServiceProvider
{
    private const SITE_NAME = 'David Harting';

    private const SITE_MANIFEST = '/manifest.json';

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Head::defaults(function (HeadBuilder $head) {
            $head
                ->title("David Harting's Website", suffix: ' - davidharting.com')
                ->description("David's Corner of the Internet")
                ->canonical()
                ->searchableByRobots()
                ->viewport('width=device-width, initial-scale=1')
                ->og(type: OgType::Website, siteName: self::SITE_NAME, locale: 'en_US')
                ->ogImage(url('/headshot.jpg'), alt: 'David Harting')
                ->twitter(card: TwitterCard::Summary)
                // No manifest here on purpose: see the fallback composer below.
                ->applicationName(self::SITE_NAME)
                ->appleWebAppTitle(self::SITE_NAME)
                ->webAppCapable()
                ->appleTouchIcon('/icons/apple-touch-icon.png', sizes: '180x180')
                ->appleWebAppStatusBarStyle('default')
                ->themeColor('#f8f1e3', media: '(prefers-color-scheme: light)')
                ->themeColor('#21110a', media: '(prefers-color-scheme: dark)')
                ->feed(
                    url(config('feed.feeds.main.url')),
                    title: config('feed.feeds.main.title'),
                    type: config('feed.feeds.main.format'),
                );
        });

        // Links stack rather than override, so a manifest default would sit
        // beside a route's own manifest and win as the first one in the head.
        // Instead the site manifest is the fallback for pages that declare none.
        View::composer('components.layout.app', function (): void {
            if (! self::declaresManifest()) {
                Head::manifest(self::SITE_MANIFEST);
            }
        });

        // On a PR preview, prefix every title with the PR number so preview
        // tabs are easy to tell apart from production and from each other.
        // This runs as the layout renders, after controllers have set their
        // titles, and takes the fully resolved title (suffix included) so it
        // covers default and exact titles that a Head prefix would skip.
        View::composer('components.layout.app', function (): void {
            $pullRequestNumber = DeploymentInfo::pullRequestNumber();

            if ($pullRequestNumber === null) {
                return;
            }

            $title = Head::toArray()['title'] ?? null;

            if (is_string($title)) {
                Head::title("PR #{$pullRequestNumber} · {$title}", exact: true);
            }
        });

        // Head::errors() is deliberately not registered. This app has no views
        // in resources/views/errors, so Laravel renders its own error pages,
        // which never reach @head -- the metadata would resolve and go nowhere.
        // Add an error view extending x-layout.app first, then register it.
    }

    /**
     * Whether the current page's resolved head already links a web app manifest.
     */
    private static function declaresManifest(): bool
    {
        $links = Head::toArray()['links']['generic'] ?? [];

        return collect($links)->contains('rel', 'manifest');
    }
}
