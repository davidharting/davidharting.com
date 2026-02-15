---
name: laravel-media-library-and-public-bucket
description: Set up a public bucket, integrate with Laravel Media Library, and possibly filament
status: in-progress
---

# Laravel Media Library and Public Bucket

## Background

I want to be able to reference / include media on my Notes and Pages. For instance, I might want to include a PDF on a Page for user's to donwload, or to include a transformed image on a Note to share pictures of a trip.

Whenever I upload an image, I want some way to ensure that I do not retain a version that has EXIF data and delete that from the bucket. i.e., I think I want to delete the originals.

I think with Filament, markdown pages can _not_ directly have Laravel Media Library attachments. The rich editor can, but I'm not using the rich editor.

So because of that, I wonder if the best way for me to integrate is to have a centralized media library - like a Media Eloquent model. I could then attach media-library media to that.
Of course, then Media model is a bit of a redundant name... Hm we can work on that I guess? or brainstorm other ways to assocaite it.

I guess what i'm saying is the expected flow given that I cannot attach media directly to Filament markdown fields is that I instead need to use a different portion of my Filaent admin to upload media (and have it transformed if appropriate), and then I get a link and reference that in my markdown.

Maybe where that leaves me is I actually need to attach media directly to the Note and Page objects instead of having markdown handle that, and somehow create filament inputs for those attachments? idk that is part of what i'm having you plan.
