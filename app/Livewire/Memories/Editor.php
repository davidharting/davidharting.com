<?php

namespace App\Livewire\Memories;

use App\Memories\AddMemoryPhoto;
use App\Memories\MemoryChangedElsewhere;
use App\Memories\MemoryPhotos;
use App\Memories\MemoryWriter;
use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Models\MemoryPhoto;
use App\Models\Month;
use App\Support\CalendarMonth;
use App\View\Memories\MonthForViewer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The viewer's own memory for one month, on its own page. The text saves
 * as they type, photos save as each one lands, and the memory is shared only
 * through the Share sheet.
 *
 * The editor remembers the `lock_version` it last saw. When another device
 * saved the text in the meantime, it stops saving and asks which version to
 * keep. Photos and captions stay out of it: the last change saved wins.
 */
class Editor extends Component
{
    use WithFileUploads;

    /**
     * The largest photo the browser may upload, in kilobytes.
     */
    public const int MAX_PHOTO_KILOBYTES = 50 * 1024;

    /**
     * The kinds of photo the editor takes: what phones and cameras save, and
     * what libvips can read.
     *
     * @var list<string>
     */
    public const array PHOTO_MIME_TYPES = ['image/jpeg', 'image/png', 'image/heic', 'image/heif', 'image/webp'];

    /**
     * The month as `Y-m`.
     */
    #[Locked]
    public string $month;

    public string $body = '';

    /**
     * The version of the memory this editor last loaded or saved, or null
     * while it hasn't seen one.
     */
    #[Locked]
    public ?int $lockVersion = null;

    /**
     * Another device saved since this editor last did, so saving is paused.
     */
    #[Locked]
    public bool $changedElsewhere = false;

    /**
     * Shows the screen that follows sharing.
     */
    #[Locked]
    public bool $justShared = false;

    /**
     * The photo the browser is uploading, one at a time. It is added to the
     * memory as soon as it lands.
     */
    public ?TemporaryUploadedFile $upload = null;

    /**
     * How many photos the memory has, so the browser knows how many more
     * fit before it uploads them.
     */
    #[Locked]
    public int $photoCount = 0;

    public function boot(): void
    {
        $this->authorize('viewAny', Month::class);
    }

    public function mount(string $month): void
    {
        $this->month = $month;

        $this->load();
        $this->authorizeWriting();
    }

    public function updatedBody(): void
    {
        if ($this->changedElsewhere) {
            return;
        }

        $this->save();
    }

    public function save(): void
    {
        $this->write(fn (MemoryWriter $writer) => $writer->save($this->body, $this->lockVersion));
    }

    public function share(): void
    {
        $shared = $this->write(fn (MemoryWriter $writer) => $writer->share($this->body, $this->lockVersion));

        $this->justShared = $shared !== null;
    }

    /**
     * Resolve a conflict by taking the version saved on the other device.
     */
    public function useTheirs(): void
    {
        $this->load();
    }

    /**
     * Resolve a conflict by saving this editor's text over the other device's.
     */
    public function keepMine(): void
    {
        if (! $this->changedElsewhere) {
            return;
        }

        $this->lockVersion = $this->monthForViewer->viewerMemory?->lock_version;
        $this->changedElsewhere = false;

        $this->save();
    }

    public function keepEditing(): void
    {
        $this->justShared = false;
    }

    /**
     * Add the photo that just landed to the end of the memory, starting the
     * memory if this is the first thing in it. The browser hears
     * `upload-handled` either way, with the new photo's id when it was added,
     * so it can send the next one.
     */
    public function updatedUpload(): void
    {
        $upload = $this->upload;
        $this->upload = null;
        $photoId = null;

        try {
            validator(['upload' => $upload], ['upload' => [
                'required',
                'file',
                'mimetypes:'.implode(',', self::PHOTO_MIME_TYPES),
                'max:'.self::MAX_PHOTO_KILOBYTES,
            ]], ['upload.mimetypes' => "That file isn't a photo the editor can take."])->validate();

            $this->authorizeWriting();
            $memory = $this->writer()->findOrStartForPhotos();

            if ($memory->wasRecentlyCreated && $this->lockVersion === null) {
                $this->lockVersion = $memory->lock_version;
            }

            $photoId = (new AddMemoryPhoto)->handle(
                $memory,
                FileUploadConfiguration::path($upload->getFilename(), false),
            )->id;

            $this->resetErrorBag('upload');
            $this->photoSaved();
        } catch (ValidationException $exception) {
            $upload?->delete();

            throw ValidationException::withMessages(['upload' => $exception->validator->errors()->first()]);
        } finally {
            $this->dispatch('upload-handled', photoId: $photoId);
        }
    }

    public function caption(int $photoId, string $caption): void
    {
        $this->changePhotos(fn (MemoryPhotos $photos) => $photos->caption($photoId, $caption));
    }

    public function movePhoto(int $photoId, int $position): void
    {
        $this->changePhotos(fn (MemoryPhotos $photos) => $photos->move($photoId, $position));
    }

    /**
     * Remove a photo, and tell the browser with `photo-removed` so it can
     * offer to undo.
     */
    public function removePhoto(int $photoId): void
    {
        $this->changePhotos(fn (MemoryPhotos $photos) => $photos->remove($photoId));

        $this->dispatch('photo-removed', photoId: $photoId);
    }

