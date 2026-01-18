<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Actions\GoodreadsImport\Importer as GoodreadsImporter;
use App\Actions\SofaImport\Importer as SofaImporter;
use App\Models\Creator;
use App\Models\Media;
use App\Models\MediaEvent;
use App\Models\Note;
use App\Models\Page;
use App\Models\Player;
use App\Models\Score;
use App\Models\Scorecard;
use App\Models\Upclick;
use App\Models\User;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! App::environment('local')) {
            throw new Exception('This seeder can only be run in the local environment');
        }
        User::factory()->create([
            'name' => 'Adam Min',
            'email' => 'admin@example.com',
            'is_admin' => true,
        ]);

        $frodo = User::factory()->create([
            'name' => 'Frodo Baggins',
            'email' => 'frodo@example.com',
        ]);

        User::factory(10)->create();

        // Anonymous clicks
        Upclick::factory(500)->create();
        // Frodos clicks
        Upclick::factory(200)->create(['user_id' => $frodo->id]);

        Scorecard::factory(20)->addPlayers()->create();

        $conkers = Scorecard::factory()->makeOne([
            'title' => 'Conkers',
        ]);

        $conkers->save();

        $conkers->players()->saveMany([
            Player::factory()->makeOne([
                'name' => 'Frodo Baggins',
            ]),
            Player::factory()->makeOne([
                'name' => 'Samwise Gamgee',
            ]),
            Player::factory()->makeOne([
                'name' => 'Peregrin Took',
            ]),
            Player::factory()->makeOne([
                'name' => 'Meriadoc Brandybuck',
            ]),
        ]);

        for ($round = 1; $round <= 10; $round++) {
            $conkers->players->each(function (Player $player, int $key) use ($round) {
                $player->scores()->save(
                    Score::factory()->makeOne([
                        'round' => $round,
                    ])
                );
            });
        }

        (new GoodreadsImporter(app_path('Actions/GoodreadsImport/data/goodreads-export-20241129.csv')))->import(null);
        (new SofaImporter)->import();

        Media::factory()
            ->book()
            ->for(Creator::factory(['name' => 'Gil G. Mesh']))
            ->has(MediaEvent::factory()->started()->at(Carbon::now()), 'events')
            ->has(MediaEvent::factory()->state(['comment' => 'It was great!'])->finished()->at(Carbon::now()), 'events')
            ->create(['title' => 'What a Time to Be Alive', 'note' => "Recommended by Kirk Hamilton.\nA fun, quick read."]);

        Note::factory(20)->create();
        Note::factory(25)->leadOnly()->create();
        Note::factory(15)->noLead()->create();
        Note::factory(5)->contentOnly()->create();

        // Pages for development
        Page::create([
            'slug' => 'about',
            'title' => 'About Me',
            'is_published' => true,
            'markdown_content' => <<<'MARKDOWN'
I'm a software engineer who loves building things for the web.

## What I Do

I work primarily with Laravel, PHP, and modern JavaScript frameworks. I'm passionate about:

- Writing clean, maintainable code
- Building great user experiences
- Learning new technologies
- Sharing knowledge through writing

## Get in Touch

Feel free to reach out if you want to chat about web development, technology, or anything else!
MARKDOWN
        ]);

        Page::create([
            'slug' => 'projects',
            'title' => 'My Projects',
            'is_published' => false,
            'markdown_content' => <<<'MARKDOWN'
Here are some projects I'm working on or have completed.

## Current Projects

### This Website
Built with Laravel and Tailwind CSS. Features include:
- Blog with markdown support
- Admin panel with Filament
- Custom pages system

### Other Projects
More coming soon...
MARKDOWN
        ]);

        // Kitchen sink markdown example
        Note::factory()->create([
            'title' => 'Markdown Kitchen Sink',
            'slug' => 'markdown-kitchen-sink',
            'lead' => 'A comprehensive demonstration of markdown features supported by this site.',
            'visible' => true,
            'markdown_content' => <<<'MARKDOWN'
## Heading 2
### Heading 3
#### Heading 4
##### Heading 5
###### Heading 6

## Text Formatting

This paragraph demonstrates **bold text**, *italic text*, and ***bold italic text***.

You can also use underscores for _italic_ and __bold__ formatting.

~~Strikethrough~~ text is also supported.

## Lists

### Unordered List
- First item
- Second item
  - Nested item
  - Another nested item
- Third item

### Ordered List
1. First step
2. Second step
   1. Substep A
   2. Substep B
3. Third step

## Links and References

Here's a [link to Anthropic](https://anthropic.com).

You can also use reference-style links like [this one][ref].

[ref]: https://claude.ai "Claude AI"

### Images

Regular markdown image with title attribute (shows tooltip on hover):

![Placeholder image showing mountain landscape](https://placehold.co/600x400/EEE/31343C?text=Mountain+Landscape "A beautiful mountain landscape at sunset")

Semantic HTML version with proper caption:

<figure>
  <img src="https://placehold.co/600x400/AAA/31343C?text=Semantic+Image" alt="Another landscape">
  <figcaption>This caption is always visible and semantically correct.</figcaption>
</figure>

## Blockquotes

Basic blockquote:

> This is a blockquote.
> It can span multiple lines.
>
> > And can be nested too!

### Blockquote with Attribution

Standard markdown approach (attribution after blockquote):

> The best way to predict the future is to invent it.

— Alan Kay

Semantic HTML version with figure and figcaption:

<figure>
  <blockquote>
    <p>Programs must be written for people to read, and only incidentally for machines to execute.</p>
  </blockquote>
  <figcaption>— Harold Abelson, <cite>Structure and Interpretation of Computer Programs</cite></figcaption>
</figure>

## Code

Inline code looks like `const x = 42;`

Fenced code blocks:

```javascript
function greet(name) {
    return `Hello, ${name}!`;
}

console.log(greet('World'));
```

```python
def fibonacci(n):
    if n <= 1:
        return n
    return fibonacci(n-1) + fibonacci(n-2)

print(fibonacci(10))
```

## Horizontal Rules

You can create horizontal rules:

---

## Tables

| Feature | Supported | Notes |
|---------|-----------|-------|
| Headers | ✓ | H1-H6 |
| Lists | ✓ | Ordered & Unordered |
| Code | ✓ | Inline & Blocks |
| Tables | ✓ | Like this one! |

## Task Lists

- [x] Implement markdown support
- [x] Add HTML sanitization
- [ ] Write more notes
- [ ] Ship it!

## Special Characters

You can escape special characters: \* \_ \[ \] \# \+

And use HTML entities: &copy; &trade; &hearts;
MARKDOWN
        ]);
    }
}
