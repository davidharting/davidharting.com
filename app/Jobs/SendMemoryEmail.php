<?php

namespace App\Jobs;

use App\Models\MemoryEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends a claimed memory email and records that it went out.
 */
class SendMemoryEmail implements ShouldQueue
{
    use Queueable;

    /**
     * The first try and three more, so a mail provider outage of up to half
     * an hour still delivers.
     */
    public int $tries = 4;

    /**
     * Seconds to wait before the second, third and fourth tries.
     *
     * @var list<int>
     */
    public array $backoff = [60, 300, 1800];

    public function __construct(public MemoryEmail $email) {}

    /**
     * Send the email, unless it has already gone out. A manual retry after
     * a failure clears the failure once it sends.
     */
    public function handle(): void
    {
        if ($this->email->isSent()) {
            return;
        }

        $this->email->user->notifyNow($this->email->kind->notification($this->email));

        $this->email->update([
            'sent_at' => now(),
            'failed_at' => null,
            'failure' => null,
        ]);
    }

    /**
     * Record why sending gave up, once the email has run out of tries.
     */
    public function failed(Throwable $exception): void
    {
        $this->email->update([
            'failed_at' => now(),
            'failure' => Str::limit($exception->getMessage(), 1000),
        ]);
    }
}
