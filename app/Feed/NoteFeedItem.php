<?php

namespace App\Feed;

use Spatie\Feed\FeedItem;

class NoteFeedItem extends FeedItem
{
    protected string $content = '';

    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }
}
