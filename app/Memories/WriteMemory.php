<?php

namespace App\Memories;

use App\Models\Memory;
use App\Models\User;
use App\Support\Month;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saving and sharing one person's memory for one month.
 *
 * Every write names the `lock_version` the writer last saw, or null when it
 * saw no memory at all. If the memory has moved on since, the write is
 * refused with MemoryChangedElsewhere rather than overwriting the newer text.
 *
 * Saving never shares. A memory row only exists once there is something in
 * it, and a shared memory can't be emptied, because memories are never
 * deleted.
 *
 * This class performs no authorization. The caller is expected to have
 * checked that the author may write about the month.
 */
class WriteMemory
{
    /**
     * Save the body, returning the memory as saved, or null when there was
     * nothing to save yet.
     *
     * @throws MemoryChangedElsewhere
     * @throws ValidationException when emptying a shared memory.
     */
    public function save(User $author, Month $month, string $body, ?int $expectedVersion): ?Memory
    {
        $memory = $this->find($author, $month);

        if ($memory?->lock_version !== $expectedVersion) {
            throw new MemoryChangedElsewhere;
        }

        if ($memory === null) {
            return trim($body) === '' ? null : $this->start($author, $month, $body);
        }

        if ($memory->isShared() && trim($body) === '') {
            throw ValidationException::withMessages(['body' => "A shared memory can't be empty."]);
        }

        $this->update($memory, $expectedVersion, ['body' => $body]);

        return $memory->refresh();
    }

    /**
     * Save the body and share the memory. Sharing an already shared memory
     * just saves it; it keeps its first `shared_at`.
     *
     * @throws MemoryChangedElsewhere
     * @throws ValidationException when the body is empty.
     */
    public function share(User $author, Month $month, string $body, ?int $expectedVersion): Memory
    {
        if (trim($body) === '') {
            throw ValidationException::withMessages(['body' => "There's nothing to share yet."]);
        }

        return DB::transaction(function () use ($author, $month, $body, $expectedVersion) {
            $memory = $this->save($author, $month, $body, $expectedVersion);

            if ($memory->isDraft()) {
                $this->update($memory, $memory->lock_version, ['shared_at' => now()]);
            }

            return $memory->refresh();
        });
    }

    private function find(User $author, Month $month): ?Memory
    {
        return Memory::query()
            ->where('user_id', $author->id)
            ->where('month', $month->toDateString())
            ->first();
    }

    /**
     * Create the memory on its first save with content. If another device
     * created it first, this one is stale.
     */
    private function start(User $author, Month $month, string $body): Memory
    {
        try {
            return Memory::query()->create([
                'user_id' => $author->id,
                'month' => $month,
                'body' => $body,
            ])->refresh();
        } catch (UniqueConstraintViolationException) {
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
