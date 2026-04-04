---
name: review-changes
description: Post-implementation review. Use after making changes to catch duplication, misplaced additions, and inconsistencies before committing.
---

# Review Changes

Read the **full content** of every modified file — not just the diff. Then check for:

## What to look for

**Added when should have edited** — Did you append a new section or sentence covering something an existing section already handles? Merge it in instead.

**Duplication** — Same idea, example, or wording appearing more than once. Consolidate to one place.

**Inconsistencies across related files** — When multiple files describe the same concept (e.g. a parameter described in both a schema and an agent prompt), ensure the wording is consistent and neither contradicts the other.

**Incomplete coverage** — A fix applied in one place but not a related place that has the same problem.

## Then fix

Don't just report — fix what you find. Keep fixes minimal: edit existing content rather than adding more.
