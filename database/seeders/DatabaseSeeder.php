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
            $conkers->players->each(function (Player $player) use ($round) {
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

        // Syntax highlighting test page
        Page::create([
            'slug' => 'syntax-highlighting-test',
            'title' => 'Syntax Highlighting Test',
            'is_published' => true,
            'markdown_content' => <<<'MARKDOWN'
A showcase of code blocks in various languages to test syntax highlighting.

## PHP

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class UserService
{
    public function __construct(
        private readonly UserRepository $repository
    ) {}

    public function getActiveUsers(): Collection
    {
        return $this->repository
            ->query()
            ->where('active', true)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
```

## JavaScript

```javascript
async function fetchUserData(userId) {
    const response = await fetch(`/api/users/${userId}`);

    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();
    return {
        ...data,
        fullName: `${data.firstName} ${data.lastName}`,
    };
}

// Usage with error handling
fetchUserData(42)
    .then(user => console.log(user.fullName))
    .catch(err => console.error('Failed to fetch:', err));
```

## TypeScript

```typescript
interface User {
    id: number;
    email: string;
    name: string;
    createdAt: Date;
}

type UserCreateInput = Omit<User, 'id' | 'createdAt'>;

class UserService {
    private users: Map<number, User> = new Map();

    create(input: UserCreateInput): User {
        const user: User = {
            ...input,
            id: this.users.size + 1,
            createdAt: new Date(),
        };
        this.users.set(user.id, user);
        return user;
    }

    findById(id: number): User | undefined {
        return this.users.get(id);
    }
}
```

## Python

```python
from dataclasses import dataclass
from typing import Optional
import asyncio

@dataclass
class Config:
    host: str = "localhost"
    port: int = 8080
    debug: bool = False

async def fetch_data(url: str) -> Optional[dict]:
    """Fetch JSON data from a URL."""
    async with aiohttp.ClientSession() as session:
        async with session.get(url) as response:
            if response.status == 200:
                return await response.json()
            return None

if __name__ == "__main__":
    config = Config(debug=True)
    print(f"Starting server on {config.host}:{config.port}")
```

## HTML

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Example Page</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
    <header class="site-header">
        <nav aria-label="Main navigation">
            <a href="/" class="logo">My Site</a>
            <ul class="nav-links">
                <li><a href="/about">About</a></li>
                <li><a href="/contact">Contact</a></li>
            </ul>
        </nav>
    </header>
    <main id="content">
        <h1>Welcome!</h1>
    </main>
</body>
</html>
```

## CSS

```css
:root {
    --color-primary: oklch(55% 0.15 250);
    --color-secondary: oklch(70% 0.12 180);
    --spacing-base: 1rem;
}

.card {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-base);
    padding: calc(var(--spacing-base) * 1.5);
    border-radius: 0.5rem;
    background: linear-gradient(135deg, white, #f5f5f5);
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
}

@media (prefers-color-scheme: dark) {
    .card {
        background: linear-gradient(135deg, #1a1a1a, #2d2d2d);
    }
}
```

## SQL

```sql
-- Find users with their order statistics
SELECT
    u.id,
    u.name,
    u.email,
    COUNT(o.id) AS total_orders,
    COALESCE(SUM(o.total), 0) AS lifetime_value,
    MAX(o.created_at) AS last_order_date
FROM users u
LEFT JOIN orders o ON o.user_id = u.id
WHERE u.active = true
    AND u.created_at >= '2024-01-01'
GROUP BY u.id, u.name, u.email
HAVING COUNT(o.id) > 0
ORDER BY lifetime_value DESC
LIMIT 100;
```

## Bash

```bash
#!/bin/bash
set -euo pipefail

# Deploy script for production
DEPLOY_DIR="/var/www/app"
BACKUP_DIR="/var/backups/app"

echo "Starting deployment..."

# Create backup
timestamp=$(date +%Y%m%d_%H%M%S)
tar -czf "$BACKUP_DIR/backup_$timestamp.tar.gz" "$DEPLOY_DIR"

# Pull latest changes
cd "$DEPLOY_DIR"
git fetch origin main
git reset --hard origin/main

# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# Run migrations
php artisan migrate --force

echo "Deployment complete!"
```

## JSON

```json
{
    "name": "my-project",
    "version": "1.0.0",
    "description": "A sample project configuration",
    "scripts": {
        "dev": "vite",
        "build": "vite build",
        "test": "vitest"
    },
    "dependencies": {
        "vue": "^3.4.0",
        "axios": "^1.6.0"
    },
    "devDependencies": {
        "vite": "^5.0.0",
        "typescript": "^5.3.0"
    }
}
```

## Go

```go
package main

import (
    "encoding/json"
    "fmt"
    "net/http"
)

type User struct {
    ID    int    `json:"id"`
    Name  string `json:"name"`
    Email string `json:"email"`
}

func handleGetUser(w http.ResponseWriter, r *http.Request) {
    user := User{
        ID:    1,
        Name:  "Frodo Baggins",
        Email: "frodo@shire.me",
    }

    w.Header().Set("Content-Type", "application/json")
    json.NewEncoder(w).Encode(user)
}

func main() {
    http.HandleFunc("/user", handleGetUser)
    fmt.Println("Server starting on :8080")
    http.ListenAndServe(":8080", nil)
}
```

## Rust

```rust
use std::collections::HashMap;

#[derive(Debug, Clone)]
struct Cache<T> {
    data: HashMap<String, T>,
    max_size: usize,
}

impl<T: Clone> Cache<T> {
    fn new(max_size: usize) -> Self {
        Cache {
            data: HashMap::new(),
            max_size,
        }
    }

    fn get(&self, key: &str) -> Option<&T> {
        self.data.get(key)
    }

    fn set(&mut self, key: String, value: T) -> Result<(), &'static str> {
        if self.data.len() >= self.max_size && !self.data.contains_key(&key) {
            return Err("Cache is full");
        }
        self.data.insert(key, value);
        Ok(())
    }
}

fn main() {
    let mut cache: Cache<i32> = Cache::new(100);
    cache.set("answer".to_string(), 42).unwrap();
    println!("Cached value: {:?}", cache.get("answer"));
}
```

## YAML

```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "8080:8080"
    environment:
      - DATABASE_URL=postgres://user:pass@db:5432/app
      - REDIS_URL=redis://cache:6379
    depends_on:
      - db
      - cache
    volumes:
      - ./storage:/app/storage

  db:
    image: postgres:16-alpine
    environment:
      POSTGRES_USER: user
      POSTGRES_PASSWORD: pass
      POSTGRES_DB: app
    volumes:
      - postgres_data:/var/lib/postgresql/data

  cache:
    image: redis:7-alpine

volumes:
  postgres_data:
```
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

        // Embed samples
        Note::factory()->create([
            'title' => 'Embed Samples',
            'slug' => 'embed-samples',
            'lead' => 'Examples of embedded content from various platforms.',
            'visible' => true,
            'markdown_content' => <<<'MARKDOWN'
## Apple Music

<iframe allow="autoplay *; encrypted-media *;" frameborder="0" height="150" style="width:100%;max-width:660px;overflow:hidden;background:transparent;" sandbox="allow-forms allow-popups allow-same-origin allow-scripts allow-storage-access-by-user-activation allow-top-navigation-by-user-activation" src="https://embed.music.apple.com/us/album/homecoming/1024335136?i=1024336144"></iframe>

## YouTube

<iframe width="560" height="315" src="https://www.youtube.com/embed/H6ufcNxeAXU?si=Ny-vGmwGXWnUuFzK" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>

## Spotify

<iframe style="border-radius:12px" src="https://open.spotify.com/embed/track/4cOdK2wGLETKBW3PvgPWqT?utm_source=generator" width="100%" height="152" frameBorder="0" allowfullscreen="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>

## Vimeo

<iframe src="https://player.vimeo.com/video/347119375?h=be462f9adc" width="640" height="360" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>

## Bluesky

<blockquote class="bluesky-embed" data-bluesky-uri="at://did:plc:pqeikn4fnyts5seuh5bazj7b/app.bsky.feed.post/3la4vfasktq27" data-bluesky-cid="bafyreidl4kaed27iku6slbtccliuiqhr3p2rsjku4sntblb4spbb4kswwy" data-bluesky-embed-color-mode="system"><p lang="en">I like to think that Yoshi gets the zoomies</p>&mdash; David Harting (<a href="https://bsky.app/profile/did:plc:pqeikn4fnyts5seuh5bazj7b?ref_src=embed">@davidharting.com</a>) <a href="https://bsky.app/profile/did:plc:pqeikn4fnyts5seuh5bazj7b/post/3la4vfasktq27?ref_src=embed">November 4, 2024 at 9:09 AM</a></blockquote><script async src="https://embed.bsky.app/static/embed.js" charset="utf-8"></script>

## GitHub Gist

GitHub Gist embeds use `document.write()` which only works during initial page load. Since this content is rendered after the page loads, gist script embeds won't display. Consider linking to gists instead:

[View Gist: davidharting/54ce4d8dcb597c657d9f6725c9465249](https://gist.github.com/davidharting/54ce4d8dcb597c657d9f6725c9465249)
MARKDOWN
        ]);
    }
}
