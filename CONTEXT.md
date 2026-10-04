# davidharting.com

David Harting's personal website: a place he publishes writing and tracks the media he reads, watches, plays and listens to.

## Language

### Writing

**Note**:
A piece of writing published on the site. Has its own page and appears in the notes index and feed.
_Avoid_: Post, article, blog post, entry

### Media library

**Media**:
A single book, album, movie, TV show or video game that David tracks. Identified by its title, its type and its creator, matched case-insensitively.
_Avoid_: Item, title, work, entry

**Creator**:
The person or group responsible for a piece of media — author, director, artist, studio. One creator has many media.
_Avoid_: Author, artist, maker

**Media event**:
Something that happened between David and a piece of media at a point in time: he started it, finished it, abandoned it, or had a thought about it. Tracking state is derived from these events, never stored directly.
_Avoid_: Log entry, activity, record

**Remark**:
Private text David has written about a piece of media, stored as `media.note`. Visible only to him.
_Avoid_: Note, description, blurb

**Comment**:
Free text attached to a single media event, stored as `media_events.comment`. Travels with the moment it describes rather than with the media as a whole.
_Avoid_: Note, remark, annotation

**Backlog**:
The media David has recorded but not yet started.
_Avoid_: Queue, to-read, wishlist

### Monthly memories

**Participant**:
One of the two people who write and read memories: David and Katie. A role in this feature, not an account; anyone who isn't a participant can't tell the feature exists.
_Avoid_: Member, user

**Memory**:
One person's entry for one past calendar month, written by David or Katie: free text alongside photos, each photo with an optional caption. A single memory may recount several moments or reflections; what makes it one memory is that each person has at most one per month. Private to David and Katie.
_Avoid_: Reflection, journal entry, post, note

**Draft**:
A memory its author is still writing. Only its author can read it, though the other person can see that it has been started. A month's memories can only be drafted once that month is over.
_Avoid_: Unpublished memory, work in progress

**Share**:
The deliberate act that turns a draft into a memory the other person may eventually read. Saving a draft is never sharing.
_Avoid_: Publish, post, submit

**Reveal**:
The moment both people's shared memories for a month become readable to each other. It happens when both have shared, or at the end of the following month, whichever comes first. A memory shared after its month's reveal is readable as soon as it is shared.
_Avoid_: Unlock, publish

**Reaction**:
An emoji one person leaves on the other's revealed memory. Any emoji, up to five per person per memory.
_Avoid_: Like, heart, vote

## Notes on this glossary

**Note, remark and comment are three different things**, and the distinction is load-bearing: a note is public writing, a remark belongs to a piece of media, a comment belongs to a moment.

Where a term names a specific column rather than a general concept, the column is given with it. `media.note` predates this vocabulary and is a remark, not a note.
