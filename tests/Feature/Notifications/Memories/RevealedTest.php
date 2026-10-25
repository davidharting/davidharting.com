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

function readyPhotoOn(Memory $memory, int $position, ?string $caption = null, int $bytes = 450_000): MemoryPhoto
{
    $photo = MemoryPhoto::factory()->for($memory)->ready()->create(['position' => $position, 'caption' => $caption, 'web_bytes' => $bytes]);
    Storage::disk('private')->put($photo->web_path, "web copy {$position}");

    return $photo;
}

/**
 * The reveal email as it would go out, claimed at the given time or now.
 */
function revealMailFor(User $recipient, ?string $claimedAt = null): MailMessage
{
    return (new Revealed(revealedOctober(), CarbonImmutable::parse($claimedAt)))->toMail($recipient);
}

describe('with both memories shared', function () {
    test('names the month and links to the month page', function () {
        /** @var TestCase $this */
        octoberSharedBy($this->david, 'Mine.');
        octoberSharedBy($this->katie, 'Hers.');

        $mail = revealMailFor($this->david);

        expect($mail->subject)->toBe('Your October 2026 memories')
            ->and($mail->viewData['greeting'])->toBe('Hi David,')
            ->and($mail->viewData['intro'])->toBe("You've both shared, so here are both of your October 2026 memories to read and keep.")
            ->and($mail->viewData['actionText'])->toBe('Read them on the site')
            ->and($mail->viewData['actionUrl'])->toBe(route('memories.show', revealedOctober()));
    });

    test('carries both memories, the other person\'s first', function () {
        /** @var TestCase $this */
        octoberSharedBy($this->david, 'We carved pumpkins.');
        octoberSharedBy($this->katie, 'We went apple picking.');

        $rendered = (string) revealMailFor($this->david)->render();

        expect($rendered)->toContain('We went apple picking.', 'We carved pumpkins.')
            ->and(strpos($rendered, "Katie's October 2026 memory"))->toBeLessThan(strpos($rendered, 'Your October 2026 memory'));
    });
});

describe('with only the other person\'s memory shared', function () {
    test('carries just theirs, leaving out a draft', function () {
        /** @var TestCase $this */
        octoberSharedBy($this->katie, 'We went apple picking.');
        Memory::factory()->for($this->david)->forMonth(CalendarMonth::of(2026, 10))->create(['body' => 'Half a thought', 'shared_at' => null]);

        $mail = revealMailFor($this->david);
        $rendered = (string) $mail->render();

        expect($mail->subject)->toBe("Katie's October 2026 memory")
            ->and($mail->viewData['intro'])->toBe("Katie's October 2026 memory is ready to read. Here's a copy to keep.")
            ->and($mail->viewData['actionText'])->toBe('Read it on the site')
            ->and($rendered)->toContain('We went apple picking.')
            ->and($rendered)->not->toContain('Half a thought');
    });

    test('leaves out a memory shared after the email was claimed, which goes out as its own late share', function () {
        /** @var TestCase $this */
        octoberSharedBy($this->katie, 'We went apple picking.', sharedAt: '2026-11-10T14:00:00Z');
        octoberSharedBy($this->david, 'We carved pumpkins.', sharedAt: '2026-12-02T14:00:00Z');

        $rendered = (string) revealMailFor($this->david, claimedAt: '2026-12-01T14:00:00Z')->render();

        expect($rendered)->toContain('We went apple picking.')
            ->and($rendered)->not->toContain('We carved pumpkins.');
    });
});

describe('the memory text', function () {
    test('keeps the line breaks the author typed', function () {
        /** @var TestCase $this */
        octoberSharedBy($this->katie, "Apples\nPumpkins\n\nA new paragraph.");

        $rendered = (string) revealMailFor($this->david)->render();

        expect($rendered)->toMatch('/Apples<br\s*\/?>\s*Pumpkins/')
            ->and($rendered)->toMatch('/<p[^>]*>A new paragraph.<\/p>/');
    });

    test('shows Markdown characters as written instead of formatting with them', function () {
        /** @var TestCase $this */
        octoberSharedBy($this->katie, "# Not a heading\n- not a list\n1. not a list either\nSo *very* [tired](https://example.com)\n    not code");

        $rendered = (string) revealMailFor($this->david)->render();

        expect($rendered)->toContain('# Not a heading', '- not a list', '1. not a list either', 'So *very* [tired](https://example.com)', 'not code')
            ->and($rendered)->not->toContain('<h1', '<ul', '<ol', '<em>', '<a href="https://example.com"', '<code>');
    });

    test('escapes HTML', function () {
        /** @var TestCase $this */
        octoberSharedBy($this->katie, '<script>alert(1)</script>');

        $rendered = (string) revealMailFor($this->david)->render();

        expect($rendered)->toContain('&lt;script&gt;')
            ->and($rendered)->not->toContain('<script>');
    });
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

    test('lists each attached photo with its caption', function () {
        /** @var TestCase $this */
        $katies = octoberSharedBy($this->katie, 'Apples.');
        readyPhotoOn($katies, position: 0, caption: 'The *best* orchard');
        readyPhotoOn($katies, position: 1);

        $rendered = (string) revealMailFor($this->david)->render();

        expect($rendered)->toContain('Photo 1: The *best* orchard', 'Photo 2</li>');
    });

    test('leaves out photos that are processing or failed, and says so', function () {
        /** @var TestCase $this */
        $katies = octoberSharedBy($this->katie, 'Apples.');
        readyPhotoOn($katies, position: 0);
        MemoryPhoto::factory()->for($katies)->create(['position' => 1]);
        MemoryPhoto::factory()->for($katies)->failed()->create(['position' => 2]);

        $mail = revealMailFor($this->david);

        expect($mail->rawAttachments)->toHaveCount(1)
            ->and((string) $mail->render())->toContain("2 photos aren't attached. See them on the site.");
    });

    test('leaves out photos past the size the email can carry, and says so', function () {
        /** @var TestCase $this */
        $katies = octoberSharedBy($this->katie, 'Apples.');
        readyPhotoOn($katies, position: 0, bytes: 9_000_000);
        $davids = octoberSharedBy($this->david, 'Pumpkins.');
        readyPhotoOn($davids, position: 0, bytes: 5_000_000);
        readyPhotoOn($davids, position: 1, bytes: 2_000_000);

        $mail = revealMailFor($this->david);

        expect(collect($mail->rawAttachments)->pluck('name')->all())->toBe(['katie-2026-10-1.jpg', 'david-2026-10-1.jpg'])
            ->and((string) $mail->render())->toContain("1 photo isn't attached. See it on the site.");
    });

    test('leaves out removed photos', function () {
        /** @var TestCase $this */
        $katies = octoberSharedBy($this->katie, 'Apples.');
        readyPhotoOn($katies, position: 0)->delete();

        $mail = revealMailFor($this->david);

        expect($mail->rawAttachments)->toBeEmpty()
            ->and((string) $mail->render())->toContain('Apples.')
            ->and((string) $mail->render())->not->toContain('Photo 1', "isn't attached");
    });
});
