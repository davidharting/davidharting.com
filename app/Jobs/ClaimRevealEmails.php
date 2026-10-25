<?php

namespace App\Jobs;

use App\Memories\RevealEmails;
use App\Models\Month;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Claims the reveal and late-share emails a share has made due.
 */
class ClaimRevealEmails implements ShouldQueue
{
    use Queueable;

    public function __construct(public Month $month) {}

    public function handle(RevealEmails $revealEmails): void
    {
        $revealEmails->claimDue($this->month);
    }
}
