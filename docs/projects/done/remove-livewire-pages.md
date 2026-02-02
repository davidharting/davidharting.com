# Project: Remove Livewire Content Pages

**What**: A multiple PR, small project to get rid of full-page Livewire components for content-focused pages
**Why**: Make content pages cache-friendly and remove livewire dependency for simple pages. Perhaps performance gains as well?

## Projects

- [x] AdminIndexPage
- [x] NotesIndexPage
- [x] ShowNotePage
- [x] MediaPage

## Methodology

For a given PR, follow these steps:

1. Convert all livewire tests to feature tests
   These should be 1-for-1 test ports, but instead of using Livewire testing helpers just use regular laravel testing features to fetch and manipulate pages

2. Make sure feature tests pass still pass

3. Convert Livewire full-page component to a vanilla Laravel controller + blade templates. Use a git mv to first move / rename any files to where the new natural location would be (e.g., Livewire pages should be moved to be the controller). Then commit before modifying. Hoping to get a clean git history and in my PRs be able to see a diff of live wire pages versus controllers

## Analysis

### ShowNotePage Already Has Feature Tests

`tests/Feature/Http/Notes/ShowNoteTest.php` contains feature tests (using `$this->get()`) rather than Livewire tests. This page may already be partially done, or these tests were written as feature tests from the start (which works since Livewire pages respond to normal HTTP requests). Could potentially skip step 1 for this component.

### AdminIndexPage Has Interactive Functionality

Unlike the other "content-focused" pages, `AdminIndexPage` has a `backupDatabase()` action that gets called via Livewire. Converting this will require:

- A POST route for the action
- A controller method to handle the form submission
- Updating the view to use a form instead of `wire:click`

This is more involved than the other pages.

### MediaPage Uses Query Parameters

The tests use `Livewire::withQueryParams(['list' => 'backlog'])`. The new controller will need to read the `list` query parameter from the request.

### Layout Still Uses wire:navigate

The shared layout at `resources/views/components/layout/app.blade.php` has `wire:navigate` on navigation links. To fully remove Livewire from content pages, these would need to become regular links. This is a broader change affecting the whole site and could be done as a final cleanup step after all pages are converted.

### Suggested Order

From simplest to most complex:

1. **ShowNotePage** - already has feature tests
2. **NotesIndexPage** - simple list display
3. **MediaPage** - list display with query param filtering
4. **AdminIndexPage** - has an action, most complex
