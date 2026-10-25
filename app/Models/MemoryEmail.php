<?php

namespace App\Models;

use App\Jobs\SendMemoryEmail;
use App\Memories\MemoryEmailKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * One email of a kind to one person about one month. The row is the claim on
 * sending it, made before anything is sent, and there is only ever one per
 * person, month and kind, so the email goes out at most once.
 *
 * Its state is derived, never stored:
 *
 * - Sending: neither of the states below yet. Its job is queued or running,
 *   or between tries.
 * - Sent: `sent_at` is set.
 * - Failed: sending ran out of tries, and `failure` says why. It waits for a
 *   manual retry of its failed job.
 */
class MemoryEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'month_id',
        'kind',
        'sent_at',
        'failed_at',
        'failure',
    ];

    protected function casts(): array
    {
        return [
            'kind' => MemoryEmailKind::class,
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * Claim the email and queue it to send. Returns null without queueing
     * anything when it was claimed before, even if that attempt failed.
     *
     * The claim and its queued job commit together, so a claim that can't be
     * queued is undone and the email is still there to claim next time.
     */
    public static function claim(User $user, Month $month, MemoryEmailKind $kind): ?self
    {
        return DB::transaction(function () use ($user, $month, $kind): ?self {
            $email = self::query()->createOrFirst([
                'user_id' => $user->id,
                'month_id' => $month->id,
                'kind' => $kind,
            ]);

            if (! $email->wasRecentlyCreated) {
                return null;
            }

            SendMemoryEmail::dispatch($email);

            return $email;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function month(): BelongsTo
    {
        return $this->belongsTo(Month::class);
    }

    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }
}
