<?php

use App\Models\Memory;
use App\Models\MemoryParticipant;
use App\Models\MemoryPhoto;
use App\Models\Month;
use App\Models\User;
use App\Notifications\Memories\Revealed;
use App\Support\CalendarMonth;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    Storage::fake('private');
    $this->travelTo('2026-11-20T14:00:00Z');
    $this->david = MemoryParticipant::factory()->for(User::factory()->state(['display_name' => 'David']))->create()->user;
    $this->katie = MemoryParticipant::factory()->for(User::factory()->state(['display_name' => 'Katie']))->create()->user;
});

function revealedOctober(): Month
{
    return Month::of(CalendarMonth::of(2026, 10));
}

function octoberSharedBy(User $author, string $body, string $sharedAt = '2026-11-10T14:00:00Z'): Memory
{
    return Memory::factory()->for($author)->forMonth(CalendarMonth::of(2026, 10))->create([
        'body' => $body,
        'created_at' => '2026-11-02T14:00:00Z',
        'shared_at' => $sharedAt,
    ]);
}

function readyPhotoOn(Memory $memory, int $position, ?string $caption = null): MemoryPhoto
{
    $photo = MemoryPhoto::factory()->for($memory)->ready()->create(['position' => $position, 'caption' => $caption]);
    Storage::disk('private')->put($photo->web_path, "web copy {$position}");

    return $photo;
}

/**
 * The reveal email as it would go out, claimed now.
 */
function revealMailFor(User $recipient): MailMessage
{
    return (new Revealed(revealedOctober(), CarbonImmutable::now()))->toMail($recipient);
}

test('names the month and links to the month page', function () {
    /** @var TestCase $this */
    octoberSharedBy($this->david, 'Mine.');
    octoberSharedBy($this->katie, 'Hers.');

    $mail = revealMailFor($this->david);

    expect($mail->subject)->toBe('October 2026 has revealed')
        ->and($mail->viewData['greeting'])->toBe('Hi David,')
        ->and($mail->viewData['actionUrl'])->toBe(route('memories.show', revealedOctober()));
});

test('carries both memories, the other person first, when both have shared', function () {
    /** @var TestCase $this */
    octoberSharedBy($this->david, 'We carved pumpkins.');
    octoberSharedBy($this->katie, 'We went apple picking.');

    $mail = revealMailFor($this->david);
    $rendered = (string) $mail->render();

    expect($mail->viewData['intro'])->toBe("You've both shared, so here are both of your October 2026 memories to read and keep.")
        ->and($rendered)->toContain('We went apple picking.', 'We carved pumpkins.')
        ->and(strpos($rendered, "Katie's memory"))->toBeLessThan(strpos($rendered, 'Your memory'));
});

test('carries only the other person\'s memory when theirs is still a draft', function () {
    /** @var TestCase $this */
    octoberSharedBy($this->katie, 'We went apple picking.');
    Memory::factory()->for($this->david)->forMonth(CalendarMonth::of(2026, 10))->create(['body' => 'Half a thought', 'shared_at' => null]);

    $mail = revealMailFor($this->david);
    $rendered = (string) $mail->render();

    expect($mail->viewData['intro'])->toBe("Katie's October 2026 memory is ready to read. Here it is to keep.")
        ->and($rendered)->toContain('We went apple picking.')
        ->and($rendered)->not->toContain('Half a thought');
});

test('leaves out a memory shared after the email was claimed, which goes out as its own late share', function () {
    /** @var TestCase $this */
    octoberSharedBy($this->katie, 'We went apple picking.', '2026-11-10T14:00:00Z');
    octoberSharedBy($this->david, 'We carved pumpkins.', '2026-12-02T14:00:00Z');

    $mail = (new Revealed(revealedOctober(), CarbonImmutable::parse('2026-12-01T14:00:00Z')))->toMail($this->david);

    expect((string) $mail->render())->not->toContain('We carved pumpkins.');
});

test('keeps the line breaks the author typed', function () {
    /** @var TestCase $this */
    octoberSharedBy($this->katie, "Apples\nPumpkins\n\nA new paragraph.");

    $rendered = (string) revealMailFor($this->david)->render();

    expect($rendered)->toMatch('/Apples<br\s*\/?>\s*Pumpkins/')
        ->and($rendered)->toMatch('/<p[^>]*>A new paragraph.<\/p>/');
});

test('escapes HTML in the memory text', function () {
    /** @var TestCase $this */
    octoberSharedBy($this->katie, '<script>alert(1)</script>');

    expect((string) revealMailFor($this->david)->render())->not->toContain('<script>');
});

describe('photos', function () {
    test('attaches the web copy of each ready photo in order, named for its author and month', function () {
        /** @var TestCase $this */
        $katies = octoberSharedBy($this->katie, 'Apples.');
        readyPhotoOn($katies, position: 1);
        readyPhotoOn($katies, position: 0);
        $davids = octoberSharedBy($this->david, 'Pumpkins.');
        readyPhotoOn($davids, position: 0);

        $attachments = collect(revealMailFor($this->david)->rawAttachments);

        expect($attachments->pluck('name')->all())->toBe(['katie-2026-10-1.jpg', 'katie-2026-10-2.jpg', 'david-2026-10-1.jpg'])
            ->and($attachments->pluck('data')->all())->toBe(['web copy 0', 'web copy 1', 'web copy 0'])
            ->and($attachments->pluck('options.mime')->unique()->all())->toBe(['image/jpeg']);
    });

    test('lists each attached photo beside its caption', function () {
        /** @var TestCase $this */
        $katies = octoberSharedBy($this->katie, 'Apples.');
        readyPhotoOn($katies, position: 0, caption: 'The orchard');
        readyPhotoOn($katies, position: 1);

        $rendered = (string) revealMailFor($this->david)->render();

        expect($rendered)->toContain('katie-2026-10-1.jpg: The orchard', 'katie-2026-10-2.jpg');
    });

    test('leaves out photos that are processing or failed, and says so', function () {
        /** @var TestCase $this */
        $katies = octoberSharedBy($this->katie, 'Apples.');
        readyPhotoOn($katies, position: 0);
        MemoryPhoto::factory()->for($katies)->create(['position' => 1]);
        MemoryPhoto::factory()->for($katies)->failed()->create(['position' => 2]);

        $mail = revealMailFor($this->david);

        expect($mail->rawAttachments)->toHaveCount(1)
            ->and((string) $mail->render())->toContain("2 photos weren't ready to attach. See them on the site.");
    });

    test('leaves out removed photos', function () {
        /** @var TestCase $this */
        $katies = octoberSharedBy($this->katie, 'Apples.');
        readyPhotoOn($katies, position: 0)->delete();

        $mail = revealMailFor($this->david);

        expect($mail->rawAttachments)->toBeEmpty()
            ->and((string) $mail->render())->not->toContain('ready to attach');
    });
});
