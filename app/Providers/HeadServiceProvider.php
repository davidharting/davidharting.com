<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Head\Facades\Head;
use Laravel\Head\HeadBuilder;

/**
 * Site-wide document <head> defaults.
 *
 * These are the tags the app layout previously hard-coded on every page.
 * Page-specific title and description are still set by the layout component,
 * which overrides these defaults field by field.
 */
class HeadServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Head::defaults(function (HeadBuilder $head) {
            $head
                ->viewport('width=device-width, initial-scale=1')
                ->themeColor('#1a1a2e')
                ->appleWebAppTitle('David Harting')
                ->appleWebAppStatusBarStyle('black')
                ->meta('apple-mobile-web-app-capable', 'yes')
                ->manifest('/manifest.json')
                ->appleTouchIcon('/icons/apple-touch-icon.png')
                ->feed(
                    url(config('feed.feeds.main.url')),
                    title: config('feed.feeds.main.title'),
                    type: config('feed.feeds.main.format'),
                );
        });
    }
}
