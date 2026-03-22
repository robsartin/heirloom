# 6. Image upload and display strategy

Date: 2026-03-22

## Status

Accepted

## Context

Admins need to upload ~1000 painting images (JPEG and PNG). Images vary in size and aspect ratio. The gallery page shows multiple paintings in a grid, so images need to fit uniformly without distortion.

## Decision

- **Upload:** Admin uploads images via a multi-file form (`<input type="file" multiple>`). Only JPEG and PNG are accepted, validated server-side via `mime_content_type()`. Files are stored in `public/uploads/` with random 32-hex-character filenames to avoid collisions and directory traversal.
- **Display:** Images are displayed at their original resolution but CSS-resized. Gallery thumbnails use `object-fit: contain` within a `4:3 aspect-ratio` container, preserving the painting's aspect ratio with a neutral background fill. Detail view uses `max-height: 600px` with `object-fit: contain`.
- **No server-side resizing:** Original files are served as-is. The browser handles scaling via CSS. This avoids a dependency on GD/Imagick for processing.

## Consequences

- **Easier:** No image processing pipeline, no thumbnail generation, no storage of multiple sizes. Upload is simple and fast. Batch upload supported.
- **Harder:** Large original files are served to all clients, including mobile. Bandwidth usage will be higher than if thumbnails were generated. Page load for the gallery could be slow if originals are very large.
- **Mitigation:** `loading="lazy"` is set on gallery images so only visible images are loaded. If performance becomes an issue, server-side thumbnail generation can be added later without changing the data model.
