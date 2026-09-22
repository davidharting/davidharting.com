# davidharting.com

David Harting's personal website: a place he publishes writing and tracks the media he reads, watches, plays and listens to.

## Language

### Writing

**Note**:
A piece of writing published on the site. Has its own page and appears in the notes index and feed.
_Avoid_: Post, article, blog post, entry

### Media library

**Media**:
A single work David tracks — a book, album, movie, TV show or video game. Identified by its title, its type and its creator, matched case-insensitively.
_Avoid_: Item, title, work, entry

**Creator**:
The person or group responsible for a work — author, director, artist, studio. One creator has many media.
_Avoid_: Author, artist, maker

**Media event**:
Something that happened between David and a work at a point in time: he started it, finished it, abandoned it, or had a thought about it. A work's tracking state is derived from its events, never stored directly.
_Avoid_: Log entry, activity, record

**Remark**:
Private text David has written about a work. Visible only to him.
_Avoid_: Note, description, blurb

**Comment**:
Free text attached to a single media event. Travels with the moment it describes rather than with the work as a whole.
_Avoid_: Note, remark, annotation

**Backlog**:
The media David has recorded but not yet started.
_Avoid_: Queue, to-read, wishlist

## Notes on this glossary

**Note, remark and comment are three different things**, and the distinction is load-bearing: a note is public writing, a remark belongs to a work, a comment belongs to a moment. Storage still uses older names in places — a remark lives in `media.note` — but the resolved terms above are what code, tool schemas and conversation should use.
