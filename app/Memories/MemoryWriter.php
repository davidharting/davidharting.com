<?php

namespace App\Memories;

use App\Jobs\ClaimRevealEmails;
use App\Models\Memory;
use App\Models\User;
use App\Support\CalendarMonth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Writes one person's memory for one month, never overwriting a newer
 * version than the one the writer last saw.
 *
 * This class performs no authorization. The caller is expected to have
 * checked that the author may write about the month.
 */
final readonly class MemoryWriter
{
    /**
     * The longest memory, in characters. About 3,500 words.
     */
    public const int MAX_LENGTH = 20_000;

    public function __construct(
        public User $author,
        public CalendarMonth $month,
    ) {}

    /**
     * Save the body without sharing it. Returns the memory as saved, or null
     * when there is no memory afterwards: a draft only exists while it has
     * text or photos, so emptying a draft without photos deletes it. Removed
     * photos count, so a removed photo can always be put back. A shared memory
     * can't be left with neither text nor photos, because shared memories are
     * never deleted.
     *
     * @param  ?int  $expectedVersion  The `lock_version` the writer last saw, or null if it saw no memory.
     *
     * @throws MemoryChangedElsewhere when the memory has moved past the expected version.
     * @throws ValidationException when emptying a shared memory without photos, or when the body is too long.
     */
    public function save(string $body, ?int $expectedVersion): ?Memory
    {
        if (mb_strlen($body) > self::MAX_LENGTH) {
            throw ValidationException::withMessages([
                'body' => 'This is longer than a memory can be ('.number_format(self::MAX_LENGTH).' characters), so the last changes weren\'t saved.',
            ]);
        }

        $memory = $this->find();

        if ($memory?->lock_version !== $expectedVersion) {
            throw new MemoryChangedElsewhere;
        }

        if ($memory === null) {
            return trim($body) === '' ? null : $this->start($body);
        }

        if ($memory->isShared() && trim($body) === '' && ! $memory->photos()->exists()) {
            throw ValidationException::withMessages(['body' => "A shared memory can't be empty."]);
        }

        if (trim($body) === '' && ! $memory->photos()->withTrashed()->exists()) {
            $this->delete($memory, $expectedVersion);

            return null;
        }

        $this->update($memory, $expectedVersion, ['body' => $body]);

        return $memory->refresh();
    }

    /**
     * Save the body and share the memory. Sharing an already shared memory
     * just saves it; it keeps its first `shared_at`. A memory with photos can
     * be shared without text. Once the share commits, it queues a job
     * to claim the reveal emails it makes due.
     *
     * @param  ?int  $expectedVersion  The `lock_version` the writer last saw, or null if it saw no memory.
     *
     * @throws MemoryChangedElsewhere when the memory has moved past the expected version.
     * @throws ValidationException when there is neither text nor a photo to share.
     */
    public function share(string $body, ?int $expectedVersion): Memory
    {
        if (trim($body) === '' && ! $this->find()?->photos()->exists()) {
            throw ValidationException::withMessages(['body' => "There's nothing to share yet."]);
        }

        $justShared = false;

        $memory = DB::transaction(function () use ($body, $expectedVersion, &$justShared) {
            $memory = $this->save($body, $expectedVersion);

            if ($memory->isDraft()) {
                $this->update($memory, $memory->lock_version, ['shared_at' => now()]);
                $justShared = true;
            }

            return $memory->refresh();
        });

        if ($justShared) {
            $this->queueRevealEmails($memory);
        }

        return $memory;
    }

    /**
     * The memory to add photos to: the author's memory for the month, started
     * without text if there isn't one yet. Photos stay out of `lock_version`,
     * so this never checks or bumps it. The memory comes back with
     * `wasRecentlyCreated` set when this call started it.
     */
    public function findOrStartForPhotos(): Memory
    {
        $memory = $this->find();

        if ($memory !== null) {
            return $memory;
        }

        try {
            return $this->start('');
        } catch (MemoryChangedElsewhere) {
            return $this->find() ?? $this->start('');
        }
    }

    /**
     * Delete a memory that has neither text nor photos, removed ones
     * included. Returns whether it was deleted.
     */
    public function discardIfEmpty(Memory $memory): bool
    {
        return Memory::query()
            ->whereKey($memory->id)
            ->where('body', '')
            ->whereDoesntHave('photos', fn (Builder $photos) => $photos->withTrashed())
            ->delete() > 0;
    }

    /**
     * Queue the job that claims the reveal emails a share made due. The share
     * has already saved, so failing to queue is only logged: the daily
     * memories:send-emails run claims any reveal email this misses.
     */
    private function queueRevealEmails(Memory $memory): void
    {
        try {
            Bus::dispatch(new ClaimRevealEmails($memory->month));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function find(): ?Memory
    {
        return Memory::query()
            ->where('user_id', $this->author->id)
            ->where('month_id', $this->month->toDateString())
            ->first();
    }

    /**
     * Create the memory on its first save with text, or its first photo. If
     * another device created it first, this one is stale.
     */
    private function start(string $body): Memory
    {
        try {
            return Memory::query()->create([
                'user_id' => $this->author->id,
                'month_id' => $this->month->toDateString(),
                'body' => $body,
            ])->refresh();
        } catch (UniqueConstraintViolationException) {
            throw new MemoryChangedElsewhere;
        }
    }

    /**
     * Delete an emptied draft, only if it is still at the expected version.
     */
    private function delete(Memory $memory, int $expectedVersion): void
    {
        $deleted = Memory::query()
            ->whereKey($memory->id)
            ->where('lock_version', $expectedVersion)
            ->delete();

        if ($deleted === 0) {
            throw new MemoryChangedElsewhere;
        }
    }

    /**
     * Update the memory only if it is still at the expected version, bumping
     * the version in the same statement.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function update(Memory $memory, int $expectedVersion, array $attributes): void
    {
        $updated = Memory::query()
            ->whereKey($memory->id)
            ->where('lock_version', $expectedVersion)
            ->update([...$attributes, 'lock_version' => DB::raw('lock_version + 1')]);

        if ($updated === 0) {
            throw new MemoryChangedElsewhere;
        }
    }
}
