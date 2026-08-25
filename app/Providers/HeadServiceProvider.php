<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Head\Enums\OgType;
use Laravel\Head\Enums\TwitterCard;
use Laravel\Head\ErrorPages;
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
                ->pwa(
                    name: self::SITE_NAME,
                    manifest: '/manifest.json',
                    themeColor: '#1a1a2e',
                    appleTouchIcon: '/icons/apple-touch-icon.png',
                    appleWebAppStatusBarStyle: 'black',
                )
                ->feed(
                    url(config('feed.feeds.main.url')),
                    title: config('feed.feeds.main.title'),
                    type: config('feed.feeds.main.format'),
                );
        });

        Head::errors(function (ErrorPages $errors) {
            $errors->defaults(robots: 'noindex, follow');

            $errors->status(404, fn (HeadBuilder $head) => $head
                ->title('Page Not Found')
                ->description('The page you are looking for could not be found.'));
        });
    }
}
