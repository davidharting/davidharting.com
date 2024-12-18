<?php

namespace Tests\Feature\Livewire\Media;

use App\Livewire\Media\Logbook;
use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LogbookTest extends TestCase
{
    use RefreshDatabase;

    private function createBook(
        string $title,
        string $author,
        string $finishedAt,
    ) {
        Media::factory()
            ->book()
            ->for(Creator::factory(['name' => $author]))
            ->has(MediaEvent::factory()->finished()->state(['occurred_at' => $finishedAt]), 'events')
            ->create(['title' => $title]);
    }

    /** @test */
    public function empty_state()
    {
        Livewire::test(Logbook::class)
            ->assertStatus(200)
            ->assertSeeText('No items');
    }

    /** @test */
    public function displays_logbook()
    {
        $this->createBook('The Hobbit', 'J.R.R. Tolkien', '2023-02-07');
        $this->createBook('The Da Vinci Code', 'Dan Brown', '2022-06-17');
        $this->createBook('The Alchemist', 'Paulo Coelho', '2021-12-25');
        Livewire::test(Logbook::class)
            ->assertStatus(200)
            ->assertSeeHtmlInOrder([
                'Media Log',

                '2023 February 07',
                'The Hobbit',
                'J.R.R. Tolkien',

                '2022 June 17',
                'The Da Vinci Code',
                'Dan Brown',

                '2021 December 25',
                'The Alchemist',
                'Paulo Coelho',
            ]);
    }
}