    public function restorePhoto(int $photoId): void
    {
        $this->changePhotos(fn (MemoryPhotos $photos) => $photos->restore($photoId));
    }

    public function retryPhoto(int $photoId): void
    {
        $this->changePhotos(fn (MemoryPhotos $photos) => $photos->retry($photoId));
    }

    /**
     * The memory's photos in order, removed ones left out.
     *
     * @return Collection<int, MemoryPhoto>
     */
    protected function photos(): Collection
    {
        return $this->monthForViewer->viewerMemory?->photos ?? new Collection;
    }

    #[Computed]
    public function monthForViewer(): MonthForViewer
    {
        $viewer = auth()->user();

        return new MonthForViewer(
            Month::of($this->calendarMonth()),
            $viewer,
            MemoryParticipant::otherThan($viewer),
        );
    }

    protected function isShared(): bool
    {
        return (bool) $this->monthForViewer->viewerMemory?->isShared();
    }

    /**
     * The one line above the text about where the month stands, for example
     * "Katie has shared. Share yours to reveal both."
     */
    protected function standingLabel(): string
    {
        $monthForViewer = $this->monthForViewer;
        $other = $monthForViewer->other->displayName();
        $otherMemory = $monthForViewer->otherMemory;
        $revealed = $monthForViewer->month->hasRevealed();

        if ($this->isShared()) {
            $shared = 'Shared '.CalendarMonth::dayLabel($monthForViewer->viewerMemory->shared_at).'. Edits still save as you type, and ';

            return $shared.($revealed
                ? "{$other} sees them."
                : "{$other} sees them once {$this->calendarMonth()->name()} is revealed.");
        }

        if ($revealed) {
            return "{$this->calendarMonth()->name()} has revealed, so {$other} can read yours as soon as you share it.";
        }

        if ($otherMemory === null) {
            return "{$other} hasn't started yet.";
        }

        if ($otherMemory->isDraft()) {
            return "{$other} started on ".CalendarMonth::dayLabel($otherMemory->created_at)." and hasn't shared yet.";
        }

        return "{$other} has shared. Share yours to reveal both.";
    }

    /**
     * The Share sheet's first fact: when the other person can read it.
     */
    protected function whenTheyCanReadLabel(): string
    {
        $monthForViewer = $this->monthForViewer;
        $other = $monthForViewer->other->displayName();
        $name = $this->calendarMonth()->name();

        if ($monthForViewer->month->hasRevealed()) {
            return "{$name} has already revealed, so {$other} can read it right away.";
        }

        if ($monthForViewer->otherMemory?->isShared()) {
            return "{$other} has shared, so {$name} reveals as soon as you do.";
        }

        return "{$other} can read it once you've both shared, or on ".CalendarMonth::dayLabel($monthForViewer->month->revealDeadline()).'.';
    }

    public function render(): View
    {
        $this->photoCount = $this->photos()->count();

        return view('livewire.memories.editor');
    }

    protected function calendarMonth(): CalendarMonth
    {
        return CalendarMonth::parseYearMonth($this->month);
    }

    /**
     * Writing means the viewer may write in the month, and may update their
     * memory, or create it if there isn't one yet.
     */
    private function authorizeWriting(): void
    {
        $this->authorize('write', $this->monthForViewer->month);
        $memory = $this->monthForViewer->viewerMemory;

        if ($memory === null) {
            $this->authorize('create', Memory::class);

            return;
        }

        $this->authorize('update', $memory);
    }

    private function load(): void
    {
        unset($this->monthForViewer);
        $memory = $this->monthForViewer->viewerMemory;

        $this->body = $memory->body ?? '';
        $this->lockVersion = $memory?->lock_version;
        $this->changedElsewhere = false;
    }

    /**
     * Run a write, keeping the version it saved, or pause saving if another
     * device got there first. A write that saved tells the browser with
     * `memory-saved`, so the page can briefly show the save landing.
     *
     * @param  callable(MemoryWriter): ?Memory  $write
     */
    private function write(callable $write): ?Memory
    {
        $this->authorizeWriting();

        try {
            $memory = $write($this->writer());
        } catch (MemoryChangedElsewhere) {
            $this->changedElsewhere = true;

            return null;
        }

        unset($this->monthForViewer);
        $this->resetErrorBag('body');

        $wroteSomething = $memory !== null || $this->lockVersion !== null;
        $this->lockVersion = $memory?->lock_version;

        if ($wroteSomething) {
            $this->dispatch('memory-saved');
        }

        return $memory;
    }

    /**
     * Run a change to the viewer's photos. There is nothing to change before
     * the memory exists. A change that saved flashes the same check as text.
     *
     * @param  callable(MemoryPhotos): void  $change
     */
    private function changePhotos(callable $change): void
    {
        $this->authorizeWriting();
        $memory = $this->monthForViewer->viewerMemory;

        if ($memory === null) {
            return;
        }

        $this->resetErrorBag(['photo', 'caption']);
        $change(new MemoryPhotos($memory));
        $this->photoSaved();
    }

    private function photoSaved(): void
    {
        unset($this->monthForViewer);
        $this->dispatch('memory-saved');
    }

    private function writer(): MemoryWriter
    {
        return new MemoryWriter(auth()->user(), $this->calendarMonth());
    }
}
